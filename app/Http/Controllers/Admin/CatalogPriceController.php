<?php
namespace App\Http\Controllers\Admin;

use App\Admin\CsvImport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCatalogPrice;
use App\Models\{CatalogPriceUpload, Product};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Support\Str;
use Inertia\Inertia;

class CatalogPriceController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('create', Product::class);
        return Inertia::render('Admin/CatalogPrice', ['upload' => null, 'details' => [],
            'priceToday'=>now('Europe/Moscow')->toDateString(),
            'sourceHealth'=>collect(\App\Admin\CatalogPresence::SOURCES)->except('unlinked')->map(function($label,$source) {
                $last=CatalogPriceUpload::where('source_profile',$source)->where('status','completed')->latest('updated_at')->first();
                return ['source'=>$source,'label'=>$label,'at'=>$last?->updated_at,'priceDate'=>$last?->price_date,'stale'=>!$last || $last->updated_at->lt(now()->subHours(48)) || ($last->price_date && \Carbon\Carbon::parse($last->price_date)->lt(today()->subDays(2)))];
            })->values(),
            'history' => CatalogPriceUpload::where('user_id', $request->user()->id)->latest()->limit(20)->get()]);
    }
    public function store(Request $request)
    {
        Gate::authorize('create', Product::class);
        $request->validate(['file' => 'required|file|extensions:xlsx|max:20480', 'price_date'=>'required|date_format:Y-m-d|before_or_equal:'.now('Europe/Moscow')->toDateString()]);
        $upload = new CatalogPriceUpload(['id' => (string) Str::uuid(), 'user_id' => $request->user()->id,
            'name' => mb_substr($request->file('file')->getClientOriginalName(), 0, 255),
            'status' => 'queued', 'price_date'=>$request->input('price_date'), 'file_hash' => hash_file('sha256', $request->file('file')->getRealPath())]);
        $request->file('file')->storeAs($upload->directory(), 'source.xlsx', 'local');
        $upload->save();
        ProcessCatalogPrice::dispatch($upload->id);
        return redirect('/admin/catalog-prices/'.$upload->id);
    }
    private function owned(Request $request, string $id): CatalogPriceUpload
    {
        Gate::authorize('create', Product::class);
        return CatalogPriceUpload::where('user_id', $request->user()->id)->findOrFail($id);
    }
    public function show(Request $request, string $id)
    {
        $upload = $this->owned($request, $id);
        $path = $upload->directory().'/report.json';
        $details = Storage::disk('local')->exists($path) ? json_decode(Storage::disk('local')->get($path), true) : [];
        // Enrich old reports from their verified source snapshot, never from today's rules.
        $stagedPath = $upload->directory().'/staged-products.json';
        $staged = Storage::disk('local')->exists($stagedPath) ? Storage::disk('local')->get($stagedPath) : null;
        $sourceRows = $staged && hash_equals($upload->staged_hash ?? '', hash('sha256', $staged))
            ? collect(json_decode($staged, true))->keyBy('row') : collect();
        if ($upload->status === 'ready' && $sourceRows->isNotEmpty()) $upload->safety=\App\Admin\CatalogPriceSafety::inspect($upload,$sourceRows->values()->all());
        $composition=$sourceRows->isNotEmpty() ? \App\Admin\CatalogPriceComposition::compare($upload,$sourceRows->values()->all()) : ['baseline'=>null,'appeared'=>[],'missing'=>[]];
        $appeared=array_fill_keys($composition['appeared'],true);
        $details=array_map(fn($row)=>[...$row,'membership'=>isset($appeared[$row['row']]) ? 'appeared' : 'retained'],$details);
        foreach($composition['missing'] as $index=>$old) {
            $key=-($index+1);
            $sourceRows->put($key,$old);
            $details[]=['row'=>$key,'sku'=>$old['sku'],'name'=>$old['name'],'operation'=>'absent','membership'=>'missing',
                'price'=>$old['price'],'stock'=>$old['stock'],'changes'=>[],'issues'=>[]];
        }
        $taxonomy=\App\Models\ProductCategory::orderBy('sort_order')->orderBy('name')->get();
        $taxonomyById=$taxonomy->keyBy('id');
        $linkedCategories=DB::table('catalog_source_products as cs')->join('products as p','p.id','=','cs.product_id')->where('cs.source',$upload->source_profile)->pluck('p.catalog_category_id','cs.identity_hash');
        $itemDecisions = \App\Models\CatalogImportMapping::where('source', $upload->source_profile)->where('rule_code','item-decision')->get()->keyBy('mapping_key');
        $details = collect($details)->map(function ($row) use ($sourceRows,$upload,$taxonomy,$taxonomyById,$linkedCategories,$itemDecisions) {
            $source = $sourceRows->get($row['row'], []);
            $hash=$upload->source_profile==='supplier-price-32' ? hash('sha256','supplier-id:'.trim($source['supplier_id'] ?? '')) : \App\Admin\CatalogDraftImport::identity($source['brand'] ?? '',$source['sku'] ?? '',$source['name'] ?? '');
            $leaf=$taxonomyById->get($linkedCategories->get($hash));
            if (!$leaf && $row['operation']==='created_drafts') {
                $root=$taxonomy->first(fn($c)=>!$c->parent_id && $c->name===($source['section'] ?? ''));
                $leaf=$root ? $taxonomy->first(fn($c)=>$c->parent_id===$root->id && $c->name===($source['subcategory'] ?? '')) : null;
            }
            $decision = $source ? $itemDecisions->get(\App\Admin\CatalogDraftImport::itemKey($upload->source_profile, $source)) : null;
            if (!$leaf && $decision?->decision === 'include') $leaf = $taxonomyById->get($decision->catalog_category_id);
            return [...$row, 'assortmentDecision'=>$decision?->decision, 'decisionCategory'=>$decision?->catalog_category_id, 'sourceCategory'=>$source['source_category'] ?? null, 'rule'=>$source['rule'] ?? null,
                'categoryId'=>$leaf ? ($leaf->parent_id ?: $leaf->id) : null,'subcategoryId'=>$leaf?->parent_id ? $leaf->id : null];
        });
        $request->validate(['section'=>'nullable|in:upload,compare,apply,photos,result', 'compare_search'=>'nullable|string|max:200', 'operation'=>'nullable|in:existing,skipped,updated,created_drafts,held,request_only,excluded,existing_untouched', 'compare_page'=>'nullable|integer|min:1', 'reason'=>'nullable|string|max:64','category'=>'nullable|integer|min:0','subcategory'=>'nullable|integer|min:1','membership'=>'nullable|in:appeared,missing','anomaly'=>'nullable|in:price,stock,missing,data']);
        $section = $request->input('section', $upload->status === 'completed' ? 'result' : 'compare');
        $search = trim($request->input('compare_search', ''));
        $operation = $request->input('operation') ?? '';
        $anomaly=(string)($request->input('anomaly') ?? '');
        $anomalies=collect(\App\Admin\CatalogPriceAnomalies::LABELS)->map(fn($label,$key)=>['key'=>$key,'label'=>$label,'count'=>$details->filter(fn($r)=>\App\Admin\CatalogPriceAnomalies::matches($r,$key))->count()])->values();
        $membership=(string)($request->input('membership') ?? '');
        $category=(string)($request->input('category') ?? '');
        $subcategory=(string)($request->input('subcategory') ?? '');
        if ($subcategory!=='') {
            $leaf=$taxonomyById->get((int)$subcategory);
            abort_unless($leaf && $leaf->parent_id && ($category==='' || (int)$category===$leaf->parent_id),422);
            $category=(string)$leaf->parent_id;
        }
        $reason = $request->input('reason', '');
        $reasonKey = function ($row) {
            $issues = $row['issues']; sort($issues);
            return hash('sha256', json_encode([$row['operation'], $row['operation']==='held' && !$issues ? $row['rule'] : null, $row['operation']==='held' ? $issues : []]));
        };
        $groups = $details->filter(fn($r)=>in_array($r['operation'], ['held','excluded','request_only']))->groupBy($reasonKey)
            ->map(fn($items,$key)=>['key'=>$key,'count'=>$items->count(),'operation'=>$items->first()['operation'],'rule'=>$items->first()['rule'],'issues'=>$items->first()['issues']])->values();
        $rows = $details->filter(fn($r) => ($anomaly ? \App\Admin\CatalogPriceAnomalies::matches($r,$anomaly) : ($membership ? $r['membership']===$membership : (!$operation ? $r['operation']!=='absent' : ($operation === 'existing' ? in_array($r['operation'], ['updated','existing_untouched']) : ($operation === 'skipped' ? in_array($r['operation'], ['held','excluded','request_only']) : $r['operation'] === $operation))))) && ($category==='' || (int)($r['categoryId'] ?? 0)===(int)$category) && ($subcategory==='' || (int)($r['subcategoryId'] ?? 0)===(int)$subcategory) && (!$reason || $reasonKey($r)===$reason) && (!$search || mb_stripos($r['name'].' '.$r['sku'], $search) !== false))
            ->sortBy(fn($r)=>array_search($r['operation'], ['updated','created_drafts','held','request_only','excluded','existing_untouched']))->values();
        $last = max(1, (int) ceil($rows->count()/50));
        $quickCounts = [];
        foreach (['', 'updated', 'existing_untouched', 'created_drafts', 'skipped'] as $filter) {
            $quickCounts[$filter ?: 'all'] = $details->filter(fn($r) => $r['operation'] !== 'absent'
                && (!$filter || ($filter === 'skipped' ? in_array($r['operation'], ['held','excluded','request_only']) : $r['operation'] === $filter))
                && (!$filter || $category === '' || (int)($r['categoryId'] ?? 0) === (int)$category)
                && (!$filter || $subcategory === '' || (int)($r['subcategoryId'] ?? 0) === (int)$subcategory)
                && (!$search || mb_stripos($r['name'].' '.$r['sku'], $search) !== false))->count();
        }
        $quickCounts['uncategorized'] = $details->filter(fn($r) => $r['operation'] !== 'absent'
            && (int)($r['categoryId'] ?? 0) === 0
            && (!$search || mb_stripos($r['name'].' '.$r['sku'], $search) !== false))->count();
        $page = min($last, (int) $request->input('compare_page', 1));
        $pageRows = $rows->slice(($page-1)*50,50)->values();
        $identities = $pageRows->mapWithKeys(function($row) use($sourceRows,$upload) {
            $source=$sourceRows->get($row['row']);
            if (!$source) return [];
            $hash=$upload->source_profile==='supplier-price-32'
                ? hash('sha256','supplier-id:'.trim($source['supplier_id'] ?? ''))
                : \App\Admin\CatalogDraftImport::identity($source['brand'] ?? '',$source['sku'] ?? '',$source['name']);
            return [$row['row']=>$hash];
        });
        $links=DB::table('catalog_source_products')->where('source',$upload->source_profile)->whereIn('identity_hash',$identities->values())->pluck('product_id','identity_hash');
        $products=Product::whereIn('id',$links->values())->get()->keyBy('id');
        $mediaPath='catalog-automation/photo-quality/review.json';
        $media=app()->environment('local') && Storage::disk('local')->exists($mediaPath)
            ? collect(json_decode(Storage::disk('local')->get($mediaPath),true))->keyBy('id') : collect();
        $pageRows=$pageRows->map(function($row) use($identities,$links,$products,$media,$upload,$sourceRows) {
            // Preview-created IDs are rolled back; only persisted source links are safe.
            $product=$products->get($links->get($identities->get($row['row'])));
            if ($row['operation']==='created_drafts' && $upload->status!=='completed') $product=null;
            $cover=null;
            if ($product) {
                $images=\App\Support\ProductMediaQuality::images($product) ?? \App\Support\ProductMediaPilot::images($product);
                $cover=$images[0] ?? null;
                $candidate=$media->get($product->id);
                if (!$cover && $candidate && $candidate['name']===$product->name && $candidate['sku']===$product->sku && $candidate['brand']===$product->brand && !empty($candidate['gallery'])) {
                    $cover=route('catalog.photo-quality-image',$candidate['gallery'][0]['file']);
                }
            }
            return [...$row,'cover'=>$cover,'brand'=>$product?->brand ?? ($sourceRows->get($row['row'])['brand'] ?? null),'productUrl'=>$product ? ($product->is_active ? route('products.show',$product) : route('admin.product-preview',$product)) : null];
        });
        $photoQueue = null;
        if ($section === 'photos' && $sourceRows->isNotEmpty()) {
            $json = Storage::disk('local')->get($upload->directory().'/staged-products.json');
            abort_unless($json && hash_equals($upload->staged_hash ?? '', hash('sha256', $json)), 409, 'Исходные данные прайса не прошли проверку.');
            $hashes = array_map(fn($r)=>$upload->source_profile === 'supplier-price-32'
                ? hash('sha256', 'supplier-id:'.trim($r['supplier_id'] ?? ''))
                : \App\Admin\CatalogDraftImport::identity($r['brand'] ?? '', $r['sku'] ?? '', $r['name']), json_decode($json,true));
            $ids = DB::table('catalog_source_products')->where('source',$upload->source_profile)->whereIn('identity_hash',$hashes)->pluck('product_id')->unique()->all();
            $photoQueue = app(CatalogPhotoQueueController::class)->data($request, $ids);
        }
        return Inertia::render('Admin/CatalogPrice', ['upload'=>$upload, 'details'=>$pageRows, 'history'=>[], 'section'=>$section,
            'comparison'=>['quickCounts'=>$quickCounts,'anomaly'=>$anomaly,'anomalies'=>$anomalies,'total'=>$rows->count(),'page'=>$page,'lastPage'=>$last,'search'=>$search,'operation'=>$operation,'reason'=>$reason,'groups'=>$groups,'membership'=>$membership,'baseline'=>$composition['baseline'],'appearedCount'=>count($composition['appeared']),'missingCount'=>count($composition['missing']),'category'=>(string)$category,'subcategory'=>(string)$subcategory,'categories'=>$taxonomy->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'parentId'=>$c->parent_id])->values()], 'photoQueue'=>$photoQueue, 'release'=>$section==='result' && $upload->status==='completed' ? app(\App\Admin\CatalogRelease::class)->preview($upload) : null]);
    }

    public function decision(Request $request, string $id)
    {
        $upload = $this->owned($request, $id);
        $data = $request->validate(['row'=>'required|integer|min:2', 'decision'=>'required|in:include,exclude,reset', 'category_id'=>'nullable|integer|exists:product_categories,id']);
        DB::transaction(function () use ($upload, $data) {
            $current = CatalogPriceUpload::whereKey($upload->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($current->status, ['ready','stale']), 409);
            $json = Storage::disk('local')->get($current->directory().'/staged-products.json');
            abort_unless($json && hash_equals($current->staged_hash ?? '', hash('sha256', $json)), 409);
            $row = collect(json_decode($json, true))->firstWhere('row', $data['row']);
            abort_unless($row && ($current->source_profile !== 'supplier-price-32' || filled($row['supplier_id'] ?? null)) && filled($row['brand']) && filled($row['sku']), 422, 'Не хватает данных для однозначного определения товара.');
            $category = \App\Models\ProductCategory::find($data['category_id'] ?? null);
            if ($data['decision'] === 'include' && (!$category?->parent_id || !$category->is_active || !$category->parent?->is_active)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['category_id'=>'Выберите действующую подкатегорию.']);
            }
            // Same serialization lock as imports. This changes a single identity, never the whole rule.
            \App\Models\ProductCategory::orderBy('id')->lockForUpdate()->firstOrFail();
            $key = \App\Admin\CatalogDraftImport::itemKey($current->source_profile, $row);
            if ($data['decision'] === 'reset') \App\Models\CatalogImportMapping::where('mapping_key', $key)->delete();
            else \App\Models\CatalogImportMapping::updateOrCreate(['mapping_key'=>$key], [
                'source'=>$current->source_profile, 'source_category'=>$row['source_category'] ?? '', 'rule_code'=>'item-decision',
                'name'=>mb_substr($row['name'], 0, 255), 'decision'=>$data['decision'], 'catalog_category_id'=>$data['decision']==='include' ? $category->id : null, 'manual_locked'=>true,
                'decision_identity'=>\App\Admin\CatalogDraftImport::decisionIdentity($row),
            ]);
            $current->update(['status'=>'queued', 'error'=>null, 'summary'=>null]);
            ProcessCatalogPrice::dispatch($current->id)->afterCommit();
        });
        return back()->with('success', 'Решение сохранено для этого товара. Пересчитываем сравнение; каталог не изменён.');
    }

    public function release(Request $request, string $id)
    {
        $upload=$this->owned($request,$id);
        $data=$request->validate(['action'=>'required|in:photos,publish,release','token'=>'required|string|size:64','groups'=>'required|array|min:1|max:3','groups.*'=>'required|in:new,updated,unchanged|distinct']);
        abort_unless($upload->status==='completed',409);
        $count=app(\App\Admin\CatalogRelease::class)->execute($upload,$data['action'],$data['token'],$request->user()->id,$data['groups']);
        return back()->with('success', $data['action']==='photos' ? "Фотографии назначены: {$count}." : ($data['action']==='release' ? "Публикация и фотографии сохранены для {$count} товаров." : "Опубликовано товаров: {$count}."));
    }

    public function run(Request $request, string $id)
    {
        $upload = $this->owned($request, $id);
        $request->validate(['safety_ack'=>'nullable|string|size:64']);
        DB::transaction(function () use ($upload, $request) {
            $current = CatalogPriceUpload::whereKey($upload->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'ready', 409, 'Прайс уже отправлен или ещё не проверен.');
            $json=Storage::disk('local')->get($current->directory().'/staged-products.json');
            abort_unless($json && hash_equals($current->staged_hash ?? '',hash('sha256',$json)),409);
            $safety=\App\Admin\CatalogPriceSafety::inspect($current,json_decode($json,true));
            if ($safety['blocked']) throw \Illuminate\Validation\ValidationException::withMessages(['safety_ack'=>implode(' ',$safety['blocked'])]);
            if ($safety['warnings'] && !hash_equals($safety['token'],(string)$request->input('safety_ack'))) throw \Illuminate\Validation\ValidationException::withMessages(['safety_ack'=>'Проверьте предупреждения и подтвердите полноту и актуальность файла.']);
            $current->update(['status' => 'applying','safety'=>$safety,'safety_ack'=>$request->input('safety_ack')]);
            ProcessCatalogPrice::dispatch($current->id, true)->afterCommit();
        });
        return back()->with('success', 'Обновление запущено. Результат появится на этой странице.');
    }
    public function refresh(Request $request, string $id)
    {
        $upload = $this->owned($request, $id);
        DB::transaction(function () use ($upload) {
            $current = CatalogPriceUpload::whereKey($upload->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($current->status, ['ready', 'stale', 'failed']), 409);
            $current->update(['status' => 'queued', 'error' => null, 'summary' => null]);
            ProcessCatalogPrice::dispatch($current->id)->afterCommit();
        });
        return back();
    }
    public function report(Request $request, string $id)
    {
        $upload = $this->owned($request, $id);
        $path = $upload->directory().'/report.json';
        abort_unless(Storage::disk('local')->exists($path), 404);
        $rows = json_decode(Storage::disk('local')->get($path), true);
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Строка', 'Артикул', 'Название', 'Решение', 'Цена из прайса', 'Есть', 'Свободно', 'Транзит', 'Изменения до/после', 'Проверки'], ';', '"', '');
            $labels = ['updated' => 'Обновить', 'created_drafts' => 'Новый черновик', 'existing_untouched' => 'Без изменений', 'held' => 'Проверить', 'excluded' => 'Исключено', 'request_only' => 'Под запрос'];
            foreach ($rows as $row) fputcsv($out, array_map([CsvImport::class, 'safeCell'], [$row['row'], $row['sku'], $row['name'], $labels[$row['operation']], $row['price'], ...$row['stock'], json_encode($row['changes'], JSON_UNESCAPED_UNICODE), implode(', ', $row['issues'])]), ';', '"', '');
            fclose($out);
        }, 'price-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
