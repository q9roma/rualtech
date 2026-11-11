<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ProductSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_content_and_metadata_are_available_without_javascript(): void
    {
        $this->withoutVite();
        config(['storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
        $product = Product::create(['name' => 'Сервер Test', 'description' => 'Для виртуализации',
            'specifications' => [['name' => 'Память', 'value' => '64 ГБ']],
            'sku' => 'TEST-1', 'price' => 1200, 'currency' => 'RUB', 'availability' => 'В наличии', 'is_active' => true]);
        $response = $this->get(route('products.show', $product))->assertOk();
        $response->assertSee('<h1>Сервер Test</h1>', false)
            ->assertSee('<dd>64 ГБ</dd>', false)
            ->assertSee('>Для виртуализации</p>', false)
            ->assertSee('name="description" content="Сервер Test. Для виртуализации"', false)
            ->assertSee('type="application/ld+json"', false);
        $schema = ProductSeo::metadata($product)['structuredData'];
        $this->assertEquals(1200, $schema['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        config(['storefront.inertia' => false]);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Для виртуализации')->assertSee('64 ГБ');
    }

    public function test_unknown_price_and_stock_are_not_fabricated_and_drafts_stay_private(): void
    {
        $this->withoutVite();
        $product = Product::create(['name' => 'Черновик', 'is_active' => false]);
        $this->assertArrayNotHasKey('offers', ProductSeo::metadata($product)['structuredData']);
        $this->get(route('products.show', $product))->assertNotFound();
        $product->update(['price' => 100, 'currency' => 'RUB', 'availability' => 'Уточняйте']);
        $this->assertArrayNotHasKey('availability', ProductSeo::metadata($product)['structuredData']['offers']);
    }
}
