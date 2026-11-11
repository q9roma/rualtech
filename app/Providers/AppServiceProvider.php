<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Policies\AdminResourcePolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(\App\Models\ProductCategory::class, \App\Policies\CatalogCategoryPolicy::class);
        Gate::policy(\App\Models\CatalogImportMapping::class, \App\Policies\CatalogMappingPolicy::class);
        Gate::policy(\Lunar\Models\Order::class, \App\Policies\OrderPolicy::class);
        // Lunar registers a global {product} binding; existing storefront IDs belong to App\Models\Product.
        $this->app->booted(fn () => \Illuminate\Support\Facades\Route::model('product', \App\Models\Product::class));
        foreach ([\App\Models\Product::class, \App\Models\Page::class,
            \App\Models\Service::class, \App\Models\ServiceCategory::class,
            \App\Models\BlogPost::class, \App\Models\ContactRequest::class] as $model) {
            Gate::policy($model, AdminResourcePolicy::class);
        }
        Gate::policy(\App\Models\Product::class, \App\Policies\CatalogProductPolicy::class);
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Устанавливаем русскую локаль для Carbon
        Carbon::setLocale('ru');
        setlocale(LC_TIME, 'ru_RU.UTF-8');
        
        // Принудительно используем HTTPS только для продакшена
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');
    }
}
