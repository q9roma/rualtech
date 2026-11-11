<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoQualityTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_search_manifests_never_assign_storefront_media(): void
    {
        Storage::fake('local');
        $product = \App\Models\Product::create(['name'=>'Monitor', 'brand'=>'Test', 'sku'=>'M1', 'is_active'=>true]);
        $file = str_repeat('a',64).'.jpg';
        Storage::disk('local')->put('catalog-automation/photo-quality/review.json', json_encode([[
            'id'=>$product->id, 'brand'=>'Test', 'sku'=>'M1', 'name'=>'Monitor', 'gallery'=>[['file'=>$file]],
        ]]));
        $this->assertSame([], \App\Support\ProductMediaQuality::images($product));
        $this->app['env']='local';
        $this->assertSame([], \App\Support\Storefront::product($product)['images']);
        $product->sku='M2';
        $this->assertSame([], \App\Support\ProductMediaQuality::images($product));
        $product->sku='M1'; $product->is_active=false;
        $this->assertSame([], \App\Support\ProductMediaQuality::images($product));
        $this->assertSame([], \App\Support\Storefront::product($product)['images']);
    }

    public function test_photo_preview_keeps_draft_data_and_does_not_publish(): void
    {
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        Storage::fake('local');
        $this->app['env'] = 'local';
        $product = \App\Models\Product::create(['name' => 'Test monitor', 'sku' => 'M-1', 'brand' => 'Test', 'price' => 123, 'availability' => '5', 'is_active' => false]);
        $before = $product->fresh()->getRawOriginal();
        $file = str_repeat('e', 64).'.jpg';
        Storage::disk('local')->put('catalog-automation/photo-quality/review.json', json_encode([[
            'id' => $product->id, 'brand' => 'Test', 'sku' => 'M-1', 'name' => 'Test monitor',
            'gallery' => [['file' => $file, 'source' => 'VVP']],
        ]]));
        $this->get('/catalog/photo-quality/product/'.$product->id)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('products/show')
                ->where('photoTest.count', 1)->where('product.sku', 'M-1')
                ->where('product.availability', '5')->where('product.canAddToCart', false)
                ->has('product.images', 1));
        $this->assertSame($before, $product->fresh()->getRawOriginal());
        $this->get('/product/'.$product->id)->assertNotFound();
        $product->update(['sku' => 'CHANGED']);
        $this->get('/catalog/photo-quality/product/'.$product->id)->assertNotFound();
        $this->app['env'] = 'production';
        $this->get('/catalog/photo-quality/product/'.$product->id)->assertNotFound();
    }

    public function test_private_quality_gallery_cannot_be_served_in_production_or_outside_manifest(): void
    {
        Storage::fake('local');
        $file = str_repeat('c', 64).'.png';
        $base = 'catalog-automation/photo-quality/';
        Storage::disk('local')->put($base.'approved-media.json', json_encode([$file => true]));
        Storage::disk('local')->put($base.'review.json', json_encode([['gallery' => [['file' => $file]], 'images' => [['file' => str_repeat('d', 64).'.png']]]]));
        Storage::disk('local')->put($base.'media/'.$file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8ZsAAAAASUVORK5CYII='));
        $this->get('/catalog/photo-quality')->assertNotFound();
        $this->get('/catalog-photo-quality-image/'.$file)->assertNotFound();
        $this->app['env'] = 'local';
        $this->get('/catalog-photo-quality-image/'.$file)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/catalog-photo-quality-image/'.str_repeat('d',64).'.png')->assertNotFound();
        $this->get('/catalog-photo-quality-image/coverage.json')->assertNotFound();
    }
}
