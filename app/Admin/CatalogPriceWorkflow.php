<?php
namespace App\Admin;

use App\Models\CatalogPriceUpload;
use Illuminate\Support\Facades\{DB, Storage};
use Symfony\Component\Process\Process;

class CatalogPriceWorkflow
{
    public function fingerprint(): string
    {
        // Same serialization lock as the CLI importer, before locking product rows.
        DB::table('product_categories')->orderBy('id')->lockForUpdate()->first();
        $state = [];
        foreach (['products', 'product_categories', 'catalog_import_mappings', 'catalog_source_products'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->lockForUpdate()->get();
        }
        foreach (['scripts/catalog_preview.py', 'scripts/supplier_price.py', 'app/Admin/CatalogDraftImport.php', 'app/Admin/CatalogMissingStock.php', 'app/Admin/CatalogPriceSafety.php', 'config/catalog-taxonomy.json', 'config/catalog-reviewed-supplier.json'] as $file) $state[$file] = hash_file('sha256', base_path($file));
        $state['missing_stock_policy'] = 'clear-owned-missing-v1';
        $state['rules_version'] = config('catalog-import.rules');
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    public function prepare(CatalogPriceUpload $upload): void
    {
        $disk = Storage::disk('local');
        $dir = $upload->directory();
        $source = $disk->path($dir.'/source.xlsx');
        if (hash_file('sha256', $source) !== $upload->file_hash) throw new CatalogPriceException('Файл изменился. Загрузите его заново.');
        $zip = new \ZipArchive;
        if ($zip->open($source) !== true) throw new CatalogPriceException('Не удалось прочитать Excel. Нужен файл XLSX.');
        try {
            $bytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) $bytes += $zip->statIndex($i)['size'];
            if ($zip->numFiles > 10000 || $bytes > 200 * 1024 * 1024) throw new CatalogPriceException('Слишком большой распакованный Excel. Разделите файл.');
        } finally { $zip->close(); }
        $disk->put($dir.'/snapshot.json', DB::table('products')->orderBy('id')->get()->toJson(JSON_UNESCAPED_UNICODE));
        $process = new Process([config('catalog-import.python'), base_path('scripts/catalog_preview.py'), $source,
            '--existing', $disk->path($dir.'/snapshot.json'), '--output', $disk->path($dir)], base_path(), ['PYTHONUTF8' => '1']);
        $process->setTimeout(180);
        $process->run();
        if (! $process->isSuccessful()) {
            logger()->error('Catalog XLSX preparation failed', ['upload' => $upload->id, 'error' => $process->getErrorOutput()]);
            throw new CatalogPriceException('Не удалось проверить Excel. Нужен один лист с исходными колонками поддерживаемого прайса (32 или 9).');
        }
        $rows = json_decode($disk->get($dir.'/staged-products.json'), true, flags: JSON_THROW_ON_ERROR);
        $analysis = json_decode($disk->get($dir.'/summary.json'), true, flags: JSON_THROW_ON_ERROR);
        $profile = $analysis['source_profile'];
        $version = $analysis['rules_version'];
        $absent = count($analysis['absent_existing_ids']);
        if ($profile === 'supplier-price-32') {
            $ids = array_map(fn ($r) => hash('sha256', 'supplier-id:'.trim($r['supplier_id'])), $rows);
            $absent = DB::table('catalog_source_products')->where('source', $profile)->whereNotIn('identity_hash', $ids)->count();
        }
        $preview = $this->preview($rows, $upload->file_hash, $profile, $version);
        $disk->put($dir.'/report.json', json_encode($preview['details'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $upload->update(['status' => 'ready', 'fingerprint' => $preview['fingerprint'],
            'source_profile' => $profile, 'rules_version' => $version,
            'staged_hash' => hash_file('sha256', $disk->path($dir.'/staged-products.json')),
            'summary' => [...$preview['summary'], 'absent' => $absent, 'skipped' => $analysis['skipped_service_rows']], 'error' => null]);
        $upload->update(['safety'=>CatalogPriceSafety::inspect($upload,$rows),'safety_ack'=>null]);
    }

    public function preview(array $rows, string $hash, string $source = 'altech-price', ?string $version = null): array
    {
        // The real importer runs inside a transaction that is ALWAYS rolled back.
        DB::beginTransaction();
        try {
            $fingerprint = $this->fingerprint();
            $summary = app(CatalogDraftImport::class)->run($rows, $source, $hash, $version ?? config('catalog-import.rules'), true);
            $summary['stock_cleared_missing']=CatalogMissingStock::apply($rows,$source,$summary['batch_id']);
            $summary['storefront_changed']=DB::table('catalog_product_events as e')->join('products as p','p.id','=','e.product_id')->where('e.batch_id',$summary['batch_id'])->where('p.is_active',true)->whereIn('e.kind',['price_updated','stock_cleared'])->distinct()->count('p.id');
            $details = $this->details($summary['batch_id']);
            unset($summary['batch_id']);
            return compact('fingerprint', 'summary', 'details');
        } finally { DB::rollBack(); }
    }

    public function apply(CatalogPriceUpload $upload): void
    {
        DB::transaction(function () use ($upload) {
            $current = CatalogPriceUpload::whereKey($upload->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'applying') return;
            if (! hash_equals($current->fingerprint, $this->fingerprint())) {
                $current->update(['status' => 'stale', 'error' => 'Каталог или правила изменились после проверки. Обновите предпросмотр перед применением.']);
                return;
            }
            $disk = Storage::disk('local');
            $staged = $disk->get($current->directory().'/staged-products.json');
            if (! hash_equals($current->staged_hash, hash('sha256', $staged))) throw new CatalogPriceException('Подготовленные данные изменились. Повторите проверку.');
            $rows = json_decode($staged, true, flags: JSON_THROW_ON_ERROR);
            $safety=CatalogPriceSafety::inspect($current,$rows);
            if ($safety['blocked'] || ($safety['warnings'] && !hash_equals($safety['token'],$current->safety_ack ?? ''))) {
                $current->update(['status'=>'stale','safety'=>$safety,'error'=>'Проверка безопасности изменилась. Обновите предпросмотр.']);
                return;
            }
            $summary = app(CatalogDraftImport::class)->run($rows, $current->source_profile, $current->file_hash, $current->rules_version ?? config('catalog-import.rules'), true);
            $summary['stock_cleared_missing'] = CatalogMissingStock::apply($rows, $current->source_profile, $summary['batch_id']);
            DB::table('catalog_product_events')->where('batch_id',$summary['batch_id'])->whereNull('user_id')->update(['user_id'=>$current->user_id]);
            $disk->put($current->directory().'/report.json', json_encode($this->details($summary['batch_id']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $current->update(['status' => 'completed', 'summary' => [...$current->summary, ...$summary], 'error' => null]);
            DB::afterCommit(function () {
                if (! app()->environment('local', 'testing')) return;
                try { app(\App\Support\CatalogPhotoQueue::class)->plan(); }
                catch (\Throwable $e) {
                    // Prices are committed. The scheduler reconciles this independently on its next pass.
                    report($e);
                }
            });
        });
    }

    private function details(int $batch): array
    {
        return DB::table('catalog_import_rows')->where('batch_id', $batch)->orderBy('source_row')->get()->map(function ($row) {
            $data = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
            return ['row' => $row->source_row, 'sku' => $data['sku'], 'name' => $data['name'],
                'operation' => $row->operation, 'changes' => $data['changes'], 'issues' => $data['import_issues'],
                'price' => $data['price'], 'stock' => $data['stock']];
        })->all();
    }
}
