<?php

namespace App\Console\Commands;

use App\Admin\CatalogDraftImport;
use Illuminate\Console\Command;

class CatalogImportDrafts extends Command
{
    protected $signature = 'catalog:import-drafts {file : Reviewed staged-products.json} {--source=} {--rules=} {--update-existing : Update only prices and stock of matched products}';
    protected $description = 'Import drafts; optionally synchronize existing prices and stock without changing editorial fields';

    public function handle(CatalogDraftImport $import): int
    {
        $file = $this->argument('file');
        if (! is_file($file) || filesize($file) > 100 * 1024 * 1024) {
            $this->error('Expected a local staging JSON file up to 100 MB.');
            return self::FAILURE;
        }
        $rows = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $source = $this->option('source') ?: ($rows[0]['source_profile'] ?? 'altech-price');
        $rules = $this->option('rules') ?: ($source === 'supplier-price-32' ? 'b2b-supplier32-2026-11-09-v2' : config('catalog-import.rules'));
        $result = $import->run($rows, $source, hash_file('sha256', $file), $rules, (bool) $this->option('update-existing'));
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
