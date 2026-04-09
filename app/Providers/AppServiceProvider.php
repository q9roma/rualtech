<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
