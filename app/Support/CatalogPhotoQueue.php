<?php

namespace App\Support;

use App\Admin\CatalogPresence;
use App\Models\CatalogPriceUpload;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/** Durable private media work queue, separate from price transactions and storefront media. */
class CatalogPhotoQueue
{
    public function currentUpload(): ?CatalogPriceUpload
    {
        return CatalogPriceUpload::where('status', 'completed')->latest('updated_at')->orderByDesc('id')->first();
    }

    public function plan(): array
    {
        $upload = $this->currentUpload();
        if (! $upload) return ['planned' => 0, 'preserved' => 0];
        $current = CatalogPresence::current($upload->source_profile);
        if (! $current['upload'] || $current['upload']->id !== $upload->id) throw new \RuntimeException('Current price evidence is unavailable');
        $links = DB::table('catalog_source_products')->where('source', $upload->source_profile)->whereIn('identity_hash', $current['hashes'])->get();
        $products = DB::table('products')->whereIn('id', $links->pluck('product_id'))->get();
        $groups = $links->groupBy('product_id');
        $disk = Storage::disk('local');
        $count = ['planned' => 0, 'preserved' => 0];
        foreach ($products as $product) {
            $codes = $groups[$product->id]->map(fn ($link) => (string) (json_decode($link->supplier_data ?? '{}', true)['EAN'] ?? ''))->filter()->unique()->values()->all();
            sort($codes);
            $payload = ['id' => $product->id, 'brand' => $product->brand ?? '', 'sku' => $product->sku ?? '', 'name' => $product->name,
                'ean' => count($codes) === 1 ? $codes[0] : '', 'ean_conflict' => count($codes) > 1];
            // Prices, stock and category do not invalidate an image identity.
            $hash = hash('sha256', json_encode([$payload, $codes], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $manual=json_decode($product->photos ?? 'null',true);
            $preserved=is_array($manual) && count($manual)>0;
            $approved=DB::table('catalog_approved_media')->where('product_id',$product->id)->first();
            if ($product->photos===null && $approved && json_decode($approved->identity,true)===$payload && collect(json_decode($approved->images,true))->contains(fn($im)=>ApprovedProductMedia::valid($im))) $preserved=true;
            $status = $preserved ? 'preserved' : ($product->photos!==null ? 'rejected' : ($payload['ean_conflict'] || ! $payload['brand'] || ! $payload['sku'] ? 'review' : 'queued'));
            $inserted = DB::table('catalog_photo_tasks')->insertOrIgnore([
                'product_id' => $product->id, 'identity_hash' => $hash, 'upload_id' => $upload->id,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'status' => $status,
                'available_at' => $status === 'queued' ? now() : null, 'attempts' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('catalog_photo_tasks')->where('product_id', $product->id)->where('identity_hash', '!=', $hash)->where('status', '!=', 'superseded')->update([
                'status' => 'superseded', 'lease_until' => null, 'lease_token' => null, 'updated_at' => now(),
            ]);
            if ($preserved) {
                DB::table('catalog_photo_tasks')->where('identity_hash', $hash)->update(['status'=>'preserved','lease_token'=>null,'lease_until'=>null,'available_at'=>null,'updated_at'=>now()]);
            } else {
                // A previously superseded identity may return in a later price.
                DB::table('catalog_photo_tasks')->where('identity_hash', $hash)->whereIn('status', ['superseded','preserved'])->update(['status'=>$status,'available_at'=>now(),'attempts'=>0,'updated_at'=>now()]);
            }
            if ($inserted) $count[$preserved ? 'preserved' : 'planned']++;
        }
        DB::table('catalog_photo_tasks')->whereNotIn('product_id', $products->pluck('id'))->where('status', '!=', 'superseded')->update(['status'=>'superseded','lease_token'=>null,'lease_until'=>null,'updated_at'=>now()]);
        foreach (DB::table('catalog_photo_tasks')->where('status', 'ready')->pluck('id') as $id) app(CatalogPhotoAssignment::class)->assign($id);
        return $count;
    }

    public function claim(): ?object
    {
        return DB::transaction(function () {
            DB::table('catalog_photo_tasks')->where('status','processing')->where('lease_until','<=',now())->where('attempts','>=',3)
                ->update(['status'=>'failed','error'=>'Worker repeatedly stopped before completion','lease_token'=>null,'lease_until'=>null,'updated_at'=>now()]);
            $task = DB::table('catalog_photo_tasks')->where(function ($q) {
                $q->where(function ($q) { $q->whereIn('status', ['queued','retry','not_found','restricted'])->where('available_at', '<=', now()); })
                    ->orWhere(fn ($q) => $q->where('status','processing')->where('lease_until','<=',now()));
            })->orderBy('available_at')->orderBy('id')->lockForUpdate()->first();
            if (! $task) return null;
            $token = (string) Str::uuid();
            DB::table('catalog_photo_tasks')->where('id',$task->id)->update(['status'=>'processing','lease_token'=>$token,'lease_until'=>now()->addMinutes(3),'attempts'=>$task->attempts+1,'updated_at'=>now()]);
            $task->lease_token = $token; $task->attempts++;
            return $task;
        });
    }

    public function finish(object $task, array $result): void
    {
        $status = $result['status'] ?? 'retry';
        if (! in_array($status, ['ready','review','not_found','restricted','retry'], true)) $status = 'retry';
        $next = match ($status) {
            'not_found' => now()->addDays(7), 'restricted' => now()->addDays(30),
            'retry' => now()->addMinutes([5,30,120][min(2,$task->attempts-1)]), default => null,
        };
        if ($status === 'retry' && $task->attempts >= 3) { $status='failed'; $next=null; }
        $saved = DB::table('catalog_photo_tasks')->where('id',$task->id)->where('status','processing')->where('lease_token',$task->lease_token)->update([
            'status'=>$status,'available_at'=>$next,'lease_until'=>null,'lease_token'=>null,
            'result'=>json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'error'=>$result['error'] ?? null,
            'attempts'=>in_array($status,['not_found','restricted'],true) ? 0 : $task->attempts,'updated_at'=>now(),
        ]);
        if ($saved && $status === 'ready') app(CatalogPhotoAssignment::class)->assign($task->id);
    }

    public function run(object $task): void
    {
        try {
            $disk = Storage::disk('local');
            $directory = 'catalog-media-queue/'.$task->identity_hash.'/'.$task->lease_token;
            $disk->put($directory.'/input.json', $task->payload);
            $process = new Process([config('catalog-import.python'), base_path('scripts/catalog_photo_task.py'), $disk->path($directory)], base_path(), ['PYTHONUTF8'=>'1']);
            $process->setTimeout(120);
            $process->mustRun();
            $result = json_decode($disk->get($directory.'/result.json'), true, flags: JSON_THROW_ON_ERROR);
            $result['directory'] = $directory;
            $this->finish($task, $result);
        } catch (\Throwable $e) {
            $this->finish($task, ['status'=>'retry','error'=>mb_substr($e->getMessage(),0,2000)]);
        }
    }

    public function report(): array
    {
        return ['mode'=>'test-only','published'=>0,'generated_at'=>now()->toIso8601String(),
            'counts'=>DB::table('catalog_photo_tasks')->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total','status')->all(),
            'issues'=>DB::table('catalog_photo_tasks')->whereIn('status',['failed','retry','review'])->select('product_id','status','error','attempts')->limit(100)->get()->all()];
    }
}
