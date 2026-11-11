<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resources;
use App\Http\Controllers\Controller;
use App\Admin\RichContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Support\Str;
use Inertia\Inertia;

class ResourceController extends Controller
{
    public function index(Request $request, string $resource)
    {
        $model = Resources::model($resource);
        Gate::authorize('viewAny', $model);
        $columns = Resources::columns($resource);
        $query = $model::query();
        if ($resource === 'services') $query->with('category');
        if ($resource === 'service-categories') $query->withCount('activeServices');
        $search = mb_substr((string) $request->query('search', ''), 0, 200);
        $searchable = array_intersect((new $model)->getFillable(), ['name', 'title', 'sku', 'brand', 'category', 'slug', 'email', 'phone', 'service', 'icon', 'price_type', 'seo_title', 'seo_description']);
        if ($search !== '') $query->where(function ($q) use ($searchable, $search) {
            foreach ($searchable as $key) $q->orWhere($key, 'like', '%'.$search.'%');
        });
        $filters = $this->filters($resource);
        $presenceInfo = null;
        if ($resource === 'products') {
            $source = (string) $request->query('catalog_source', '');
            $presenceSource = in_array($source, ['supplier-price-32', 'altech-price']) ? $source : 'supplier-price-32';
            $current = \App\Admin\CatalogPresence::current($presenceSource);
            $presenceInfo = ['source' => \App\Admin\CatalogPresence::SOURCES[$presenceSource], 'file' => $current['upload']?->name];
            if (array_key_exists($source, \App\Admin\CatalogPresence::SOURCES)) \App\Admin\CatalogPresence::source($query, $source);
            if (in_array($request->query('current_price'), ['present', 'absent'])) \App\Admin\CatalogPresence::apply($query, $presenceSource, $request->query('current_price'), $current);
        }
        foreach ($filters as $filter) {
            if ($resource === 'products' && in_array($filter['key'], ['catalog_source', 'current_price'])) continue;
            $value = $request->query($filter['key']);
            if ($value !== null && $value !== '' && array_key_exists($value, $filter['options'])) $query->where($filter['key'], $value);
        }
        $default = match ($resource) { 'products' => 'brand', 'service-categories', 'product-categories' => 'sort_order', 'catalog-mappings' => 'name', default => 'created_at' };
        $sort = in_array($request->query('sort'), array_column($columns, 'key')) ? $request->query('sort') : $default;
        $direction = $request->query('direction', in_array($default, ['brand', 'sort_order']) ? 'asc' : 'desc') === 'asc' ? 'asc' : 'desc';
        if ($sort === 'category_name') $query->orderBy(\App\Models\ServiceCategory::select('name')->whereColumn('id', 'services.service_category_id'), $direction);
        else $query->orderBy($sort, $direction);
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100]) ? (int) $request->query('per_page') : ($resource === 'products' ? 50 : 10);
        $records = $query->orderBy('id')->paginate($perPage)->withQueryString()->through(fn ($record) => $this->serialize($record, $resource, true));
        return Inertia::render('Admin/Index', ['resource' => $resource, 'title' => Resources::LABELS[$resource],
            'columns' => $columns, 'records' => $records, 'filters' => $filters,
            'presenceInfo' => $presenceInfo,
            'query' => [...$request->only(['search', ...array_column($filters, 'key')]), 'sort' => $sort, 'direction' => $direction, 'per_page' => $perPage],
            'canCreate' => Gate::allows('create', $model), 'canDelete' => Gate::allows('deleteAny', $model)]);
    }

    private function filters(string $resource): array
    {
        return match ($resource) {
            'products' => [
                ['key' => 'catalog_source', 'label' => 'Источник', 'options' => \App\Admin\CatalogPresence::SOURCES],
                ['key' => 'current_price', 'label' => 'В актуальном прайсе', 'options' => ['present' => 'Есть', 'absent' => 'Нет']],
                ['key' => 'catalog_category_id', 'label' => 'Подкатегория', 'options' => \App\Models\ProductCategory::leafOptions()],
                ...array_map(fn ($key, $label) => ['key' => $key, 'label' => $label, 'options' => \App\Models\Product::whereNotNull($key)->distinct()->orderBy($key)->pluck($key, $key)->filter()->all()], ['brand', 'category'], ['Бренд', 'Категория']),
                ['key' => 'is_active', 'label' => 'Публикация', 'options' => ['1' => 'Опубликованы', '0' => 'Черновики']]],
            'product-categories' => [['key' => 'parent_id', 'label' => 'Раздел', 'options' => \App\Models\ProductCategory::whereNull('parent_id')->pluck('name', 'id')->all()]],
            'catalog-mappings' => [['key' => 'decision', 'label' => 'Решение', 'options' => ['include' => 'Включать', 'exclude' => 'Исключать', 'request' => 'Под запрос', 'review' => 'Проверить']]],
            'pages' => [['key' => 'is_active', 'label' => 'Активность', 'options' => ['1' => 'Активные', '0' => 'Неактивные']]],
            'contact-requests' => [['key' => 'status', 'label' => 'Статус', 'options' => \App\Models\ContactRequest::getStatuses()], ['key' => 'source', 'label' => 'Источник', 'options' => \App\Models\ContactRequest::getSources()]],
            default => [],
        };
    }

    private function serialize(Model $record, string $resource, bool $listing = false): array
    {
        $keys = $listing ? array_column(Resources::columns($resource), 'key') : array_column(Resources::fields($resource), 'key');
        $data = $record->only(['id', ...$keys, 'created_at', 'updated_at']);
        if ($resource === 'contact-requests' && !$listing) $data['supplyRequest'] = ($record->meta_data['request_type'] ?? null) === 'supply' ? array_intersect_key($record->meta_data, array_flip(['product_id','quantity','product_name','sku'])) : null;
        if ($resource === 'products') {
            $data['photoUrls'] = array_map(fn ($index) => route('products.photo', [$record->id, $index]), array_keys($record->photos ?? []));
            $data['cover'] = $record->photos !== null ? ($data['photoUrls'][0] ?? null) : ((\App\Support\ProductMediaQuality::images($record) ?? \App\Support\ProductMediaPilot::images($record))[0] ?? null);
            $data['contentMissing'] = array_values(array_filter([
                filled($record->description) ? null : 'Своё описание',
                count($record->specifications ?? []) ? null : 'Характеристики',
                $data['cover'] ? null : 'Фото',
            ]));
            if (! $listing) {
                $data['supplierDescription'] = \App\Support\Storefront::supplierDescription($record);
                $data['priceSources'] = \App\Admin\CatalogHistory::sources($record->id);
            }
        }
        if ($resource === 'services') $data['category_name'] = $record->category?->name;
        foreach (Resources::fields($resource) as $field) {
            $key = $field['key'];
            if (! array_key_exists($key, $data)) continue;
            if ($field['type'] === 'datetime-local') $data[$key] = $record->$key?->format('Y-m-d\TH:i');
            if ($field['type'] === 'richtext') $data[$key] = RichContent::sanitize($record->$key);
            if ($field['type'] === 'image') $data[$key.'_url'] = $record->$key ? url("/admin/{$resource}/{$record->id}/image/{$key}") : null;
        }
        $data['canEdit'] = Gate::allows('update', $record);
        $data['canDelete'] = Gate::allows('delete', $record);
        return $data;
    }

    public function create(string $resource)
    {
        Gate::authorize('create', Resources::model($resource));
        return $this->form($resource);
    }

    public function show(string $resource, string $record)
    {
        $record = Resources::model($resource)::findOrFail($record);
        Gate::authorize('view', $record);
        return $this->form($resource, $record, true);
    }

    public function edit(string $resource, string $record)
    {
        $record = Resources::model($resource)::findOrFail($record);
        Gate::authorize('update', $record);
        return $this->form($resource, $record);
    }

    private function form(string $resource, ?Model $record = null, bool $readOnly = false)
    {
        if ($resource === 'products') request()->validate(['tab'=>'nullable|in:card,source,history']);
        return Inertia::render('Admin/Form', ['resource' => $resource, 'title' => Resources::LABELS[$resource],
            'productTab'=>request()->query('tab','card'),
            'productHistory'=>$resource === 'products' && $record ? app(ProductHistoryController::class)->data(request(),$record,true) : null,
            'fields' => Resources::fields($resource), 'record' => $record ? $this->serialize($record, $resource) : null, 'readOnly' => $readOnly]);
    }

    public function store(Request $request, string $resource)
    {
        $model = Resources::model($resource);
        Gate::authorize('create', $model);
        $data=$this->validated($request,$resource);
        $record = DB::transaction(fn()=>$model::create($data));
        return redirect('/admin/'.$resource.'/'.$record->id.'/edit')->with('success', 'Запись создана.');
    }

    public function update(Request $request, string $resource, string $record)
    {
        $record = Resources::model($resource)::findOrFail($record);
        Gate::authorize('update', $record);
        $data=$this->validated($request,$resource,$record);
        DB::transaction(fn()=>$record->update($data));
        return back()->with('success', 'Изменения сохранены.');
    }

    private function validated(Request $request, string $resource, ?Model $record = null): array
    {
        if (! in_array($resource, ['products', 'contact-requests', 'product-categories', 'catalog-mappings']) && ! $request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->input('name', $request->input('title', '')))]);
        }
        $rules = Resources::rules($resource, $record);
        foreach (Resources::fields($resource) as $field) if ($field['type'] === 'image' && $request->hasFile($field['key'])) {
            $rules[$field['key']] = ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'];
        }
        $data = $request->validate($rules);
        if ($resource === 'products') {
            if (array_key_exists('specifications', $data)) $data['specifications'] = array_values($data['specifications'] ?? []);
            if (array_key_exists('photos', $data)) $data['photos'] = array_map(
                fn ($photo) => $photo instanceof \Illuminate\Http\UploadedFile ? $photo->store('product-photos', 'local') : $photo,
                array_values($data['photos'] ?? [])
            );
        }
        if ($resource === 'catalog-mappings') $data['manual_locked'] = true;
        // Optional form fields whose database columns have non-null defaults.
        foreach (match ($resource) {
            'products' => ['currency' => 'RUB'],
            'contact-requests' => ['subject' => 'Заказ услуги', 'message' => '', 'source' => 'website'],
            default => [],
        } as $key => $default) if (array_key_exists($key, $data) && $data[$key] === null) $data[$key] = $default;
        foreach (Resources::fields($resource) as $field) {
            $key = $field['key'];
            if ($field['type'] === 'richtext' && isset($data[$key])) $data[$key] = RichContent::sanitize($data[$key]);
            if ($field['type'] === 'image' && $request->hasFile($key)) {
                $data[$key] = $request->file($key)->store($resource === 'blog-posts' ? 'blog' : 'categories', 'public');
            } elseif ($field['type'] === 'image' && ! empty($data[$key]) && $data[$key] !== $record?->$key) {
                throw \Illuminate\Validation\ValidationException::withMessages([$key => 'Загрузите изображение файлом.']);
            }
        }
        return $data;
    }

    public function destroy(string $resource, string $record)
    {
        $record = Resources::model($resource)::findOrFail($record);
        Gate::authorize('delete', $record);
        $record->delete();
        return redirect('/admin/'.$resource)->with('success', 'Запись удалена.');
    }

    public function bulkDestroy(Request $request, string $resource)
    {
        $model = Resources::model($resource);
        Gate::authorize('deleteAny', $model);
        $data = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|distinct']);
        DB::transaction(function () use ($model, $data) {
            $records = $model::whereKey($data['ids'])->lockForUpdate()->get();
            foreach ($records as $record) Gate::authorize('delete', $record);
            foreach ($records as $record) $record->delete();
        });
        return back()->with('success', 'Выбранные записи удалены.');
    }

    public function attachment(Request $request, string $resource)
    {
        $model = Resources::model($resource);
        // The editor is available only on writable content resources.
        abort_unless(in_array($resource, ['services', 'pages', 'blog-posts']), 404);
        if ($request->filled('record_id')) Gate::authorize('update', $model::findOrFail($request->input('record_id')));
        else Gate::authorize('create', $model);
        $request->validate(['file' => 'required|file|mimes:jpeg,png,webp,gif,pdf,txt,doc,docx,xls,xlsx,zip|max:12288']);
        $path = $request->file('file')->store('content', 'public');
        return response()->json(['url' => Storage::disk('public')->url($path), 'name' => $request->file('file')->getClientOriginalName(), 'image' => str_starts_with($request->file('file')->getMimeType(), 'image/')]);
    }

    public function image(string $resource, string $record, string $field)
    {
        $record = Resources::model($resource)::findOrFail($record);
        Gate::authorize('view', $record);
        abort_unless(collect(Resources::fields($resource))->contains(fn ($f) => $f['key'] === $field && $f['type'] === 'image'), 404);
        $path = $record->$field;
        abort_unless(filled($path), 404);
        // Existing Filament uploads used the configured default disk (often private local).
        foreach (array_unique(['public', config('filesystems.default'), 'local']) as $diskName) {
            $disk = Storage::disk($diskName);
            if (! $disk->exists($path)) continue;
            abort_unless(str_starts_with($disk->mimeType($path) ?: '', 'image/'), 404);
            return $disk->response($path, null, ['X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
        }
        abort(404);
    }
}
