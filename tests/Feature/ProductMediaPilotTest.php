<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Support\ProductMediaPilot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaPilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_requires_local_environment_active_product_and_matching_identity(): void
    {
        Storage::fake('local');
        $p = Product::create(['name'=>'Test', 'brand'=>'Brand', 'sku'=>'SKU', 'is_active'=>true]);
        Storage::disk('local')->put('icecat-pilot/preview.json', json_encode([['id'=>$p->id,'sku'=>'SKU','brand'=>'Brand','exact_ean'=>true,'ean_conflict'=>false,'preview_images'=>[['file'=>'0.png']]]]));
        Storage::disk('local')->put('icecat-pilot/media/'.$p->id.'/0.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8ZsAAAAASUVORK5CYII='));
        $url = '/product-media-pilot/'.$p->id.'/0';
        $this->assertSame([], ProductMediaPilot::images($p));
        $this->get($url)->assertNotFound();
        $this->app['env']='local';
        $this->assertCount(1, ProductMediaPilot::images($p));
        config(['storefront.inertia' => true]);
        $this->get('/catalog')->assertInertia(fn ($page) => $page
            ->component('catalog/index')
            ->where('products.data.0.id', $p->id)
            ->where('products.data.0.images', []));
        $this->get('/catalog/photo-preview')->assertInertia(fn ($page) => $page
            ->component('products/photo-preview')
            ->where('products.0.images', ProductMediaPilot::images($p)));
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->get('/product-media-pilot/'.$p->id.'/99')->assertNotFound();
        $p->update(['is_active'=>false]);
        $this->get($url)->assertNotFound();
        $p->update(['is_active'=>true,'sku'=>'OTHER']);
        $this->get($url)->assertNotFound();
    }
}
