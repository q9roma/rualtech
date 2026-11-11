<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('catalog:automation-check')->everyTenMinutes()->withoutOverlapping(30);
Schedule::command('catalog:photos-work --continuous --max-time=3600')->everyMinute()->withoutOverlapping(70)->runInBackground();
Schedule::command('catalog:media-cleanup --apply')->dailyAt('03:30')->withoutOverlapping(30);
Schedule::command('queue:work database --queue=catalog-prices --stop-when-empty --max-jobs=2 --max-time=120 --memory=512 --tries=1 --timeout=600')
    ->everyMinute()->withoutOverlapping(20)->runInBackground();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
