<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\{CatalogPhotoSimilarity, ApprovedProductMedia};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Inertia\Inertia;

class CatalogPhotoQueueController extends Controller
{
    public function __construct(private CatalogPhotoSimilarity $similarity) {}
    public const STATES = ['queued','processing','preserved','ready','review','not_found','restricted','retry','failed','rejected','superseded'];
    public function index(Request $request)
    {
        return Inertia::render('Admin/PhotoQueue', $this->data($request));
    }

    public function assignedImage(Product $product, int $index)
    {
        Gate::authorize('view',$product);
        $images=ApprovedProductMedia::files($product);
        abort_unless(isset($images[$index]),404);
        return Storage::disk('local')->response('catalog-media-queue/media/'.$images[$index]['file'],null,['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    public function progress(Request $request)
    {
        Gate::authorize('create', Product::class);
        $request->validate(['upload'=>'nullable|uuid']);
        $ids = null;
        if ($request->filled('upload')) {
            $upload = \App\Models\CatalogPriceUpload::where('user_id',$request->user()->id)->findOrFail($request->input('upload'));
            $json = Storage::disk('local')->get($upload->directory().'/staged-products.json');
            abort_unless($json && hash_equals($upload->staged_hash ?? '',hash('sha256',$json)),409);
            $hashes = array_map(fn($r)=>$upload->source_profile==='supplier-price-32'
                ? hash('sha256','supplier-id:'.trim($r['supplier_id'] ?? ''))
                : \App\Admin\CatalogDraftImport::identity($r['brand'] ?? '',$r['sku'] ?? '',$r['name']),json_decode($json,true));
            $ids=DB::table('catalog_source_products')->where('source',$upload->source_profile)->whereIn('identity_hash',$hashes)->pluck('product_id')->unique()->all();
        }
        return response()->json($this->data($request,$ids,true))->header('Cache-Control','no-store');
    }

    public function data(Request $request, ?array $productIds = null, bool $progressOnly = false): array
    {
        Gate::authorize('create', Product::class);
        $request->validate(['status'=>'nullable|in:'.implode(',',self::STATES),'search'=>'nullable|string|max:200','page'=>'nullable|integer|min:1']);
        $request->validate(['category'=>'nullable|integer|min:0','subcategory'=>'nullable|integer|min:1']);
        $categories=\App\Models\ProductCategory::orderBy('name')->get(['id','name','parent_id']);
        $category=$request->string('category')->value();
        $subcategory=$request->string('subcategory')->value();
        if ($subcategory) {
            $child=$categories->firstWhere('id',(int)$subcategory);
            abort_unless($child && $child->parent_id && (string)$child->parent_id===$category,422);
        }
        $query=DB::table('catalog_photo_tasks as t')->leftJoin('products as p','p.id','=','t.product_id');
        if ($productIds !== null) $query->whereIn('t.product_id',$productIds);
        if ($subcategory) $query->where('p.catalog_category_id',(int)$subcategory);
        elseif ($category==='0') $query->whereNull('p.catalog_category_id');
        elseif ($category!=='') $query->whereIn('p.catalog_category_id',[(int)$category,...$categories->where('parent_id',(int)$category)->pluck('id')->all()]);
        $counts=(clone $query)->select('t.status')->selectRaw('COUNT(*) as total')->groupBy('t.status')->pluck('total','status');
        if ($progressOnly) {
            $total=$counts->except('superseded')->sum();
            $pending=collect(['queued','processing','retry'])->sum(fn($key)=>(int)($counts[$key] ?? 0));
            $path='catalog-media-queue/latest.json';
            $report=Storage::disk('local')->exists($path) ? json_decode(Storage::disk('local')->get($path),true) : [];
            return ['counts'=>$counts,'total'=>$total,'completed'=>$total-$pending,'lastRun'=>$report['generated_at'] ?? null];
        }
        $status=$request->string('status')->value();
        if ($status==='restricted') $status='failed';
        if ($status==='failed') $query->whereIn('t.status',['failed','restricted']);
        elseif ($status) $query->where('t.status',$status); else $query->where('t.status','!=','superseded');
        if ($search=trim($request->string('search')->value())) {
            $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
            $query->where(fn($q)=>$q->whereRaw("p.name LIKE ? ESCAPE '!'",[$pattern])->orWhereRaw("p.sku LIKE ? ESCAPE '!'",[$pattern])->orWhereRaw("p.brand LIKE ? ESCAPE '!'",[$pattern]));
        }
        $rows=$query->select('t.*','p.name as product_name','p.sku as product_sku','p.is_active')->orderByRaw("CASE t.status WHEN 'failed' THEN 0 WHEN 'retry' THEN 1 WHEN 'review' THEN 2 WHEN 'processing' THEN 3 WHEN 'queued' THEN 4 ELSE 5 END")->orderByDesc('t.updated_at')->orderBy('t.id')->paginate(30)->withQueryString()->through(function($row) {
            $payload=json_decode($row->payload,true) ?? []; $result=json_decode($row->result ?? '{}',true) ?? [];
            $savedImages=[]; $assignment=null;
            if ($row->status==='preserved') {
                $product=Product::find($row->product_id);
                $record=DB::table('catalog_approved_media as m')->leftJoin('users as u','u.id','=','m.user_id')->where('m.product_id',$row->product_id)->select('m.origin','m.created_at','u.name')->first();
                $assignment=$product?->photos!==null ? ['label'=>'Загружено в карточке товара','at'=>null] : ($record ? ['label'=>$record->origin==='legacy_display' ? 'Перенесено из прежнего показа на сайте' : ($record->origin==='automatic' ? 'Назначено автоматически' : ($record->name ?: 'Назначено через админку')),'at'=>$record->created_at] : null);
                if ($product && $product->photos!==null) {
                    $savedImages=array_map(fn($index)=>['url'=>route('products.photo',[$product->id,$index]),'source'=>'Загружено в карточке','width'=>0,'height'=>0,'flags'=>[]],array_keys($product->photos));
                } elseif ($product) {
                    $savedImages=array_map(fn($im,$index)=>['url'=>url('/admin/products/'.$product->id.'/assigned-photo/'.$index),'source'=>$im['source'] ?? '', 'width'=>$im['width'] ?? 0,'height'=>$im['height'] ?? 0,'flags'=>[]],\App\Support\ApprovedProductMedia::files($product),array_keys(\App\Support\ApprovedProductMedia::files($product)));
                }
            }
            return ['id'=>$row->id,'productId'=>$row->product_id,'name'=>$payload['name'] ?? $row->product_name,'sku'=>$payload['sku'] ?? $row->product_sku,
                'reviewToken'=>hash('sha256',$row->payload.($row->result ?? '').$row->status), 'brand'=>$payload['brand'] ?? '', 'status'=>$row->status,'attempts'=>$row->attempts,'updatedAt'=>$row->updated_at,
                'nextAttempt'=>$row->available_at,'leaseUntil'=>$row->lease_until,'error'=>$row->error,'reason'=>$result['review_reason'] ?? null,
                'ean'=>$payload['ean'] ?? '', 'eanConflict'=>$payload['ean_conflict'] ?? false,
                'assignment'=>$assignment, 'identity'=>$result['identity_evidence'] ?? null, 'sources'=>$result['sources'] ?? [],'errors'=>$result['errors'] ?? [],
                'images'=>$row->status==='preserved' ? $savedImages : array_map(fn($im,$index)=>$this->similarity->photo($im,$row->id,$index,$row->product_id),$result['images'] ?? [],array_keys($result['images'] ?? []))];
        });
        $report=Storage::disk('local')->exists('catalog-media-queue/latest.json') ? json_decode(Storage::disk('local')->get('catalog-media-queue/latest.json'),true) : [];
        $total=$counts->except('superseded')->sum();
        $pending=collect(['queued','processing','retry'])->sum(fn($key)=>(int)($counts[$key] ?? 0));
        return ['tasks'=>$rows,'counts'=>$counts,'total'=>$total,'completed'=>$total-$pending,
            'categories'=>$categories->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'parentId'=>$c->parent_id])->values(),
            'filters'=>['status'=>$status,'search'=>$request->string('search')->value(),'category'=>$category,'subcategory'=>$subcategory], 'lastRun'=>$report['generated_at'] ?? null];
    }

    public function review(Request $request, int $task)
    {
        Gate::authorize('create',Product::class);
        $data=$request->validate(['decision'=>'required|in:accept,accept_all,accept_match,reject,upload','matchId'=>'required_if:decision,accept_match|string|size:64','token'=>'required|string|size:64','index'=>'required_if:decision,accept_match|nullable|integer|min:0','photo'=>'required_if:decision,upload|image|mimes:jpeg,png,webp|max:10240|dimensions:min_width=200,min_height=200,max_width=10000,max_height=10000']);
        DB::transaction(function() use($task,$data,$request) {
            $row=DB::table('catalog_photo_tasks')->where('id',$task)->lockForUpdate()->first();
            abort_unless($row,404);
            $uploadAfterFailure=$data['decision']==='upload' && $row->status==='failed' && empty(json_decode($row->result ?? '{}',true)['images']);
            if (($row->status!=='review' && !$uploadAfterFailure) || !hash_equals(hash('sha256',$row->payload.($row->result ?? '').$row->status),$data['token'])) throw \Illuminate\Validation\ValidationException::withMessages(['review'=>'Результат изменился. Обновите список и проверьте его снова.']);
            $payload=json_decode($row->payload,true);$result=json_decode($row->result ?? '{}',true);
            $product=Product::findOrFail($row->product_id);
            foreach(['name','brand','sku'] as $field) if (($payload[$field] ?? '')!==(string)$product->$field) throw \Illuminate\Validation\ValidationException::withMessages(['review'=>'Данные товара изменились. Нужна новая проверка фото.']);
            if ($data['decision']==='upload') {
                $photo=$request->file('photo');
                [$width,$height]=getimagesize($photo->getRealPath());
                if (max($width,$height)<600) throw \Illuminate\Validation\ValidationException::withMessages(['photo'=>'Длинная сторона фото должна быть не меньше 600 пикселей.']);
                $raw=file_get_contents($photo->getRealPath());
                $file=hash('sha256',$raw).'.'.(['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$photo->getMimeType()]);
                Storage::disk('local')->put('catalog-media-queue/media/'.$file,$raw);
                $result['images'][]=['file'=>$file,'source'=>'Загружено вручную','width'=>$width,'height'=>$height,'flags'=>[],'identity'=>'review'];
                $result['manual_upload']=['user_id'=>$request->user()->id,'at'=>now()->toIso8601String()];
                DB::table('catalog_photo_tasks')->where('id',$task)->update(['status'=>'review','result'=>json_encode($result,JSON_UNESCAPED_UNICODE),'available_at'=>null,'lease_until'=>null,'lease_token'=>null,'updated_at'=>now()]);
                return;
            }
            if ($data['decision']==='accept_match') {
                $original=$result['images'][$data['index']] ?? null;
                $match=$original ? collect($this->similarity->matches($original,$row->product_id))->firstWhere('id',$data['matchId']) : null;
                if (!$match || ApprovedProductMedia::blockingFlags($match['image']) || !empty($payload['ean_conflict'])) throw \Illuminate\Validation\ValidationException::withMessages(['review'=>'Совпадение больше недоступно или фото не проходит проверку качества. Обновите сравнение.']);
                $image=$match['image'];
                $path=$match['directory'].'/'.$image['file'];
                if (!Storage::disk('local')->exists($path)) throw \Illuminate\Validation\ValidationException::withMessages(['review'=>'Файл совпадения больше недоступен.']);
                if ($match['directory']!=='catalog-media-queue/media') Storage::disk('local')->put('catalog-media-queue/media/'.$image['file'],Storage::disk('local')->get($path));
                $result['review_original_images']=$result['images'];
                $result['images']=[$image];
                $result['selected_match']=['product_id'=>$match['productId'],'file'=>$image['file'],'distance'=>$match['distance'],'original_file'=>$original['file']];
            }
            if (in_array($data['decision'],['accept','accept_all'],true)) {
                $images=$data['decision']==='accept_all' ? ($result['images'] ?? []) : [$result['images'][$data['index'] ?? -1] ?? null];
                if (!$images || collect($images)->contains(fn($image)=>!$image || !\App\Support\ApprovedProductMedia::valid($image)) || !empty($payload['ean_conflict'])) throw \Illuminate\Validation\ValidationException::withMessages(['review'=>'Это фото нельзя подтвердить: проверьте качество изображения и конфликт EAN. Можно отклонить фото и продолжить без него.']);
                $result['review_original_images']=$result['images'];
                $result['images']=array_values($images);
            }
            if ($data['decision']==='reject' && isset($data['index'])) {
                $index=$data['index'];
                if (!isset($result['images'][$index])) throw \Illuminate\Validation\ValidationException::withMessages(['review'=>'Фото не найдено. Обновите список.']);
                $result['review_rejected_images'][]=$result['images'][$index];
                array_splice($result['images'],$index,1);
            }
            $status=$data['decision']==='reject' ? (isset($data['index']) && !empty($result['images']) ? 'review' : 'rejected') : 'ready';
            $result['manual_review']=['decision'=>$data['decision'],'user_id'=>$request->user()->id,'at'=>now()->toIso8601String()];
            DB::table('catalog_photo_tasks')->where('id',$task)->update(['status'=>$status,'result'=>json_encode($result,JSON_UNESCAPED_UNICODE),'available_at'=>null,'lease_until'=>null,'lease_token'=>null,'updated_at'=>now()]);
        });
        app(\App\Support\CatalogPhotoAssignment::class)->assign($task);
        if ($request->expectsJson()) {
            $row=DB::table('catalog_photo_tasks')->find($task);
            $result=json_decode($row->result,true);
            return response()->json([
                'status'=>$row->status,
                'reviewToken'=>hash('sha256',$row->payload.$row->result.$row->status),
                'updatedAt'=>$row->updated_at,
                'images'=>array_map(fn($image,$index)=>$this->similarity->photo($image,$task,$index,$row->product_id),$result['images'] ?? [],array_keys($result['images'] ?? [])),
            ]);
        }
        return back()->with('success',match($data['decision']) {'upload'=>'Фото загружено. Проверьте изображение и подтвердите его.','accept','accept_all','accept_match'=>'Фото подтверждено и назначено товару.',default=>isset($data['index']) ? 'Фото отклонено. Проверьте оставшиеся фотографии.' : 'Фото отклонено. Оно больше не блокирует публикацию товара.'});
    }

    public function matches(Request $request, int $task, int $index)
    {
        Gate::authorize('create',Product::class);
        $row=DB::table('catalog_photo_tasks')->find($task);abort_unless($row,404);
        $image=(json_decode($row->result ?? '{}',true)['images'] ?? [])[$index] ?? null;abort_unless($image,404);
        $matches=$this->similarity->matches($image,$row->product_id);
        return response()->json(['matches'=>array_map(fn($match)=>$this->similarity->present($match),array_slice($matches,0,100)),'total'=>count($matches),'reviewToken'=>hash('sha256',$row->payload.($row->result ?? '').$row->status)]);
    }
    public function image(int $task, int $index)
    {
        Gate::authorize('create', Product::class);
        $row=DB::table('catalog_photo_tasks')->where('id',$task)->first(); abort_unless($row,404);
        $result=json_decode($row->result ?? '{}',true) ?? [];
        $file=$result['images'][$index]['file'] ?? '';
        abort_unless(preg_match('/\A[a-f0-9]{64}\.(jpg|png|webp)\z/',$file),404);
        $path=Storage::disk('local')->path('catalog-media-queue/media/'.$file);abort_unless(is_file($path),404);
        return response()->file($path,['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','X-Robots-Tag'=>'noindex, nofollow']);
    }
}
