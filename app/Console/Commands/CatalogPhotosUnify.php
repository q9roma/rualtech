<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache,Storage};
class CatalogPhotosUnify extends Command
{
    protected $signature='catalog:photos-unify {--apply}';
    protected $description='Migrate legacy photo selections to explicit assignments and candidates';
    public function handle(\App\Support\LegacyCatalogMedia $migration): int {
        $lock=Cache::lock('catalog-photos-work',600);
        if (!$lock->get()) { $this->error('Photo worker is busy. Retry after current task.'); return self::FAILURE; }
        try {
            $report=$migration->migrate((bool)$this->option('apply'));
            Storage::disk('local')->put('catalog-media-queue/unification-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            unset($report['products']); $this->line(json_encode($report));
            return self::SUCCESS;
        } finally { $lock->release(); }
    }
}
