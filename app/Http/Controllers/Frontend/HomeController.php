<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index(Request $request): View|\Inertia\Response
    {
        $request->validate(['category' => ['nullable', 'string', 'max:255']]);
        $catalogFilter = $request->string('category')->trim()->value();
        // Кэшируем навигационные страницы на 1 час
        $pages = Cache::remember('navigation_pages', 3600, function () {
            return Page::where('is_active', true)
                ->orderBy('created_at')
                ->limit(6)
                ->get();
        });

        // Кэшируем категории услуг на 30 минут
        $serviceCategories = Cache::remember('home_service_categories', 1800, function () {
            return ServiceCategory::where('is_active', true)
                ->withCount(['services' => function($query) {
                    $query->where('is_active', true);
                }])
                ->orderBy('sort_order')
                ->limit(6)
                ->get();
        });

        // Кэшируем рекомендуемые услуги на 30 минут
        $featuredServices = Cache::remember('home_featured_services', 1800, function () {
            return Service::where('is_active', true)
                ->where('is_featured', true)
                ->with('category')
                ->orderBy('sort_order')
                ->limit(6)
                ->get();
        });

        $catalogQuery = Product::active()->when($catalogFilter !== '', fn ($query) =>
            \App\Support\CatalogDirectory::filter($query, $catalogFilter));
        $catalogTotal = (clone $catalogQuery)->count();
        $catalogProducts = $catalogQuery->orderBy('id')->limit(12)->get();
        $catalogFilters = \App\Support\CatalogDirectory::categories()->pluck('category')->unique()->values();
        $canonicalUrl = route('home');
        $catalogSections = \App\Support\CatalogDirectory::categories()
            ->filter(fn ($category) => $category['products_count'] > 0)->values()->map(function ($category) {
            return [
                'name' => $category['category'],
                'total' => $category['products_count'],
                'products' => \App\Support\CatalogDirectory::filter(Product::active(), $category['category'])
                    ->orderBy('id')->limit(4)->get()->map(\App\Support\Storefront::cartProduct(...)),
            ];
        });

        return \App\Support\Storefront::page('frontend.home', compact('pages', 'serviceCategories', 'featuredServices', 'catalogProducts', 'catalogFilter', 'catalogFilters', 'catalogTotal', 'canonicalUrl', 'catalogSections'));
    }
}
