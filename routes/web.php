<?php

use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\ServiceController;
use App\Http\Controllers\Frontend\BlogController;
use Illuminate\Support\Facades\Route;

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

// Страницы (должны быть в самом конце, чтобы не перехватывать другие маршруты)
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
