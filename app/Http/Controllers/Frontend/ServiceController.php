<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View|\Inertia\Response
    {
        if (config('storefront.inertia')) {
            return \Inertia\Inertia::render('services/directions', [
                'title' => 'Услуги и направления',
                'description' => 'Подбор оборудования и программного обеспечения для бизнеса. Выберите задачу, изучите подходящие товары и отправьте запрос.',
                'canonicalUrl' => route('services.index'),
                'directions' => \App\Support\ServiceDirections::cards(),
            ]);
        }
        // Кэшируем категории услуг на 30 минут
        $categories = Cache::remember('services_categories_with_services', 1800, function () {
            return ServiceCategory::where('is_active', true)
                ->with(['activeServices' => function ($query) {
                    $query->with('category')->orderBy('sort_order')->orderBy('id');
                }])
                ->orderBy('sort_order')
                ->get();
        });

        $canonicalUrl = route('services.index');

        return \App\Support\Storefront::page('frontend.services.index', compact('categories', 'canonicalUrl'));
    }

    public function category(string $categorySlug): View|\Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        if (config('storefront.inertia')) {
            if (isset(\App\Support\ServiceDirections::ALIASES[$categorySlug])) {
                return redirect()->route('services.category', \App\Support\ServiceDirections::ALIASES[$categorySlug], 301);
            }
            if (config('directions.'.$categorySlug)) {
                return \Inertia\Inertia::render('services/direction', \App\Support\ServiceDirections::page($categorySlug));
            }
        }
        $category = ServiceCategory::where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $services = $category->activeServices()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(12);

        $pageNum = max(1, (int) request('page', 1));
        $canonicalUrl = route('services.category', ['categorySlug' => $category->slug]);
        if ($pageNum > 1) {
            $canonicalUrl = route('services.category', ['categorySlug' => $category->slug, 'page' => $pageNum]);
        }

        return \App\Support\Storefront::page('frontend.services.category', compact('category', 'services', 'canonicalUrl'));
    }

    public function show(string $categorySlug, string $serviceSlug): View|\Inertia\Response
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
                ->with('category')
                ->where('id', '!=', $service->id)
                ->inRandomOrder()
                ->limit(3)
                ->get();
        });

        $canonicalUrl = route('services.show', [
            'categorySlug' => $category->slug,
            'serviceSlug' => $service->slug,
        ]);

        return \App\Support\Storefront::page('frontend.services.show', compact('service', 'category', 'relatedServices', 'canonicalUrl'));
    }
}
