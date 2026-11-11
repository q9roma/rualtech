<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CatalogMediaCleanup extends Command
{
    protected $signature = 'catalog:media-cleanup {--apply : Remove files unreferenced for at least 30 days} {--consolidate : Move legacy images into shared storage}';
    protected $description = 'Report unused catalog images; deletion requires --apply and a 30-day observation period';

    public function handle(\App\Support\CatalogMediaCleanup $cleanup): int
    {
        $lock = Cache::lock('catalog-photos-work', 600);
        if (!$lock->get()) { $this->warn('Photo worker is active; cleanup skipped.'); return self::SUCCESS; }
        try {
            if ($this->option('consolidate')) $this->line(json_encode(\App\Support\CatalogMediaStorage::consolidate()));
            $report = $cleanup->run((bool)$this->option('apply'));
            unset($report['files']);
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        } finally { $lock->release(); }
    }
}
