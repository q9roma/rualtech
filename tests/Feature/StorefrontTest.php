<?php

namespace Tests\Feature;

use App\Models\{BlogPost, Page, Product, Service, ServiceCategory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
        $this->withoutVite();
    }

    public function test_russian_reading_time_and_block_description_are_readable(): void
    {
        $data = $this->content();
        $data['post']->update(['content' => '<p>'.str_repeat('оборудование ', 201).'</p>']);
        $this->assertSame(2, $data['post']->fresh()->reading_time);
        $data['post']->update(['content' => '<p>Короткая статья</p>']);
        $this->assertSame(1, $data['post']->fresh()->reading_time);
        $data['page']->update(['content' => '<h2>Доставка</h2><p>По согласованию</p>']);
        $this->get('/about')->assertInertia(fn (Assert $page) => $page
            ->where('description', 'Доставка По согласованию'));
    }

    public function test_home_catalog_only_exposes_active_products_and_filters_all_records(): void
    {
        Product::create(['name' => 'Скрытый сервер', 'category' => 'Серверы', 'is_active' => false]);
        $server = Product::create(['name' => 'Сервер', 'category' => 'Серверы', 'is_active' => true]);
        Product::create(['name' => 'Другой сервер', 'category' => 'Серверы', 'is_active' => true]);
        foreach (['Сеть', 'Компьютеры', 'ПО', 'Хранение'] as $category) {
            Product::create(['name' => $category, 'category' => $category, 'is_active' => true]);
        }
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('catalogProducts', 6)
            ->where('catalogProducts.0.id', $server->id)
            ->where('catalogProducts.0.formattedPrice', 'По запросу')
            ->where('catalogProducts.0.url', route('products.show', $server))
            ->missing('catalogProducts.0.is_active'));
        for ($i = 0; $i < 15; $i++) {
            Product::create(['name' => 'CPU '.$i, 'category' => 'Процессоры', 'is_active' => true]);
        }
        $this->get('/?category='.urlencode('Процессоры'))->assertInertia(fn (Assert $page) => $page
            ->has('catalogProducts', 12)->where('catalogTotal', 15)
            ->where('catalogFilter', 'Процессоры')->where('catalogProducts.0.category', 'Процессоры')
            ->where('canonicalUrl', route('home')));
        $this->get('/?category='.urlencode('Видеокарты'))->assertInertia(fn (Assert $page) => $page
            ->has('catalogProducts', 0)->where('catalogTotal', 0));

    }

    private function content(): array
    {
        $category = ServiceCategory::create(['name' => 'Серверы', 'slug' => 'servers', 'is_active' => true]);
        $service = Service::create(['service_category_id' => $category->id, 'name' => 'Сборка сервера',
            'slug' => 'build', 'is_active' => true, 'is_featured' => true,
            'short_description' => 'Сборка и настройка', 'description' => '<p>Описание услуги</p>', 'features' => ['Гарантия'], 'price_from' => 1000]);
        $post = BlogPost::create(['excerpt' => 'Краткое описание', 'title' => 'Статья', 'slug' => 'article', 'is_published' => true,
            'content' => '<p>Содержание статьи</p>', 'tags' => ['Серверы']]);
        $page = Page::create(['title' => 'О компании', 'slug' => 'about', 'is_active' => true,
            'content' => '<h2>Компания</h2><p style="color:green" onclick="alert(1)">Текст</p><script>alert(1)</script><a href="javascript:alert(1)">Ссылка</a>']);
        $product = Product::create(['name' => 'Сервер', 'sku' => 'SRV-1', 'is_active' => true, 'price' => 100, 'currency' => 'RUB']);
        return compact('category', 'service', 'post', 'page', 'product');
    }

    public function test_home_category_sections_include_subcategories_and_exclude_inactive_products(): void
    {
        $server = Product::create(['name' => 'Стоечный сервер', 'category' => 'Стоечные серверы', 'is_active' => true]);
        Product::create(['name' => 'Скрытый сервер', 'category' => 'Серверы', 'is_active' => false]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('catalogSections.0.name', 'Серверы')
            ->where('catalogSections.0.total', 1)
            ->has('catalogSections.0.products', 1)
            ->where('catalogSections.0.products.0.id', $server->id)
            ->has('catalogSections', 1));
    }

    public function test_every_public_page_renders_inertia_and_keeps_canonical_urls(): void
    {
        $data = $this->content();
        foreach ([
            '/' => 'home', '/services' => 'services/directions', '/services/servers' => 'services/category',
            '/services/servers/build' => 'services/show', '/blog' => 'blog/index',
            '/blog/article' => 'blog/show', '/about' => 'page', '/product/'.$data['product']->id => 'products/show',
        ] as $url => $component) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component($component)->where('canonicalUrl', url($url))
                ->has('navigation.pages', 1)->where('contactUrl', route('contact.order-service')));
        }
        $this->get('/about')->assertInertia(fn (Assert $page) => $page
            ->where('content', fn ($html) => str_contains($html, '<h2>Компания</h2>')
                && ! preg_match('/script|onclick|style=|javascript:/i', $html)));
        $this->get('/services/servers/build')->assertInertia(fn (Assert $page) => $page
            ->where('features.0', 'Гарантия')->missing('service.created_at'));
    }

    public function test_inactive_content_is_not_exposed_and_drafts_are_not_published(): void
    {
        $data = $this->content();
        $data['service']->update(['is_active' => false]);
        $data['post']->update(['is_published' => false]);
        $data['page']->update(['is_active' => false]);
        $data['product']->update(['is_active' => false]);
        foreach (['/services/servers/build', '/blog/article', '/about', '/product/'.$data['product']->id] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('services', 0)->has('pages', 0)->has('navigation.pages', 0));
        $this->get('/blog')->assertInertia(fn (Assert $page) => $page->has('posts.data', 0));
        $data['service']->update(['is_active' => true]);
        $data['category']->update(['is_active' => false]);
        $this->get('/services/servers')->assertNotFound();
        $this->get('/services/servers/build')->assertNotFound();
    }

    public function test_blog_and_services_pagination_and_future_publications(): void
    {
        $data = $this->content();
        for ($i = 0; $i < 12; $i++) {
            Service::create(['service_category_id' => $data['category']->id, 'name' => 'Услуга '.$i, 'slug' => 'service-'.$i, 'is_active' => true, 'short_description' => 'Кратко', 'description' => 'Описание']);
            BlogPost::create(['excerpt' => 'Краткое описание', 'title' => 'Статья '.$i, 'slug' => 'post-'.$i, 'is_published' => true, 'content' => '<p>Текст</p>']);
        }
        BlogPost::create(['excerpt' => 'Краткое описание', 'title' => 'Завтра', 'slug' => 'tomorrow', 'is_published' => true, 'published_at' => now()->addDay(), 'content' => 'Позже']);
        $this->get('/blog/tomorrow')->assertNotFound();
        $this->get('/blog?page=2')->assertInertia(fn (Assert $page) => $page
            ->has('posts.data', 4)->where('posts.total', 13)->where('canonicalUrl', url('/blog?page=2')));
        $this->get('/services/servers?page=2')->assertInertia(fn (Assert $page) => $page
            ->has('services.data', 1)->where('canonicalUrl', url('/services/servers?page=2')));
    }

    public function test_switch_restores_all_blade_routes(): void
    {
        $data = $this->content();
        config(['storefront.inertia' => false]);
        $bufferLevel = ob_get_level();
        foreach ([
            '/' => 'frontend.home', '/services' => 'frontend.services.index', '/services/servers' => 'frontend.services.category',
            '/services/servers/build' => 'frontend.services.show', '/blog' => 'frontend.blog.index',
            '/blog/article' => 'frontend.blog.show', '/about' => 'frontend.page',
            '/product/'.$data['product']->id => 'frontend.products.show',
        ] as $url => $view) { $this->get($url)->assertOk()->assertViewIs($view); $this->assertSame($bufferLevel, ob_get_level(), $url); }
    }

    public function test_request_form_validates_and_persists_without_sending_real_mail(): void
    {
        Mail::fake();
        $headers = ['X-Requested-With' => 'XMLHttpRequest'];
        $this->postJson('/contact/order-service', ['name' => 'A', 'email' => 'bad'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'service']);
        $this->postJson('/contact/order-service', ['name' => 'Тест', 'email' => 'test@example.com',
            'service' => 'SRV-1', 'message' => 'Уточните сроки поставки'], $headers)
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('contact_requests', ['email' => 'test@example.com', 'service' => 'SRV-1']);
        Mail::assertSent(\App\Mail\AdminContactNotification::class, 1);
    }
}

