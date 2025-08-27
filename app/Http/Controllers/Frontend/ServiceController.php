<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;

class ServiceController extends Controller
{
    public function index(): View
    {
        // Кэшируем категории услуг на 30 минут
        $categories = Cache::remember('services_categories_with_services', 1800, function () {
            return ServiceCategory::where('is_active', true)
                ->with(['activeServices' => function($query) {
                    $query->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();
        });

        return view('frontend.services.index', compact('categories'));
    }

    public function category(string $categorySlug): View
    {
        $category = ServiceCategory::where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $services = $category->activeServices()
            ->orderBy('sort_order')
            ->paginate(12);

        return view('frontend.services.category', compact('category', 'services'));
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

        return view('frontend.services.show', compact('service', 'category', 'relatedServices'));
    }
}
