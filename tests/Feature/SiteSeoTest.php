<?php

namespace Tests\Feature;

use App\Models\{Page, Product, ProductCategory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
    }

    public function test_public_pages_have_server_content_and_cms_html_stays_sanitized(): void
    {
        Page::create(['title' => 'О компании', 'slug' => 'about', 'is_active' => true,
            'content' => '<h2>Наша команда</h2><script>alert(1)</script>']);
        foreach (['/', '/catalog', '/services', '/blog', '/delivery', '/about'] as $url) {
            $this->get($url)->assertOk()->assertSee('<h1>', false)
                ->assertSee('name="description"', false)->assertSee('rel="canonical"', false);
        }
        $this->get('/about')->assertSee('<h2>Наша команда</h2>', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_category_is_indexable_filters_are_not_and_pagination_is_crawlable(): void
    {
        $category = ProductCategory::create(['name' => 'Серверы', 'is_active' => true]);
        for ($i = 0; $i < 49; $i++) Product::create(['name' => 'Server '.$i, 'is_active' => true, 'catalog_category_id' => $category->id]);
        $url = route('catalog.category', $category->slug);
        $this->get('/catalog')->assertSee('<a href="'.$url.'">Серверы</a>', false);
        $this->get($url)->assertOk()->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('Следующая страница')->assertSee('Серверы');
        $this->get('/catalog?category='.urlencode($category->name))->assertSee('href="'.$url.'"', false);
        $this->get('/catalog?search=Server')->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get($url.'?page=2')->assertOk()->assertSee('страница 2');
        $this->get($url.'?page=3')->assertNotFound();
    }

    public function test_sitemap_and_robots_only_advertise_public_canonical_pages(): void
    {
        $category = ProductCategory::create(['name' => 'Сеть', 'is_active' => true]);
        $active = Product::create(['name' => 'Router', 'is_active' => true, 'catalog_category_id' => $category->id]);
        $draft = Product::create(['name' => 'Draft', 'is_active' => false]);
        $xml = $this->get('/sitemap.xml')->assertOk()->assertSee(route('catalog.category', $category->slug), false)
            ->assertSee(route('delivery'), false)->assertSee(route('products.show', $active), false)
            ->assertDontSee('<loc>'.route('products.show', $draft).'</loc>', false)->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringNotContainsString('<loc>'.route('home').'</loc><lastmod>', $xml);
        $this->get('/robots.txt')->assertOk()->assertSee('Clean-param:')->assertSee('Disallow: /cart');
        $this->get('/cart')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_category_guide_and_product_category_link_use_public_taxonomy(): void
    {
        $category = ProductCategory::create(['name' => 'SSD', 'is_active' => true]);
        $product = Product::create(['name' => 'Test SSD', 'is_active' => true, 'catalog_category_id' => $category->id]);
        $this->get(route('catalog.category', $category->slug))->assertOk()->assertSee('Как выбрать SSD');
        $this->get('/catalog?category=SSD&search=Test')->assertOk()->assertDontSee('Как выбрать SSD');
        $this->assertSame(route('catalog.category', $category->slug), \App\Support\Storefront::product($product)['categoryUrl']);
    }
}
