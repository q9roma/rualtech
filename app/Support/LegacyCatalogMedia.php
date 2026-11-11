<?php
namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\{DB,Storage};

class LegacyCatalogMedia
{
    public function migrate(bool $apply=false): array
    {
        $disk=Storage::disk('local');
        $path='catalog-automation/photo-quality/review.json';
        $rows=$disk->exists($path) ? json_decode($disk->get($path),true,flags:JSON_THROW_ON_ERROR) : [];
        $products=Product::whereIn('id',array_column($rows,'id'))->get()->keyBy('id');
        $approved=DB::table('catalog_approved_media')->pluck('product_id')->flip();
        $tasks=DB::table('catalog_photo_tasks')->where('status','!=','superseded')->orderByDesc('id')->get()->unique('product_id')->keyBy('product_id');
        $report=['assigned'=>0,'ready'=>0,'review'=>0,'skipped'=>0,'products'=>[]];
        foreach ($rows as $old) {
            $product=$products->get($old['id']);
            if (!$product || !$old['gallery'] || $product->photos!==null || $approved->has($product->id)
                || collect(['name','brand','sku'])->contains(fn($k)=>($old[$k] ?? '')!==(string)$product->$k)) { $report['skipped']++; continue; }
            $task=$tasks->get($product->id);
            if ($task && $task->status!=='preserved' && !($task->status==='queued' && !$task->result && !$task->attempts)) { $report['skipped']++; continue; }
            $payload=$task ? json_decode($task->payload,true) : ['id'=>$product->id,'name'=>$product->name,'brand'=>$product->brand,'sku'=>$product->sku,'ean'=>$old['ean'] ?? '', 'ean_conflict'=>false];
            if (!empty($payload['ean_conflict']) || ($old['ean'] ?? '')!==($payload['ean'] ?? '')) { $report['skipped']++; continue; }
            $images=[];
            foreach ($old['gallery'] as $image) {
                if (!ApprovedProductMedia::valid($image)) continue;
                $confirmed=($image['source'] ?? '')==='VVP' && ($old['vvp_status'] ?? '')==='matched';
                if (($image['source'] ?? '')==='Icecat') {
                    $cache='catalog-automation/icecat-full/'.$product->id.'.json';
                    $evidence=$disk->exists($cache) ? json_decode($disk->get($cache),true,flags:JSON_THROW_ON_ERROR) : [];
                    $brand=$evidence['returned_brand'] ?? ''; if (is_array($brand)) $brand=$brand['Name'] ?? '';
                    $confirmed=mb_strtolower(trim($brand))===mb_strtolower(trim($payload['brand'])) && !empty($payload['ean']) && in_array($payload['ean'],$evidence['returned_gtins'] ?? [],true);
                }
                $images[]=[...$image,'identity'=>$confirmed ? 'confirmed' : 'review'];
            }
            if (!$images) { $report['skipped']++; continue; }
            // Only photos already visible on published products become migration assignments.
            $state=$product->is_active ? 'assigned' : (collect($images)->every(fn($im)=>$im['identity']==='confirmed') ? 'ready' : 'review');
            $report[$state]++; $report['products'][]=['id'=>$product->id,'state'=>$state,'images'=>count($images)];
            if (!$apply) continue;
            DB::transaction(function() use ($product,$payload,$task,$images,$state) {
                Product::whereKey($product->id)->lockForUpdate()->first();
                $result=['images'=>$images,'sources'=>[],'errors'=>[],'legacy_import'=>true,'review_reason'=>$state==='review' ? 'Brand/MPN matched but EAN not confirmed' : null];
                if ($task) DB::table('catalog_photo_tasks')->where('id',$task->id)->update(['status'=>$state==='assigned' ? 'preserved' : $state,'result'=>json_encode($result),'available_at'=>null,'lease_token'=>null,'lease_until'=>null,'updated_at'=>now()]);
                else {
                    $id=DB::table('catalog_photo_tasks')->insertGetId(['product_id'=>$product->id,'identity_hash'=>hash('sha256','legacy:'.$product->id),'upload_id'=>'legacy','payload'=>json_encode($payload),'status'=>$state==='assigned' ? 'preserved' : $state,'result'=>json_encode($result),'created_at'=>now(),'updated_at'=>now()]);
                    $task=(object)['id'=>$id];
                }
                if ($state==='assigned') {
                    DB::table('catalog_approved_media')->insert(['product_id'=>$product->id,'task_id'=>$task->id,'user_id'=>null,'origin'=>'legacy_display','identity'=>json_encode($payload),'images'=>json_encode($images),'created_at'=>now(),'updated_at'=>now()]);
                    \App\Admin\CatalogHistory::record($product->id,'photos_assigned',[],['task_id'=>$task->id,'origin'=>'legacy_display','note'=>'Перенос фотографий, ранее показанных на сайте'],'legacy-media');
                }
            });
        }
        return $report;
    }
}
