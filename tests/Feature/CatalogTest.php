<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
        $this->withoutVite();
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Коммутатор', 'sku' => 'SW-01', 'brand' => 'Altech',
            'category' => 'Сетевое оборудование', 'price' => '12500.00',
            'currency' => 'RUB', 'is_active' => true,
        ], $attributes));
    }

    public function test_live_search_returns_only_active_public_matches(): void
    {
        $this->product(['sku' => 'LIVE-01']);
        $this->product(['sku' => 'LIVE-hidden', 'is_active' => false]);
        $this->getJson('/catalog/suggestions?search=LIVE')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.sku', 'LIVE-01')
            ->assertJsonMissingPath('items.0.created_at');
        $this->getJson('/catalog/suggestions?search=L')->assertOk()->assertJsonCount(0, 'items');
        $this->getJson('/catalog/suggestions?search[]=bad')->assertUnprocessable();
    }

    public function test_price_brand_and_stock_filters_combine_and_keep_contextual_facets(): void
    {
        $this->product(['name' => 'Подходит', 'brand' => 'Alpha', 'price' => 150, 'availability' => '>10']);
        $this->product(['name' => 'Нет в наличии', 'brand' => 'Alpha', 'price' => 150, 'availability' => '0']);
        $this->product(['name' => 'Другой бренд', 'brand' => 'Beta', 'price' => 150, 'availability' => 'В наличии']);
        $this->product(['name' => 'Дороже', 'brand' => 'Alpha', 'price' => 300, 'availability' => '5']);
        $this->product(['name' => 'Без цены', 'brand' => 'Alpha', 'price' => null, 'availability' => '5']);
        $this->get('/catalog?price_min=100&price_max=200&brands[]=Alpha&in_stock=1')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)->where('products.data.0.name', 'Подходит')
                ->where('filters.brands', ['Alpha'])->where('filters.in_stock', true)
                ->has('facets.brands', 2));
        $this->get('/catalog?price_min=200&price_max=100&brands[]=Alpha&brands[]=Beta&in_stock=1')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
        $this->getJson('/catalog?price_min=-10&brands=Alpha')->assertUnprocessable();
    }

    public function test_facet_filters_survive_pagination(): void
    {
        for ($i = 0; $i < 49; $i++) {
            $this->product(['name' => sprintf('Filtered %02d', $i), 'brand' => 'Alpha', 'price' => 150, 'availability' => 'В наличии']);
        }
        $this->get('/catalog?price_min=100&price_max=200&brands[]=Alpha&in_stock=1&page=2')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 1)
                ->where('products.prev_page_url', fn ($url) => str_contains($url, 'price_min=100') && str_contains($url, 'price_max=200') && str_contains($url, 'Alpha') && str_contains($url, 'in_stock=1')));
    }

    public function test_catalog_filters_active_products_and_exposes_only_public_fields(): void
    {
        $this->product();
        $this->product(['name' => 'Скрытая позиция', 'is_active' => false]);
        $this->product(['name' => 'Монитор', 'category' => 'Мониторы', 'sku' => 'MON-01']);

        $this->get('/catalog?search=SW-01&category='.urlencode('Сетевое оборудование'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('catalog/index')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Коммутатор')
                ->where('products.data.0.formattedPrice', '12 500,00 ₽')
                ->missing('products.data.0.created_at')
                ->where('filters.search', 'SW-01')
                ->where('totalActiveProducts', 2)
                ->has('categories', 6));
    }

    public function test_sorting_places_unknown_prices_last_and_accepts_inertia_visits(): void
    {
        $this->product(['name' => 'Дороже', 'price' => '200.00']);
        $this->product(['name' => 'По запросу', 'price' => null]);
        $this->product(['name' => 'Дешевле', 'price' => '100.00']);

        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $this->get('/catalog?sort=price_asc', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? ''])
            ->assertOk()->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('props.products.data.0.name', 'Дешевле')
            ->assertJsonPath('props.products.data.2.name', 'По запросу');
    }

    public function test_pagination_preserves_filters_and_has_a_stable_order(): void
    {
        for ($i = 0; $i < 49; $i++) {
            $this->product(['name' => sprintf('Товар %02d', $i)]);
        }
        $this->get('/catalog?search=Товар&sort=name&page=2')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.current_page', 2)
                ->where('products.data.0.name', 'Товар 48')
                ->where('products.prev_page_url', fn ($url) => str_contains($url, 'sort=name') && str_contains($url, 'search=')));
    }

    public function test_infinite_scroll_exposes_next_batch_and_reset_metadata(): void
    {
        for ($i = 0; $i < 49; $i++) {
            $this->product(['name' => sprintf('Scroll %02d', $i), 'sku' => 'SC-'.$i]);
        }
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? ''];
        $this->get('/catalog?search=Scroll', $headers)->assertOk()
            ->assertJsonPath('scrollProps.products.nextPage', 2)
            ->assertJsonPath('scrollProps.products.currentPage', 1)
            ->assertJsonCount(48, 'props.products.data');
        $partial = array_merge($headers, [
            'X-Inertia-Partial-Component' => 'catalog/index',
            'X-Inertia-Partial-Data' => 'products',
            'X-Inertia-Infinite-Scroll-Merge-Intent' => 'append',
        ]);
        $this->get('/catalog?search=Scroll&page=2', $partial)->assertOk()
            ->assertJsonCount(1, 'props.products.data')
            ->assertJsonPath('props.products.data.0.sku', 'SC-48')
            ->assertJsonPath('scrollProps.products.nextPage', null)
            ->assertJsonPath('mergeProps.0', 'products.data');
        $this->get('/catalog?search=SC-01', array_merge($partial, ['X-Inertia-Reset' => 'products']))
            ->assertOk()->assertJsonMissingPath('mergeProps');
    }

    public function test_empty_results_and_invalid_query_parameters(): void
    {
        $this->get('/catalog?search=missing')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->getJson('/catalog?search[]=bad')->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson('/catalog?page=-1')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_blade_rollback_and_product_detail_still_work(): void
    {
        $product = $this->product();
        config(['storefront.inertia' => false]);
        $this->get('/catalog')->assertOk()->assertViewIs('frontend.products.index');
        $this->get('/product/'.$product->id)->assertOk()->assertViewIs('frontend.products.show');
        $product->update(['is_active' => false]);
        $this->get('/product/'.$product->id)->assertNotFound();
    }
    public function test_core_categories_include_empty_sections_and_count_only_active_products(): void
    {
        $this->product(['category' => 'Серверы']);
        $this->product(['category' => 'Серверы', 'is_active' => false]);
        $this->product(['category' => 'Программное обеспечение', 'sku' => 'SOFT-01']);

        $this->get('/catalog?category='.urlencode('Компьютеры'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('navigation.catalogCategories', 5)
                ->where('navigation.catalogCategories.0.name', 'Серверы')
                ->where('navigation.catalogCategories.0.count', 1)
                ->where('navigation.catalogCategories.1.name', 'Компьютеры')
                ->where('navigation.catalogCategories.1.count', 0)
                ->where('navigation.catalogCategories.4.count', 1)
                ->where('categories.1.category', 'Компьютеры')
                ->where('filters.category', 'Компьютеры')
                ->has('products.data', 0));
    }
    public function test_parent_category_includes_children_and_child_filter_is_exact(): void
    {
        $this->product(['category' => 'Серверы']);
        $this->product(['category' => 'Стоечные серверы', 'sku' => 'RACK-01']);
        $this->product(['category' => 'Стоечные серверы', 'is_active' => false]);
        $this->get('/catalog?category='.urlencode('Серверы'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 2)
                ->where('navigation.catalogCategories.0.count', 2)
                ->where('navigation.catalogCategories.0.children.0.name', 'Стоечные серверы')
                ->has('navigation.catalogCategories', 5));
        $this->get('/catalog?category='.urlencode('Стоечные серверы'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)
                ->where('products.data.0.sku', 'RACK-01'));
    }

    public function test_supply_variants_are_independent_and_recommendations_stay_in_category(): void
    {
        $current = $this->product(['name' => 'Система формат поставки — BOX']);
        $variant = $this->product(['name' => 'Система формат поставки — Электронная лицензия']);
        $this->product(['name' => 'Система формат поставки — Скрытый', 'is_active' => false]);
        $this->product(['name' => 'Чужая категория', 'category' => 'Мониторы']);
        $this->product(['name' => 'Аналог', 'brand' => 'Другой']);
        for ($i = 0; $i < 7; $i++) {
            $this->product(['name' => 'Альтернатива '.$i]);
        }
        $this->get('/product/'.$current->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('variants', 1)->where('variants.0.id', $variant->id)
            ->has('related', 6)->where('related.0.name', 'Альтернатива 0')
            ->where('related', fn ($items) => collect($items)->every(fn ($item) => $item['category'] === 'Сетевое оборудование' && $item['brand'] === 'Altech')));
    }

    public function test_recommendations_do_not_fill_with_other_categories_and_zero_price_is_unknown(): void
    {
        $product = $this->product(['price' => 0, 'category' => null]);
        $this->product(['name' => 'Другой товар']);
        $this->get('/product/'.$product->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('related', 0)->has('variants', 0)
            ->where('product.hasPrice', false)->where('product.formattedPrice', 'По запросу')
            ->where('product.canAddToCart', false));
    }

    public function test_small_recommendation_set_is_not_padded_and_purchase_fields_are_shared(): void
    {
        config(['commerce.enabled' => true]);
        $product = $this->product(['name' => 'Основной товар', 'availability' => 'В наличии']);
        $related = $this->product(['name' => 'Аналог', 'brand' => 'Другой', 'availability' => 'В наличии']);
        $this->product(['name' => 'Монитор того же бренда', 'category' => 'Мониторы']);
        $this->get('/product/'.$product->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('related', 1)->where('related.0.id', $related->id)
            ->where('product.hasPrice', true)->where('product.canAddToCart', true)
            ->where('related.0.hasPrice', true)->where('related.0.canAddToCart', true)
            ->where('related.0.addToCartUrl', route('cart.add', $related)));
        $this->get('/catalog?search=Основной')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.hasPrice', true)->where('products.data.0.canAddToCart', true)
            ->where('products.data.0.addToCartUrl', route('cart.add', $product)));
    }

    public function test_search_matches_words_across_fields_and_prioritizes_exact_sku(): void
    {
        $exact = $this->product(['name' => 'Switch Pro', 'brand' => 'Acme', 'sku' => 'AC-10']);
        $this->product(['name' => 'A cable AC-10', 'sku' => 'CABLE']);
        $this->product(['name' => 'Switch Pro', 'brand' => 'Other', 'sku' => 'OTHER']);
        $this->getJson('/catalog/suggestions?search=AC-10')->assertOk()
            ->assertJsonPath('items.0.id', $exact->id)->assertJsonPath('total', 2);
        $this->getJson('/catalog/suggestions?search=Acme%20%20Pro')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $exact->id);
        $this->get('/catalog?search=Pro%20Acme')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.id', $exact->id)->where('filters.sort', 'relevance'));
    }

    public function test_suggestion_count_is_not_limited_and_wildcards_are_literal(): void
    {
        for ($i = 0; $i < 8; $i++) $this->product(['name' => 'Match '.$i]);
        $this->product(['name' => 'Match hidden', 'is_active' => false]);
        $this->getJson('/catalog/suggestions?search=Match')->assertOk()->assertJsonCount(6, 'items')->assertJsonPath('total', 8);
        $literal = $this->product(['name' => 'Discount 50%_off']);
        $this->product(['name' => 'Discount 500Xoff']);
        $this->getJson('/catalog/suggestions?search='.urlencode('50%_'))->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $literal->id);
    }
}
