<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
        $this->withoutVite();
    }

    public function test_collection_combines_categories_and_scopes_facets_and_filters(): void
    {
        $computer = Product::create(['name' => 'Компьютер', 'category' => 'Ноутбуки', 'brand' => 'Acer', 'price' => 50000, 'is_active' => true]);
        Product::create(['name' => 'Монитор', 'category' => 'Мониторы', 'brand' => 'Dell', 'price' => 20000, 'is_active' => true]);
        Product::create(['name' => 'Сервер', 'category' => 'Серверы', 'brand' => 'Other', 'price' => 100000, 'is_active' => true]);
        Product::create(['name' => 'Скрытый монитор', 'category' => 'Мониторы', 'brand' => 'Hidden', 'is_active' => false]);
        $this->get('/catalog?collection=workplace')->assertInertia(fn (Assert $page) => $page
            ->where('collection.name', 'Рабочее место')->where('filters.collection', 'workplace')
            ->where('products.total', 2)->has('facets.brands', 2)->has('collection.categoryFilters', 5));
        $this->get('/catalog?collection=workplace&collection_categories[]=peripherals-monitory')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)->where('products.data.0.brand', 'Dell')
            ->has('collection.categoryFilters', 1)->where('collection.categoryFilters.0.label', 'Мониторы')
            ->where('filters.collection_categories', ['peripherals-monitory']));
        $this->get('/catalog?collection=workplace&brands[]=Acer&price_min=30000')->assertInertia(fn (Assert $page) => $page
            ->where('filters.collection', 'workplace')->where('products.total', 1)->where('products.data.0.id', $computer->id));
        $this->get('/catalog?collection=workplace&category='.urlencode('Серверы'))->assertInertia(fn (Assert $page) => $page
            ->where('collection', null)->where('filters.collection', '')->where('products.total', 1)->where('products.data.0.brand', 'Other'));
        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page->where('collection', null)->where('products.total', 3));
    }

    public function test_collection_uses_stable_codes_and_preserves_pagination(): void
    {
        $root = ProductCategory::create(['code' => 'computers', 'slug' => 'renamed', 'name' => 'Переименованные компьютеры', 'is_active' => true]);
        $child = ProductCategory::create(['code' => 'computers-noutbuki', 'slug' => 'laptops', 'name' => 'Переименованные ноутбуки', 'parent_id' => $root->id, 'is_active' => true]);
        for ($i = 0; $i < 49; $i++) Product::create(['name' => 'Ноутбук '.$i, 'catalog_category_id' => $child->id, 'is_active' => true]);
        $this->get('/catalog?collection=workplace')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 49)->has('products.data', 48)
            ->where('products.next_page_url', fn ($url) => str_contains($url, 'collection=workplace')));
        $this->get('/catalog?collection=workplace&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('filters.collection', 'workplace')->has('products.data', 1));
    }
}
