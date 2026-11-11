<?php

namespace Tests\Feature;

use App\Models\{Product, ProductCategory};
use App\Support\ServiceDirections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceDirectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
        $this->withoutVite();
    }

    public function test_directions_work_without_cms_records_and_are_in_sitemap(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('directions', 8));
        $this->get('/services')->assertInertia(fn (Assert $page) => $page->component('services/directions')->has('directions', 8));
        foreach (array_keys(config('directions')) as $slug) {
            $this->get('/services/'.$slug)->assertOk()->assertSee('rel="canonical"', false)->assertSee('<h1>', false)->assertInertia(fn (Assert $page) => $page->component('services/direction'));
            $this->get('/sitemap.xml')->assertSee('/services/'.$slug, false);
        }
        foreach (ServiceDirections::ALIASES as $old => $new) {
            $this->get('/services/'.$old)->assertStatus(301)->assertRedirect('/services/'.$new);
        }
        $this->get('/services/not-a-direction')->assertNotFound();
        $this->get('/services/workplaces?collection=missing')->assertNotFound();
    }

    public function test_server_platforms_are_separate_from_parts_and_drafts(): void
    {
        $platform = Product::create(['name' => 'Серверная платформа Supermicro', 'category' => 'Серверные комплектующие', 'is_active' => true]);
        $part = Product::create(['name' => 'Процессор Intel Xeon', 'category' => 'Серверные комплектующие', 'is_active' => true]);
        Product::create(['name' => 'Серверная платформа черновик', 'category' => 'Серверные комплектующие', 'is_active' => false]);
        Product::create(['name' => 'Корпус ПК', 'category' => 'Корпуса', 'is_active' => true]);
        $this->get('/services/servers-storage')->assertInertia(fn (Assert $page) => $page
            ->where('sections.0.total', 1)->where('sections.0.products.0.id', $platform->id)
            ->where('sections.3.total', 1)->where('sections.3.products.0.id', $part->id));
    }

    public function test_assigned_category_overrides_legacy_text_and_full_collection_paginates(): void
    {
        $category = ProductCategory::create(['name' => 'Ноутбуки', 'slug' => 'laptops', 'is_active' => true]);
        $other = ProductCategory::create(['name' => 'Гарнитуры', 'slug' => 'headsets', 'is_active' => true]);
        for ($i = 0; $i < 26; $i++) Product::create(['name' => 'Ноутбук '.sprintf('%02d', $i), 'catalog_category_id' => $category->id, 'category' => 'Старый раздел', 'is_active' => true]);
        Product::create(['name' => 'Посторонний товар', 'catalog_category_id' => $other->id, 'category' => 'Ноутбуки', 'is_active' => true]);
        $this->get('/services/workplaces')->assertInertia(fn (Assert $page) => $page->where('sections.0.total', 26)->has('sections.0.products', 4));
        $this->get('/services/workplaces?collection=computers')->assertInertia(fn (Assert $page) => $page
            ->has('sections.0.products', 24)->where('sections.0.pagination.last_page', 2)
            ->where('sections.0.pagination.next_page_url', fn ($url) => str_contains($url, 'collection=computers') && str_contains($url, 'page=2')));
        $this->get('/services/workplaces?collection=computers&page=2')->assertInertia(fn (Assert $page) => $page->has('sections.0.products', 2));
        $this->get('/services/workplaces?collection=computers&page=3')->assertNotFound();
    }

    public function test_software_and_meeting_accessories_are_not_mixed(): void
    {
        Product::create(['name' => 'Windows 11 Pro', 'category' => 'Программное обеспечение', 'is_active' => true]);
        Product::create(['name' => 'Другое ПО', 'category' => 'Программное обеспечение', 'is_active' => true]);
        Product::create(['name' => 'Видеотерминал ICU', 'category' => 'Оборудование видеоконференций', 'is_active' => true]);
        Product::create(['name' => 'Консоль управления', 'category' => 'Оборудование видеоконференций', 'is_active' => true]);
        $this->get('/services/software')->assertInertia(fn (Assert $page) => $page->where('sections.0.total', 1)->where('sections.0.products.0.name', 'Windows 11 Pro'));
        $this->get('/services/meeting-rooms')->assertInertia(fn (Assert $page) => $page->where('sections.0.total', 1)->where('sections.1.total', 1));
    }

    public function test_preview_represents_different_types_and_excludes_unrelated_products(): void
    {
        for ($i = 0; $i < 8; $i++) Product::create(['name' => 'Моноблок '.$i, 'category' => 'Моноблоки', 'is_active' => true]);
        $laptop = Product::create(['name' => 'Ноутбук', 'category' => 'Ноутбуки', 'is_active' => true]);
        $desktop = Product::create(['name' => 'ПК', 'category' => 'Настольные компьютеры', 'is_active' => true]);
        $this->get('/services/workplaces')->assertInertia(fn (Assert $page) => $page
            ->where('sections.0.total', 10)->has('sections.0.products', 4)
            ->where('sections.0.products.0.id', $laptop->id)->where('sections.0.products.1.id', $desktop->id));
        Product::create(['name' => 'Мобильная стойка Hisense', 'category' => 'Интерактивные панели', 'is_active' => true]);
        Product::create(['name' => 'Гарнитура игровая Logitech G435', 'category' => 'Гарнитуры', 'is_active' => true]);
        $this->get('/services/meeting-rooms')->assertInertia(fn (Assert $page) => $page->where('sections.2.total', 0)->where('sections.4.total', 0));
    }
}
