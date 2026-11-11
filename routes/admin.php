<?php

use App\Http\Controllers\Admin\{AuthController, DashboardController, ImportController, NotificationController, OrderController, ResourceController};
use App\Http\Middleware\{AdminInertiaRequests, RequireAdmin};
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(AdminInertiaRequests::class)->group(function () {
    Route::get('login', [AuthController::class, 'form'])->name('admin.login');
    Route::get('local-reset-password/{token}', [\App\Http\Controllers\Admin\LocalPasswordResetController::class,'form'])->middleware('throttle:10,1');
    Route::post('local-reset-password', [\App\Http\Controllers\Admin\LocalPasswordResetController::class,'reset'])->middleware('throttle:6,1')->block();
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1')->block();
    Route::middleware([RequireAdmin::class, \Illuminate\Session\Middleware\AuthenticateSession::class])->group(function () {
        Route::get('/', DashboardController::class)->name('admin.dashboard');
        Route::post('logout', [AuthController::class, 'logout'])->name('admin.logout')->block();
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('products/{product}/history', [\App\Http\Controllers\Admin\ProductHistoryController::class, 'show'])->whereNumber('product');
        Route::get('products/{product}/history/{event}/photo/{side}/{index}', [\App\Http\Controllers\Admin\ProductHistoryController::class, 'photo'])->whereNumber(['product','event','index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
        Route::get('imports/create', [ImportController::class, 'create']);
        Route::get('catalog-prices', [\App\Http\Controllers\Admin\CatalogPriceController::class, 'index']);
        Route::get('catalog-photo-queue/{task}/matches/{index}', [\App\Http\Controllers\Admin\CatalogPhotoQueueController::class, 'matches'])->whereNumber(['task','index']);
        Route::get('products/{product}/assigned-photo/{index}', [\App\Http\Controllers\Admin\CatalogPhotoQueueController::class, 'assignedImage'])->whereNumber(['product','index']);
        Route::get('catalog-photo-queue', [\App\Http\Controllers\Admin\CatalogPhotoQueueController::class, 'index']);
        Route::get('catalog-photo-queue/progress', [\App\Http\Controllers\Admin\CatalogPhotoQueueController::class, 'progress']);
        Route::post('catalog-photo-queue/{task}/review', [\App\Http\Controllers\Admin\CatalogPhotoQueueController::class, 'review'])->whereNumber('task')->middleware('throttle:30,1');
        Route::get('catalog-photo-queue/{task}/image/{index}', [\App\Http\Controllers\Admin\CatalogPhotoQueueController::class, 'image'])->whereNumber(['task','index']);
        Route::post('catalog-prices', [\App\Http\Controllers\Admin\CatalogPriceController::class, 'store'])->middleware('throttle:10,1');
        Route::get('catalog-prices/{id}', [\App\Http\Controllers\Admin\CatalogPriceController::class, 'show'])->whereUuid('id');
        Route::post('catalog-prices/{id}/decision', [\App\Http\Controllers\Admin\CatalogPriceController::class, 'decision'])->whereUuid('id')->middleware('throttle:30,1');
        Route::post('catalog-prices/{id}/release', [\App\Http\Controllers\Admin\CatalogPriceController::class, 'release'])->whereUuid('id')->middleware('throttle:10,1');
        Route::post('catalog-prices/{id}/run', [\App\Http\Controllers\Admin\CatalogPriceController::class, 'run'])->whereUuid('id')->middleware('throttle:5,1');
        Route::post('catalog-prices/{id}/refresh', [\App\Http\Controllers\Admin\CatalogPriceController::class, 'refresh'])->whereUuid('id')->middleware('throttle:5,1');
        Route::get('catalog-prices/{id}/report', [\App\Http\Controllers\Admin\CatalogPriceController::class, 'report'])->whereUuid('id');
        Route::get('imports/example', [ImportController::class, 'example']);
        Route::get('imports/legacy/{import}/errors', [ImportController::class, 'legacyErrors'])->whereNumber('import');
        Route::post('imports', [ImportController::class, 'preview'])->middleware('throttle:10,1');
        Route::get('imports/{id}', [ImportController::class, 'show']);
        Route::post('imports/{id}/run', [ImportController::class, 'run'])->middleware('throttle:5,1');
        Route::get('imports/{id}/errors', [ImportController::class, 'errors']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{record}', [OrderController::class, 'show'])->whereNumber('record');
        Route::post('orders/{record}/status', [OrderController::class, 'status'])->whereNumber('record');
        Route::get('{resource}', [ResourceController::class, 'index']);
        Route::get('{resource}/create', [ResourceController::class, 'create']);
        Route::post('{resource}/bulk-delete', [ResourceController::class, 'bulkDestroy']);
        Route::post('{resource}/attachments', [ResourceController::class, 'attachment'])->middleware('throttle:30,1');
        Route::post('{resource}', [ResourceController::class, 'store']);
        Route::get('{resource}/{record}', [ResourceController::class, 'show'])->whereNumber('record');
        Route::get('{resource}/{record}/edit', [ResourceController::class, 'edit'])->whereNumber('record');
        Route::get('{resource}/{record}/image/{field}', [ResourceController::class, 'image'])->whereNumber('record');
        Route::put('{resource}/{record}', [ResourceController::class, 'update'])->whereNumber('record');
        Route::delete('{resource}/{record}', [ResourceController::class, 'destroy'])->whereNumber('record');
    });
});
