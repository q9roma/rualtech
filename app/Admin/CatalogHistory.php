<?php
namespace App\Admin;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CatalogHistory
{
    public static function record(int $product, string $kind, array $changes = [], array $context = [], ?string $source = null, ?int $batch = null, ?int $user = null): void
    {
        DB::table('catalog_product_events')->insert([
            'product_id'=>$product, 'kind'=>$kind, 'changes'=>json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'context'=>json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'source'=>$source,
            'batch_id'=>$batch, 'user_id'=>$user ?? auth()->id(), 'created_at'=>now(),
        ]);
    }

    public static function modelSaved(Product $product, bool $created = false): void
    {
        $changes=[];
        foreach ($product->getChanges() as $key=>$value) {
            if (in_array($key,['updated_at','created_at','id'])) continue;
            $changes[$key]=['before'=>$product->getRawOriginal($key),'after'=>$value];
        }
        if ($created) {
            $changes=[];
            foreach ($product->getAttributes() as $key=>$value) if (!in_array($key,['id','created_at','updated_at'])) $changes[$key]=['before'=>null,'after'=>$value];
            self::record($product->id,'created',$changes,['name'=>$product->name]);
        } elseif ($changes) self::record($product->id,'edited',$changes);
    }

    public static function sources(int $product): array
    {
        return DB::table('catalog_source_products')->where('product_id',$product)->get()->map(function($link) {
            // Resolve evidence for this product, never the latest file of the whole source.
            $rowBatches=DB::table('catalog_import_rows as r')->join('catalog_import_batches as b','b.id','=','r.batch_id')
                ->where('r.product_id',$link->product_id)->where('b.source',$link->source)->where('b.status','completed')
                ->whereIn('r.operation',['updated','created_drafts'])->pluck('r.batch_id');
            $eventBatches=DB::table('catalog_product_events')->where('product_id',$link->product_id)->where('source',$link->source)
                ->whereIn('kind',['created','price_updated','stock_cleared'])->whereNotNull('batch_id')->pluck('batch_id');
            $batch=DB::table('catalog_import_batches')->whereIn('id',$rowBatches->merge($eventBatches)->unique())
                ->where('status','completed')->orderByDesc('id')->value('id');
            $last=$batch ? \App\Models\CatalogPriceUpload::where('source_profile',$link->source)->where('status','completed')
                ->where('summary->batch_id',$batch)->latest('updated_at')->first() : null;
            $stale=$link->last_validated_at && \Carbon\Carbon::parse($link->last_validated_at)->lt(now()->subHours(48));
            $date=fn($value)=>$value ? \Carbon\Carbon::parse($value,config('app.timezone'))->toIso8601String() : null;
            return ['id'=>$link->id,'source'=>$link->source,'supplierId'=>$link->supplier_id,'lastSeen'=>$date($link->last_seen_at),
                'validated'=>$date($link->last_validated_at),'missingSince'=>$date($link->missing_since),
                'stale'=>(bool)$stale,'freshness'=>!$link->last_validated_at ? 'unknown' : ($stale ? 'stale' : 'recent'),
                'upload'=>$last ? ['id'=>$last->id,'name'=>$last->name,'at'=>$last->updated_at,'canOpen'=>$last->user_id === auth()->id()] : null];
        })->all();
    }

    public static function provenance(int $product): array
    {
        $result=[];
        foreach (DB::table('catalog_product_events as e')->leftJoin('users as u','u.id','=','e.user_id')->where('product_id',$product)->select('e.*','u.name as actor')->orderByDesc('e.id')->cursor() as $event) {
            foreach (json_decode($event->changes,true) as $field=>$change) {
                if (!isset($result[$field])) $result[$field]=['field'=>$field,'source'=>$event->source,'actor'=>$event->actor,'at'=>$event->created_at,'kind'=>$event->kind];
            }
        }
        return array_values($result);
    }
}
