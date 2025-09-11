<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index(): View
    {
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

        return view('frontend.home', compact('pages', 'serviceCategories', 'featuredServices'));
    }
}
