<?php

namespace App\Console\Commands;

use App\Admin\CatalogPriceWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, DB, Storage};
use Symfony\Component\Process\Process;

/** Deliberately has no apply/publish option. */
class CatalogAutomationCheck extends Command
{
    protected $signature = 'catalog:automation-check';
    protected $description = 'Check configured supplier files and media privately, without applying or publishing';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        if (! $disk->exists('catalog-automation/sources.json')) return self::SUCCESS;
        $lock = Cache::lock('catalog-automation-check', 1800);
        if (! $lock->get()) { $this->info('Already running'); return self::SUCCESS; }
        try {
            $settings = json_decode($disk->get('catalog-automation/sources.json'), true, flags: JSON_THROW_ON_ERROR);
            if (! ($settings['enabled'] ?? false)) return self::SUCCESS;
            $dir = $disk->path('catalog-automation');
            $products = DB::table('products')->orderBy('id')->get();
            $disk->put('catalog-automation/products.json', $products->toJson(JSON_UNESCAPED_UNICODE));
            $context = [];
            foreach (['product_categories', 'catalog_import_mappings', 'catalog_source_products'] as $table) {
                $context[$table] = DB::table($table)->orderBy('id')->get();
            }
            $disk->put('catalog-automation/context.json', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $links = DB::table('catalog_source_products')->where('source', 'supplier-price-32')->orderBy('id')->get();
            $ean = [];
            foreach ($links as $link) {
                $data = json_decode($link->supplier_data ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                if (! empty($data['EAN'])) $ean[$link->product_id][] = (string) $data['EAN'];
            }
            $media = $products->map(function ($p) use ($ean) {
                $codes = array_values(array_unique($ean[$p->id] ?? []));
                return ['id' => $p->id, 'brand' => $p->brand, 'sku' => $p->sku,
                    'ean' => count($codes) === 1 ? $codes[0] : '', 'active' => (bool) $p->is_active];
            });
            $disk->put('catalog-automation/media-products.json', $media->toJson(JSON_UNESCAPED_UNICODE));
            $process = new Process([config('catalog-import.python'), base_path('scripts/catalog_automation.py'), $dir], base_path(), ['PYTHONUTF8' => '1']);
            $process->setTimeout(1200);
            $process->mustRun();
            $this->line(trim($process->getOutput()));
            $report = json_decode($disk->get('catalog-automation/latest.json'), true, flags: JSON_THROW_ON_ERROR);
            foreach ($report['prices'] as $price) {
                if ($price['status'] !== 'prepared') continue;
                $path = $price['directory'];
                if (is_file($path.'/import-preview.json')) continue;
                $rows = json_decode(file_get_contents($path.'/staged-products.json'), true, flags: JSON_THROW_ON_ERROR);
                $analysis = json_decode(file_get_contents($path.'/summary.json'), true, flags: JSON_THROW_ON_ERROR);
                $preview = app(CatalogPriceWorkflow::class)->preview($rows, $analysis['source_sha256'], $analysis['source_profile'], $analysis['rules_version']);
                file_put_contents($path.'/import-preview.json', json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $this->info('Rolled-back import preview: '.json_encode($preview['summary']));
            }
            return collect([...$report['prices'], ...$report['feeds']])->contains(fn ($r) => $r['status'] === 'failed') ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $disk->put('catalog-automation/last-error.json', json_encode(['at' => now()->toIso8601String(), 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally { $lock->release(); }
    }
}
