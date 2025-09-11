<?php

use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\ServiceController;
use App\Http\Controllers\Frontend\BlogController;
use App\Http\Controllers\Frontend\ContactController;
use Illuminate\Support\Facades\Route;

// Роут для аутентификации (нужен для Filament)
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Главная страница
Route::get('/', [HomeController::class, 'index'])->name('home');

// Услуги
Route::prefix('services')->name('services.')->group(function () {
    Route::get('/', [ServiceController::class, 'index'])->name('index');
    Route::get('/{categorySlug}', [ServiceController::class, 'category'])->name('category');
    Route::get('/{categorySlug}/{serviceSlug}', [ServiceController::class, 'show'])->name('show');
});

// Блог
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/{slug}', [BlogController::class, 'show'])->name('show');
});

// Контакты и заявки
Route::prefix('contact')->name('contact.')->group(function () {
    Route::post('/', [ContactController::class, 'store'])->name('store');
    Route::post('/order-service', [ContactController::class, 'orderService'])->name('order-service');
    Route::post('/services/{service}/order', [ContactController::class, 'orderService'])->name('order-service-by-id');
});

// Страницы (должны быть в самом конце, чтобы не перехватывать другие маршруты)
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
