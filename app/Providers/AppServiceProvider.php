<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

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
        // Подключаем helper для склонения русских слов
        require_once app_path('Helpers/RussianPluralHelper.php');
        
        // Устанавливаем русскую локаль для Carbon
        Carbon::setLocale('ru');
        setlocale(LC_TIME, 'ru_RU.UTF-8');
        
        // Принудительно используем HTTPS только для продакшена
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
