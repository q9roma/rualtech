<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        // Кэшируем категории услуг на 30 минут
        $categories = Cache::remember('services_categories_with_services', 1800, function () {
            return ServiceCategory::where('is_active', true)
                ->with(['activeServices' => function ($query) {
                    $query->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();
        });

        $canonicalUrl = route('services.index');

        return view('frontend.services.index', compact('categories', 'canonicalUrl'));
    }

    public function category(string $categorySlug): View
    {
        $category = ServiceCategory::where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $services = $category->activeServices()
            ->orderBy('sort_order')
            ->paginate(12);

        $pageNum = max(1, (int) request('page', 1));
        $canonicalUrl = route('services.category', ['categorySlug' => $category->slug]);
        if ($pageNum > 1) {
            $canonicalUrl = route('services.category', ['categorySlug' => $category->slug, 'page' => $pageNum]);
        }

        return view('frontend.services.category', compact('category', 'services', 'canonicalUrl'));
    }

    public function show(string $categorySlug, string $serviceSlug): View
    {
        $category = ServiceCategory::where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $service = $category->activeServices()
            ->where('slug', $serviceSlug)
            ->firstOrFail();

        // Кэшируем похожие услуги на 1 час
        $relatedServices = Cache::remember("related_services_{$category->id}_{$service->id}", 3600, function () use ($category, $service) {
            return $category->activeServices()
                ->where('id', '!=', $service->id)
                ->inRandomOrder()
                ->limit(3)
                ->get();
        });

        $canonicalUrl = route('services.show', [
            'categorySlug' => $category->slug,
            'serviceSlug' => $service->slug,
        ]);

        return view('frontend.services.show', compact('service', 'category', 'relatedServices', 'canonicalUrl'));
    }
}
