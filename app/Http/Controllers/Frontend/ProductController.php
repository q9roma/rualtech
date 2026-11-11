<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function pilotImage(Product $product, int $index)
    {
        abort_unless($product->is_active, 404);
        $entry = \App\Support\ProductMediaPilot::entry($product);
        $file = $entry['preview_images'][$index]['file'] ?? '';
        abort_unless(preg_match('/^\d+\.(jpg|png|webp)$/', $file), 404);
        $path = \Illuminate\Support\Facades\Storage::disk('local')->path('icecat-pilot/media/'.$product->id.'/'.$file);
        abort_unless(is_file($path), 404);
        return response()->file(\App\Support\ProductImageDelivery::path($path, (string) request()->query('size', 'full')), ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function photoPreview()
    {
        abort_unless(app()->environment('local'), 404);
        $ids = array_column(\App\Support\ProductMediaPilot::rows(), 'id');
        $products = Product::active()->whereIn('id', $ids)->get()->map(fn ($p) => [
            ...\App\Support\Storefront::product($p), 'images' => \App\Support\ProductMediaPilot::images($p),
        ]);
        return Inertia::render('products/photo-preview', ['title' => 'Пробные фотографии товаров', 'products' => $products, 'canonicalUrl' => url('/catalog/photo-preview')]);
    }

    public function suggestions(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['search' => ['required', 'string', 'max:200']]);
        $search = trim($request->string('search')->value());
        if (mb_strlen($search) < 2) return response()->json(['items' => [], 'total' => 0]);
        $query = Product::query()->active()->search($search);
        $total = (clone $query)->count();
        $items = $query->with('catalogCategory')->searchRelevance($search)
            ->orderBy('name')->orderBy('id')->limit(6)->get()
            ->map(fn (Product $product) => [
                ...\App\Support\Storefront::product($product),
                'price' => $product->formatted_price,
            ]);
        return response()->json(['items' => $items, 'total' => $total]);
    }

    public function index(Request $request): View|Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'brands' => ['nullable', 'array', 'max:100'],
            'brands.*' => ['string', 'max:255'],
            'in_stock' => ['nullable', 'boolean'],
        ]);

        $query = Product::query()->active()->with('catalogCategory');

        $search = trim($request->string('search')->value());
        if ($search !== '') $query->search($search);

        if ($request->filled('category')) {
            \App\Support\CatalogDirectory::filter($query, $request->string('category')->value());
        }

        $facets = [
            'brands' => (clone $query)->whereNotNull('brand')->where('brand', '!=', '')
                ->select('brand')->selectRaw('COUNT(*) as count')->groupBy('brand')->orderBy('brand')->get()
                ->map(fn ($row) => ['name' => $row->brand, 'count' => (int) $row->count])->values(),
            'priceMin' => (clone $query)->min('price'),
            'priceMax' => (clone $query)->max('price'),
        ];
        $priceMin = $request->filled('price_min') ? (float) $request->input('price_min') : null;
        $priceMax = $request->filled('price_max') ? (float) $request->input('price_max') : null;
        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            [$priceMin, $priceMax] = [$priceMax, $priceMin];
        }
        $brands = array_values(array_unique($request->input('brands') ?? []));
        if ($priceMin !== null) $query->where('price', '>=', $priceMin);
        if ($priceMax !== null) $query->where('price', '<=', $priceMax);
        if ($brands) $query->whereIn('brand', $brands);
        if ($request->boolean('in_stock')) {
            // Match the exact availability rules used by product cards and checkout.
            $available = (clone $query)->select('availability')->distinct()->pluck('availability')
                ->filter(fn ($value) => \App\Commerce\ProductBridge::inStock(new Product(['availability' => $value])))->values();
            $query->whereIn('availability', $available);
        }

        $sort = $request->query('sort', $search !== '' ? 'relevance' : 'name');
        if (! in_array($sort, ['relevance', 'name', 'price_asc', 'price_desc', 'brand'], true)) {
            $sort = 'name';
        }

        match ($sort) {
            'relevance' => $query->searchRelevance($search)->orderBy('name'),
            'price_asc' => $query
                ->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END')
                ->orderBy('price')
                ->orderBy('name'),
            'price_desc' => $query
                ->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('price')
                ->orderBy('name'),
            'brand' => $query->orderBy('brand')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        $products = $query->orderBy('id')->paginate(48)->appends($request->only('search', 'category', 'sort', 'price_min', 'price_max', 'brands', 'in_stock'));

        abort_if($products->currentPage() > 1 && $products->isEmpty(), 404);
        $pageNum = max(1, $request->integer('page', 1));
        $canonicalUrl = $request->routeIs('catalog.category') ? url($request->path()) : route('products.index');
        if (!$request->routeIs('catalog.category') && $request->filled('category')) {
            $category = \App\Models\ProductCategory::where('is_active', true)->where(fn ($q) => $q->where('name', $request->input('category'))->orWhere('slug', $request->input('category')))->first();
            if ($category && (!$category->parent_id || $category->parent?->is_active)) $canonicalUrl = route('catalog.category', $category->slug);
            else $canonicalUrl .= '?'.http_build_query(['category' => $request->input('category')]);
        }
        if ($pageNum > 1) {
            $canonicalUrl .= (str_contains($canonicalUrl, '?') ? '&' : '?').'page='.$pageNum;
        }

        $totalActiveProducts = Product::query()->active()->count();

        $categoryDirectory = \App\Support\CatalogDirectory::categories()
            ->map(fn ($category) => (object) $category);

        if (config('storefront.inertia')) {
            return Inertia::render('catalog/index', [
                'categoryGuide' => $pageNum === 1 && $search === '' ? collect(config('catalog-guides'))->get($request->input('category')) : null,
                'title' => ($request->input('category') ?: 'Каталог товаров').($pageNum > 1 ? ' — страница '.$pageNum : ''),
                'description' => ($request->input('category') ?: 'Оборудование, комплектующие и программное обеспечение').' — каталог Алтех. Цены, характеристики и наличие.'.($pageNum > 1 ? ' Страница '.$pageNum.'.' : ''),
                'products' => Inertia::scroll(fn () => $products->through(\App\Support\Storefront::cartProduct(...))),
                'filters' => [
                    'search' => $request->string('search')->trim()->value(),
                    'category' => $request->string('category')->value(),
                    'sort' => $sort,
                    'price_min' => $priceMin,
                    'price_max' => $priceMax,
                    'brands' => $brands,
                    'in_stock' => $request->boolean('in_stock'),
                ],
                'facets' => $facets,
                'categories' => $categoryDirectory,
                'totalActiveProducts' => $totalActiveProducts,
                'canonicalUrl' => $canonicalUrl,
            ]);
        }

        return \App\Support\Storefront::page('frontend.products.index', compact(
            'products',
            'sort',
            'categoryDirectory',
            'totalActiveProducts',
            'canonicalUrl',
        ));
    }

    public function category(Request $request, \App\Models\ProductCategory $catalogCategory): View|Response
    {
        abort_unless($catalogCategory->is_active && (! $catalogCategory->parent_id || $catalogCategory->parent?->is_active), 404);
        $request->merge(['category' => $catalogCategory->name]);
        return $this->index($request);
    }

    public function show(Product $product): View|\Inertia\Response
    {
        if (! $product->is_active) {
            abort(404);
        }

        return $this->details($product);
    }

    public function preview(Product $product)
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $product);
        \Inertia\Inertia::share(['preview' => true, 'previewIsPublished' => $product->is_active]);
        $response = $this->details($product);
        $response = $response instanceof \Inertia\Response ? $response->toResponse(request()) : response($response);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    private function details(Product $product): View|\Inertia\Response
    {

        // Supply formats belong to the exact same named product, brand and category.
        $title = $product->display_title;
        $variants = Product::query()->active()->whereKeyNot($product->getKey())
            ->where('category', $product->category)->where('brand', $product->brand)
            ->where('name', 'like', addcslashes($title, '%_\\').'%')
            ->orderBy('name')->orderBy('id')->get()
            ->filter(fn (Product $item) => $item->display_title === $title
                && $item->supply_format && $item->supply_format !== $product->supply_format)
            ->values();

        // Never fill the section with unrelated categories or supply variants.
        $related = Product::query()->active()->whereKeyNot($product->getKey())
            ->whereNotIn('id', $variants->pluck('id'))
            ->where('category', $product->category)
            ->when(! $product->category, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($product->brand, fn ($q) => $q->orderByRaw('CASE WHEN brand = ? THEN 0 ELSE 1 END', [$product->brand]))
            ->orderBy('name')->orderBy('id')->cursor()
            ->filter(fn (Product $item) => $item->display_title !== $title)
            ->take(6)->collect()->values();

        return \App\Support\Storefront::page('frontend.products.show', compact('product', 'related', 'variants'));
    }
}
