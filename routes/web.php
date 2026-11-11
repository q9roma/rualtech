<?php

use App\Http\Controllers\Frontend\BlogController;
use App\Http\Controllers\Frontend\ContactController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\ProductController;
use App\Http\Controllers\Frontend\ServiceController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/product-preview/{product}', [ProductController::class, 'preview'])
    ->middleware([\App\Http\Middleware\HandleInertiaRequests::class, \App\Http\Middleware\RequireAdmin::class, \Illuminate\Session\Middleware\AuthenticateSession::class])
    ->name('admin.product-preview');

require __DIR__.'/admin.php';
Route::get('/product-photo/{product}/{index}', \App\Http\Controllers\ProductPhotoController::class)->whereNumber('index')->name('products.photo');

Route::get('/catalog/photo-review', [\App\Http\Controllers\Frontend\PhotoReviewController::class, 'index']);
Route::get('/catalog/photo-quality', [\App\Http\Controllers\Frontend\PhotoQualityController::class, 'index']);
Route::get('/catalog/photo-quality/product/{product}', [\App\Http\Controllers\Frontend\PhotoQualityController::class, 'product'])
    ->middleware(\App\Http\Middleware\HandleInertiaRequests::class)->name('catalog.photo-quality-product');
Route::get('/catalog-approved-image/{product}/{index}', function (\App\Models\Product $product, int $index) {
    abort_unless($product->is_active,404);
    $images=\App\Support\ApprovedProductMedia::files($product);
    abort_unless(isset($images[$index]),404);
    return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path('catalog-media-queue/media/'.$images[$index]['file']), ['Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff']);
})->whereNumber(['product','index'])->name('catalog.approved-image');
Route::get('/catalog-photo-quality-image/{file}', [\App\Http\Controllers\Frontend\PhotoQualityController::class, 'image'])
    ->where('file', '[a-f0-9]{64}\.(jpg|png|webp)')->name('catalog.photo-quality-image');
Route::get('/catalog-photo-review-image/{file}', [\App\Http\Controllers\Frontend\PhotoReviewController::class, 'image'])
    ->where('file', '[a-f0-9]{64}\.(jpg|png|webp)')->name('catalog.photo-review-image');

Route::get('/product-media-pilot/{product}/{index}', [ProductController::class, 'pilotImage'])->whereNumber('index')->name('products.pilot-image');
Route::get('/catalog/photo-preview', [ProductController::class, 'photoPreview'])->middleware(\App\Http\Middleware\HandleInertiaRequests::class);


// Retired diagnostic endpoint must never fall through to CMS content.
Route::any('/test-login', fn () => abort(404));
Route::get('/ready', \App\Http\Controllers\ReadinessController::class)->name('ready');

Route::middleware(\App\Http\Middleware\HandleInertiaRequests::class)->group(function () {

Route::middleware(\App\Http\Middleware\RequireCustomerStorefront::class)->group(function () {
    Route::get('/login', [\App\Http\Controllers\Frontend\AccountController::class, 'form'])->name('login');
    Route::get('/register', [\App\Http\Controllers\Frontend\AccountController::class, 'form'])->name('register');
    Route::post('/login', [\App\Http\Controllers\Frontend\AccountController::class, 'login'])->middleware('throttle:6,1')->block();
    Route::post('/register', [\App\Http\Controllers\Frontend\AccountController::class, 'register'])->middleware('throttle:5,1')->block();
    Route::middleware('auth')->group(function () {
        Route::get('/account', [\App\Http\Controllers\Frontend\AccountController::class, 'index'])->name('account');
        Route::post('/account/claim', [\App\Http\Controllers\Frontend\AccountController::class, 'claim'])->middleware('throttle:20,1')->block();
        Route::post('/logout', [\App\Http\Controllers\Frontend\AccountController::class, 'logout'])->block()->name('logout');
    });
});

// Главная страница
Route::get('/', [HomeController::class, 'index'])->name('home');

// Индексация (robots / sitemap — до catch-all страниц)
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Каталог прайса (до catch-all страниц)
Route::redirect('/katalog', '/catalog', 301);
Route::get('/catalog/suggestions', [ProductController::class, 'suggestions'])->middleware('throttle:120,1');
Route::get('/catalog/category/{catalogCategory:slug}', [ProductController::class, 'category'])->name('catalog.category');
Route::get('/catalog', [ProductController::class, 'index'])
    ->name('products.index');
// Привязка по id: у части товаров slug мог быть пуст — {product:slug} ломал route('products.show', $product)
Route::get('/product/{product}', [ProductController::class, 'show'])->name('products.show');

// Услуги
Route::get('/delivery', fn () => \Inertia\Inertia::render('delivery', [
    'title' => 'Доставка',
    'description' => 'Доставим оборудование штатными курьерами или отправим транспортной компанией на ваш выбор.',
    'canonicalUrl' => route('delivery'),
]))->name('delivery');

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
Route::prefix('contact')->name('contact.')->middleware('throttle:contact')->group(function () {
    Route::post('/', [ContactController::class, 'store'])->name('store');
    Route::post('/order-service', [ContactController::class, 'orderService'])->name('order-service');
    Route::post('/services/{service}/order', [ContactController::class, 'orderService'])->name('order-service-by-id');
});

// Страницы (должны быть в самом конце, чтобы не перехватывать другие маршруты)
Route::get('/cart', [\App\Http\Controllers\Frontend\CommerceController::class, 'index'])->block()->name('cart.index');
Route::post('/cart/products/{product}', [\App\Http\Controllers\Frontend\CommerceController::class, 'add'])->middleware('throttle:60,1')->block()->name('cart.add');
Route::patch('/cart/lines/{line}', [\App\Http\Controllers\Frontend\CommerceController::class, 'update'])->middleware('throttle:60,1')->block()->name('cart.update');
Route::delete('/cart/lines/{line}', [\App\Http\Controllers\Frontend\CommerceController::class, 'remove'])->middleware('throttle:60,1')->block()->name('cart.remove');
Route::get('/checkout', [\App\Http\Controllers\Frontend\CommerceController::class, 'checkout'])->block()->name('checkout.index');
Route::post('/checkout', [\App\Http\Controllers\Frontend\CommerceController::class, 'place'])->middleware('throttle:10,1')->block()->name('checkout.place');
Route::get('/orders/{commerceOrder}', [\App\Http\Controllers\Frontend\CommerceController::class, 'order'])->middleware(\App\Http\Middleware\RequireCustomerStorefront::class)->name('orders.show');

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');

});
