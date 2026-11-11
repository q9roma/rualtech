<?php
namespace App\Admin;
use App\Models\{CatalogPriceUpload,Product};
use App\Support\ApprovedProductMedia;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
class CatalogRelease {
    public function scope(CatalogPriceUpload $upload): array {
        if ($upload->status!=='completed') return [];
        $latest=CatalogPriceUpload::where('source_profile',$upload->source_profile)->where('status','completed')->latest('updated_at')->orderByDesc('id')->first();
        if (!$latest || $latest->id!==$upload->id) return [];
        $json=Storage::disk('local')->get($upload->directory().'/staged-products.json');
        if (!$json || !hash_equals($upload->staged_hash ?? '',hash('sha256',$json))) return [];
        $rows=json_decode($json,true);
        $hashes=array_map(fn($r)=>$upload->source_profile==='supplier-price-32' ? hash('sha256','supplier-id:'.trim($r['supplier_id'] ?? '')) : CatalogDraftImport::identity($r['brand'] ?? '',$r['sku'] ?? '',$r['name']),$rows);
        return DB::table('catalog_source_products')->where('source',$upload->source_profile)->whereIn('identity_hash',$hashes)->pluck('product_id')->unique()->all();
    }
    public function preview(CatalogPriceUpload $upload): array {
        $ids=$this->scope($upload);
        $products=Product::whereIn('id',$ids)->orderBy('id')->with('catalogCategory.parent')->get()->keyBy('id');
        $operations=DB::table('catalog_import_rows')->where('batch_id',$upload->summary['batch_id'] ?? 0)->whereNotNull('product_id')->pluck('operation','product_id');
        $groupFor=fn($id)=>match($operations->get($id)) {'created_drafts'=>'new','updated'=>'updated','existing_untouched'=>'unchanged',default=>'skipped'};
        $groups=[];
        foreach(['new'=>'Новые','updated'=>'Обновлённые','unchanged'=>'Без изменений','skipped'=>'Пропущенные'] as $key=>$label) $groups[$key]=['key'=>$key,'label'=>$label,'total'=>0,'published'=>0];
        $photos=[];
        foreach(DB::table('catalog_photo_tasks')->whereIn('product_id',$ids)->where('status','ready')->orderBy('id')->get() as $task) {
            $p=$products->get($task->product_id); $payload=json_decode($task->payload,true); $result=json_decode($task->result ?? '{}',true);
            if (!$p || $p->photos!==null || $groupFor($p->id)==='skipped' || !empty($payload['ean_conflict']) || ApprovedProductMedia::files($p)) continue;
            if (collect(['name','brand','sku'])->contains(fn($key)=>($payload[$key] ?? '')!==(string)$p->$key)) continue;
            $codes=DB::table('catalog_source_products')->where('product_id',$p->id)->where('source',$upload->source_profile)->pluck('supplier_data')->map(fn($data)=>(string)(json_decode($data ?? '{}',true)['EAN'] ?? ''))->filter()->unique()->values();
            if ($codes->count()>1 || ($codes->first() ?? '')!==($payload['ean'] ?? '')) continue;
            $images=$result['images'] ?? [];
            if (!$images || collect($images)->contains(fn($im)=>!ApprovedProductMedia::valid($im))) continue;
            $photos[]=['id'=>$p->id,'name'=>$p->name,'group'=>$groupFor($p->id),'task'=>$task->id,'version'=>hash('sha256',$task->payload.$task->result)];
        }
        $publish=[]; $blocked=[];
        foreach($products as $p) {
            $group=$groupFor($p->id);
            $groups[$group]['total']++;
            if ($p->is_active) { $groups[$group]['published']++; continue; }
            $category=$p->catalogCategory;
            $reason= !$category?->parent_id || !$category->is_active || !$category->parent?->is_active ? 'Нет действующей подкатегории' : (!filled($p->name) || !filled($p->brand) || !filled($p->sku) || !$p->price || $p->price<=0 ? 'Не заполнены данные товара или цена' : null);
            if (DB::table('catalog_photo_tasks')->where('product_id',$p->id)->where('status','review')->exists()) $reason='Фото требует проверки';
            if ($group==='skipped') $reason='Товар не прошёл текущий импорт';
            $row=['id'=>$p->id,'name'=>$p->name,'group'=>$group,'version'=>hash('sha256',$p->toJson())];
            if($reason) $blocked[]=[...$row,'reason'=>$reason,'reviewUrl'=>$reason==='Фото требует проверки' ? '/admin/catalog-prices/'.$upload->id.'?section=photos&status=review&search='.rawurlencode($p->sku) : '/admin/products/'.$p->id.'/edit']; else $publish[]=$row;
        }
        return ['photos'=>$photos,'publish'=>$publish,'blocked'=>$blocked,'groups'=>array_values($groups),'published'=>array_sum(array_column($groups,'published')),'total'=>count($ids),'token'=>hash('sha256',json_encode([$photos,$publish,$blocked,$groups]))];
    }
    public function execute(CatalogPriceUpload $upload,string $action,string $token,int $user,array $groups=['new','updated','unchanged']): int {
        return DB::transaction(function() use($upload,$action,$token,$user,$groups) {
            CatalogPriceUpload::whereKey($upload->id)->lockForUpdate()->firstOrFail();
            DB::table('product_categories')->orderBy('id')->lockForUpdate()->first();
            $ids=$this->scope($upload);
            Product::whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
            DB::table('catalog_photo_tasks')->whereIn('product_id',$ids)->orderBy('id')->lockForUpdate()->get();
            $preview=$this->preview($upload);
            if (!hash_equals($preview['token'],$token)) throw ValidationException::withMessages(['release'=>'Список изменился. Обновите страницу и проверьте его ещё раз.']);
            $affected=[];
            foreach ($action==='release' ? ['photos','publish'] : [$action] as $step) {
            $items=array_values(array_filter($preview[$step],fn($item)=>in_array($item['group'],$groups,true)));
            foreach($items as $item) {
                $affected[$item['id']]=true;
                if($step==='publish') Product::findOrFail($item['id'])->update(['is_active'=>true]);
                else {
                    $task=DB::table('catalog_photo_tasks')->find($item['task']);
                    DB::table('catalog_approved_media')->updateOrInsert(['product_id'=>$item['id']],['origin'=>'manual','task_id'=>$task->id,'user_id'=>$user,'identity'=>$task->payload,'images'=>json_encode(json_decode($task->result,true)['images']),'created_at'=>now(),'updated_at'=>now()]);
                    CatalogHistory::record($item['id'],'photos_assigned',[],['task_id'=>$task->id,'selected_match'=>json_decode($task->result,true)['selected_match'] ?? null],$upload->source_profile,$upload->summary['batch_id'] ?? null,$user);
                    DB::table('catalog_photo_tasks')->where('id',$task->id)->update(['status'=>'preserved','updated_at'=>now()]);
                }
            }
            }
            return count($affected);
        });
    }
}
