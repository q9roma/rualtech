<?php
namespace App\Support;

use Illuminate\Support\Facades\{DB, Storage};

/** Conservative mark-and-sweep: a file must be unreferenced for 30 observed days. */
class CatalogMediaCleanup
{
    public const DIRECTORY = 'catalog-media-queue/media';
    private const LEDGER = 'catalog-media-queue/cleanup-ledger.json';

    private function files(mixed $value): array
    {
        $found = [];
        if (is_array($value)) foreach ($value as $item) $found = [...$found, ...$this->files($item)];
        elseif (is_string($value) && preg_match_all('/[a-f0-9]{64}\.(?:jpg|png|webp)/', $value, $matches)) $found = $matches[0];
        return array_unique($found);
    }

    public function references(): array
    {
        $used = [];
        $mark = function ($value) use (&$used) { foreach ($this->files($value) as $file) $used[$file] = true; };
        foreach (DB::table('catalog_approved_media')->get(['images']) as $row) $mark($row->images);
        foreach (DB::table('products')->get(['photos']) as $row) $mark($row->photos);
        $historyTasks = [];
        foreach (DB::table('catalog_product_events')->get(['changes','context']) as $event) {
            $mark($event->changes); $mark($event->context);
            $context = json_decode($event->context ?? '{}', true, flags: JSON_THROW_ON_ERROR);
            if (isset($context['task_id'])) $historyTasks[$context['task_id']] = true;
        }
        foreach (DB::table('catalog_photo_tasks')->get(['id','status','result','updated_at']) as $task) {
            $result = json_decode($task->result ?? '{}', true, flags: JSON_THROW_ON_ERROR);
            if (isset($historyTasks[$task->id])) $mark($result);
            elseif (!in_array($task->status, ['rejected','superseded'])) $mark($result['images'] ?? []);
            // Keep original/rejected images during the review retention period too.
            if (\Carbon\Carbon::parse($task->updated_at)->gt(now()->subDays(30))) $mark($result);
        }
        $disk = Storage::disk('local');
        // These manifests are still used by the storefront and review screens.
        foreach (['catalog-automation/photo-quality/review.json','catalog-automation/photo-quality/approved-media.json'] as $path) {
            if (!$disk->exists($path)) continue;
            $json = $disk->get($path);
            json_decode($json, true, flags: JSON_THROW_ON_ERROR); // Fail closed on corrupt manifests.
            $mark($json);
        }
        return $used;
    }

    public function run(bool $apply = false): array
    {
        $disk = Storage::disk('local');
        $used = $this->references();
        $ledger = $disk->exists(self::LEDGER) ? json_decode($disk->get(self::LEDGER), true, flags: JSON_THROW_ON_ERROR) : [];
        $next = []; $rows = []; $deleted = 0; $bytes = 0;
        $busy = DB::table('catalog_photo_tasks')->where('status','processing')->exists();
        foreach ($disk->files(self::DIRECTORY) as $path) {
            $file = basename($path);
            if (!preg_match('/\A[a-f0-9]{64}\.(jpg|png|webp)\z/', $file) || isset($used[$file])) continue;
            $since = $ledger[$file] ?? now()->toIso8601String();
            $next[$file] = $since;
            $size = $disk->size($path);
            $eligible = \Carbon\Carbon::parse($since)->lte(now()->subDays(30)) && $disk->lastModified($path) <= now()->subDays(30)->timestamp;
            $rows[] = ['file'=>$file,'bytes'=>$size,'unreferenced_since'=>$since,'eligible'=>$eligible];
            $bytes += $size;
            if ($apply && $eligible && !$busy) {
                $removed = DB::transaction(function () use ($disk, $file, $path) {
                    // Serialize against assignment/review, which lock these same rows.
                    DB::table('products')->orderBy('id')->lockForUpdate()->get(['id']);
                    DB::table('catalog_photo_tasks')->orderBy('id')->lockForUpdate()->get(['id']);
                    if (isset($this->references()[$file]) || DB::table('catalog_photo_tasks')->where('status','processing')->exists()) return false;
                    // Retire the legacy copy first; otherwise fallback readers could revive it.
                    if ($disk->exists(CatalogMediaStorage::LEGACY.'/'.$file)) return false;
                    if ($disk->lastModified($path) > now()->subDays(30)->timestamp) return false;
                    return $disk->delete($path);
                });
                if ($removed) { unset($next[$file]); $deleted++; }
            }
        }
        $duplicateBytes = 0; $duplicateCount = 0;
        foreach ($disk->files(CatalogMediaStorage::LEGACY) as $old) {
            $file = basename($old); $canonical = self::DIRECTORY.'/'.$file;
            if (!preg_match('/\A([a-f0-9]{64})\.(jpg|png|webp)\z/', $file, $match) || !$disk->exists($canonical)) continue;
            if (!hash_equals($match[1], hash_file('sha256', $disk->path($old))) || !hash_equals($match[1], hash_file('sha256', $disk->path($canonical)))) continue;
            // Old paths retain a rollback copy for 30 days after migration.
            $key = 'legacy:'.$file; $since = $ledger[$key] ?? now()->toIso8601String();
            $next[$key] = $since; $duplicateCount++; $duplicateBytes += $disk->size($old);
            if ($apply && !$busy && \Carbon\Carbon::parse($since)->lte(now()->subDays(30))) {
                if ($disk->delete($old)) { unset($next[$key]); $deleted++; }
            }
        }
        $disk->put(self::LEDGER, json_encode($next, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $report = ['generated_at'=>now()->toIso8601String(),'retention_days'=>30,'processing'=>$busy,'unreferenced_files'=>count($rows),'unreferenced_bytes'=>$bytes,'legacy_duplicates'=>$duplicateCount,'legacy_duplicate_bytes'=>$duplicateBytes,'eligible_files'=>count(array_filter($rows,fn($r)=>$r['eligible'])),'deleted'=>$deleted,'files'=>$rows];
        $disk->put('catalog-media-queue/cleanup-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $report;
    }
}
