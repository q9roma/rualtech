<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Storefront;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/** Isolated local preview: no model writes and no public media assignments. */
class PhotoQualityController extends Controller
{
    private function read(string $file): array
    {
        abort_unless(app()->environment('local'), 404);
        $path = 'catalog-automation/photo-quality/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);
        return json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function index(Request $request)
    {
        $rows = collect($this->read('review.json'));
        $request->validate(['q' => 'nullable|string|max:200', 'status' => 'nullable|in:covered,uncovered', 'page' => 'nullable|integer|min:1']);
        $search = mb_strtolower(trim($request->string('q')->value()));
        if ($search !== '') $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['name'].' '.$r['sku'].' '.$r['brand']), $search));
        if ($request->input('status') === 'covered') $rows = $rows->filter(fn ($r) => (bool) $r['cover']);
        if ($request->input('status') === 'uncovered') $rows = $rows->filter(fn ($r) => ! $r['cover']);
        $rows = $rows->sortBy(fn ($r) => (! $r['cover'] ? '1' : '0').$r['brand'].$r['sku'])->values();
        $page = $request->integer('page', 1);
        $items = $rows->slice(($page - 1) * 24, 24)->values();
        $products = Product::with('catalogCategory')->whereIn('id', $items->pluck('id'))->get()->keyBy('id');
        return response()->view('catalog.photo-quality', [
            'rows' => new LengthAwarePaginator($items, $rows->count(), 24, $page, ['path' => $request->url(), 'query' => $request->query()]),
            'products' => $products, 'coverage' => $this->read('coverage.json'),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function product(Product $product)
    {
        $row = collect($this->read('review.json'))->firstWhere('id', $product->id);
        abort_unless($row && $row['brand'] === $product->brand && $row['sku'] === $product->sku && $row['name'] === $product->name, 404);
        $response = Inertia::render('products/show', [
            'title' => $product->name, 'canonicalUrl' => request()->url(),
            'product' => [...Storefront::cartProduct($product),
                'description' => $product->description,
                'specifications' => $product->specifications ?? [],
                'images' => array_map(fn ($im) => route('catalog.photo-quality-image', $im['file']), $row['gallery']),
                'supplierDescription' => Storefront::supplierDescription($product),
                'canAddToCart' => false],
            'related' => [], 'variants' => [], 'canAddToCart' => false, 'addToCartUrl' => '',
            'photoTest' => ['sources' => array_values(array_unique(array_column($row['gallery'], 'source'))), 'count' => count($row['gallery'])],
        ])->toResponse(request());
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }

    public function image(string $file)
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\.(jpg|png|webp)\z/', $file), 404);
        $allowed = ($this->read('approved-media.json')[$file] ?? false) === true;
        abort_unless($allowed, 404);
        $path = Storage::disk('local')->path(\App\Support\CatalogMediaStorage::path($file));
        abort_unless(is_file($path), 404);
        return response()->file(\App\Support\ProductImageDelivery::path($path, (string) request()->query('size', 'full')), ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
