<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'storefront';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'navigation' => [
                'home' => route('home'),
                'catalog' => route('products.index'),
                'services' => route('services.index'),
                'blog' => route('blog.index'),
                'logo' => asset('images/logo.svg'),
                'catalogCategories' => \App\Support\CatalogDirectory::categories(includeEmpty: true)
                    ->map(fn ($category) => ['name' => $category['category'],
                        'count' => $category['products_count'],
                        'children' => $category['children'] ?? collect(config('storefront.catalog_subcategories', [])[$category['category']] ?? [])
                            ->map(fn ($name) => ['name' => $name, 'url' => route('products.index', ['category' => $name])])->values(),
                        'url' => $category['url'] ?? route('products.index', ['category' => $category['category']])]),
                'pages' => \App\Models\Page::where('is_active', true)->orderBy('title')
                    ->get(['title', 'slug'])->map(fn ($page) => ['name' => $page->title, 'url' => route('pages.show', $page->slug)]),
            ],
            'contactUrl' => route('contact.order-service'),
            'authenticated' => (bool) $request->user(),
            'commerceEnabled' => (bool) config('commerce.enabled'),
            'cartUrl' => route('cart.index'),
            'cartSummary' => function () use ($request) {
                if (! config('commerce.enabled') || ! $request->session()->has('commerce.cart_id')) {
                    return ['count' => 0, 'addedUrls' => [], 'lines' => [], 'maxQuantity' => (int) config('commerce.max_quantity')];
                }
                $cart = app(\App\Commerce\ShoppingCart::class)->current($request);
                $lines = $cart?->lines ?? collect();
                return [
                    'count' => (int) $lines->sum('quantity'),
                    'maxQuantity' => (int) config('commerce.max_quantity'),
                    'lines' => $lines->filter(fn ($line) => ! empty($line->meta['product_id']))->map(fn ($line) => [
                        'addUrl' => route('cart.add', $line->meta['product_id']),
                        'updateUrl' => route('cart.update', $line->id),
                        'removeUrl' => route('cart.remove', $line->id),
                        'quantity' => (int) $line->quantity,
                    ])->values(),
                    'addedUrls' => $lines->map(fn ($line) => $line->meta['product_id'] ?? null)
                        ->filter()->unique()->map(fn ($id) => route('cart.add', $id))->values(),
                ];
            },
        ];
    }
}
