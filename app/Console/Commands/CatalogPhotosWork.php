<?php
namespace App\Console\Commands;

use App\Support\CatalogPhotoQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Storage};

class CatalogPhotosWork extends Command
{
    protected $signature = 'catalog:photos-work {--limit=3} {--plan-only} {--retry-failed} {--continuous : Keep taking tasks without a batch pause} {--max-time=3600}';
    protected $description = 'Reconcile completed imports and process durable private photo tasks (no publication)';
    public function handle(CatalogPhotoQueue $queue): int
    {
        if (! app()->environment('local','testing') && ! config('catalog-import.photos_enabled')) { $this->error('Catalog photo processing is disabled for this deployment'); return self::FAILURE; }
        $continuous=(bool)$this->option('continuous') && !$this->option('plan-only');
        $maxTime=max(1,min(3600,(int)$this->option('max-time')));
        $daemon=$continuous ? Cache::lock('catalog-photos-daemon',$maxTime+600) : null;
        if ($daemon && !$daemon->get()) return self::SUCCESS;
        $started=microtime(true); $lastPlan=0; $processed=0; $retryFailed=(bool)$this->option('retry-failed');
        $stopRequested=false; $handlers=[]; $asyncSignals=null;
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal') && function_exists('pcntl_signal_get_handler')) {
            $asyncSignals=pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT] as $signal) {
                $handlers[$signal]=pcntl_signal_get_handler($signal);
                pcntl_signal($signal, function () use (&$stopRequested) { $stopRequested=true; });
            }
        }
        try {
            do {
                $lock=Cache::lock('catalog-photos-work',600);
                $task=null;
                if ($lock->get()) {
                    try {
                        if (time()-$lastPlan>=60) {
                            $this->line(json_encode($queue->plan())); $lastPlan=time();
                        }
                        if ($retryFailed) {
                            \Illuminate\Support\Facades\DB::table('catalog_photo_tasks')->where('status','failed')->update(['status'=>'queued','attempts'=>0,'available_at'=>now(),'updated_at'=>now()]);
                            $retryFailed=false;
                        }
                        if (!$this->option('plan-only') && ($continuous || $processed<max(0,(int)$this->option('limit')))) {
                            $task=$queue->claim();
                            if ($task) {
                                $queue->run($task); $processed++;
                                $this->line('Processed task '.$task->id);
                            }
                        }
                        $report=$queue->report();
                        Storage::disk('local')->put('catalog-media-queue/latest.json',json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
                    } finally { $lock->release(); }
                }
                if (!$continuous && (!$task || $processed>=max(0,(int)$this->option('limit')))) break;
                if ($continuous && !$task && !$stopRequested) sleep(2);
            } while (!$stopRequested && (!$continuous || microtime(true)-$started<$maxTime));
            return self::SUCCESS;
        } catch (\Throwable $e) { report($e); $this->error($e->getMessage()); return self::FAILURE; }
        finally {
            $daemon?->release();
            foreach ($handlers as $signal => $handler) pcntl_signal($signal, $handler);
            if ($asyncSignals !== null) pcntl_async_signals($asyncSignals);
        }
    }
}
