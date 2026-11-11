<?php

namespace Tests\Feature;

use App\Admin\CatalogDraftImport;
use App\Models\{Product, ProductCategory, CatalogImportMapping, User};
use App\Support\{CatalogDirectory, CatalogTaxonomy};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogDraftImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
        $this->withoutVite();
    }

    private function row(array $overrides = []): array
    {
        return array_replace(['row' => 2, 'brand' => 'HP', 'sku' => 'TEST-1', 'name' => 'Ноутбук HP',
            'source_category' => 'IT', 'status' => 'include', 'rule' => 'laptop',
            'section' => 'Компьютеры и ноутбуки', 'subcategory' => 'Ноутбуки', 'errors' => [],
            'stock' => ['>50', '0', ''], 'price' => '100.00', 'currency' => 'RUB'], $overrides);
    }

    private function runImport(array $rows): array
    {
        return app(CatalogDraftImport::class)->run($rows, 'test-source', hash('sha256', json_encode($rows)), 'test-v1');
    }

    public function test_taxonomy_has_stable_ids_and_manual_names_survive_seeding(): void
    {
        CatalogTaxonomy::seed();
        $this->assertSame(8, ProductCategory::whereNull('parent_id')->count());
        $this->assertSame(64, ProductCategory::whereNotNull('parent_id')->count());
        $leaf = ProductCategory::where('name', 'Ноутбуки')->firstOrFail();
        $id = $leaf->id; $slug = $leaf->slug;
        $leaf->update(['name' => 'Ноутбуки для организаций']);
        CatalogTaxonomy::seed();
        $this->assertSame($id, CatalogTaxonomy::categoryFor('Компьютеры и ноутбуки', 'Ноутбуки')->id);
        $this->assertSame($slug, $leaf->fresh()->slug);
        $this->assertSame('Ноутбуки для организаций', $leaf->fresh()->name);
    }

    public function test_import_creates_only_drafts_and_repeat_preserves_manual_edits(): void
    {
        $this->assertSame(1, $this->runImport([$this->row()])['created_drafts']);
        $p = Product::firstOrFail();
        $this->assertFalse($p->is_active);
        $this->assertSame('0', $p->stock_free);
        $this->assertSame('>50', $p->availability);
        $this->assertNull($p->stock_transit);
        $p->update(['name' => 'Отредактировано вручную', 'price' => 150]);
        $before = $p->fresh()->getAttributes();
        $second = $this->runImport([$this->row(['price' => '200.00'])]);
        $this->assertSame(0, $second['created_drafts']);
        $this->assertSame(1, $second['existing_untouched']);
        $this->assertSame($before, $p->fresh()->getAttributes());
        $this->assertSame(1, Product::count());
        $this->assertDatabaseCount('catalog_import_rows', 2);
    }

    public function test_existing_published_products_are_not_changed(): void
    {
        $p = Product::create(['name' => 'Ноутбук HP', 'sku' => 'TEST-1', 'brand' => 'HP', 'category' => 'Старая', 'price' => 999, 'is_active' => true]);
        $before = $p->fresh()->getAttributes();
        $result = $this->runImport([$this->row()]);
        $this->assertSame(1, $result['existing_untouched']);
        $this->assertSame($before, $p->fresh()->getAttributes());
    }

    public function test_duplicates_and_bad_identity_are_held_and_excluded_rows_are_audited(): void
    {
        $result = $this->runImport([$this->row(), $this->row(['row' => 3]),
            $this->row(['row' => 4, 'sku' => '', 'errors' => ['missing_pn']]),
            $this->row(['row' => 5, 'sku' => 'X', 'rule' => 'excluded-category', 'status' => 'exclude', 'section' => '', 'subcategory' => ''])]);
        $this->assertSame(0, $result['created_drafts']);
        $this->assertSame(3, $result['held']);
        $this->assertSame(1, $result['excluded']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('catalog_import_rows', 4);
    }

    public function test_manual_mapping_overrides_are_not_overwritten(): void
    {
        $this->runImport([$this->row()]);
        $mapping = CatalogImportMapping::firstOrFail();
        $mapping->update(['decision' => 'exclude', 'manual_locked' => true]);
        $result = $this->runImport([$this->row(['sku' => 'TEST-2'])]);
        $this->assertSame(1, $result['excluded']);
        $this->assertSame('exclude', $mapping->fresh()->decision);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_drafts_are_absent_from_catalog_suggestions_navigation_and_product_page(): void
    {
        $this->runImport([$this->row()]);
        $this->assertCount(0, CatalogDirectory::categories());
        $menu = CatalogDirectory::categories(includeEmpty: true);
        $this->assertCount(8, $menu);
        $this->assertSame(64, $menu->sum(fn ($root) => $root['children']->count()));
        $this->assertSame(0, $menu->sum('products_count'));
        $this->get('/catalog')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('products.total', 0)->has('navigation.catalogCategories', 8));
        $this->getJson('/catalog/suggestions?search=TEST')->assertOk()->assertJsonCount(0, 'items');
        $this->get('/product/'.Product::first()->id)->assertNotFound();
    }

    public function test_category_urls_and_parent_filter_work_after_explicit_publication(): void
    {
        $this->runImport([$this->row()]);
        $p = Product::first(); $p->update(['is_active' => true]);
        $root = $p->catalogCategory->parent;
        $this->get('/catalog/category/'.$root->slug)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('products.total', 1));
        $this->get('/catalog/category/'.$p->catalogCategory->slug)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('products.total', 1));
        $this->assertCount(1, CatalogDirectory::categories());
    }

    public function test_admin_can_review_categories_and_drafts_but_customer_cannot(): void
    {
        $this->runImport([$this->row()]);
        $staff = User::factory()->create(['is_admin' => true]);
        $this->actingAs($staff)->get('/admin/product-categories')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('records.total', 72));
        $this->get('/admin/catalog-mappings')->assertOk();
        $this->get('/admin/products?is_active=0')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('records.total', 1));
        $customer = User::factory()->create(['is_admin' => false]);
        $this->actingAs($customer)->get('/admin/product-categories')->assertForbidden();
    }

    public function test_category_cycle_and_deletion_of_used_category_are_rejected(): void
    {
        $this->runImport([$this->row()]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $leaf = Product::first()->catalogCategory;
        $this->delete('/admin/product-categories/'.$leaf->id)->assertForbidden();
        $root = $leaf->parent;
        $this->put('/admin/product-categories/'.$root->id, ['name' => $root->name, 'parent_id' => (string) $root->id, 'sort_order' => 0, 'is_active' => true])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_sync_updates_only_price_and_stock_and_repeat_is_idempotent(): void
    {
        $this->runImport([$this->row()]);
        $product = Product::firstOrFail();
        $product->update(['name' => 'Редакторский заголовок', 'category' => 'Ручная категория', 'is_active' => true]);
        $before = $product->fresh()->getAttributes();
        $rows = [$this->row(['price' => '250.50', 'stock' => ['0', '>10', ''],
            'errors' => ['title_changed_verify_identity'], 'status' => 'review'])];
        $sync = fn ($rows) => app(CatalogDraftImport::class)->run($rows, 'test-source', hash('sha256', json_encode($rows)), 'test-v1', true);
        $this->assertSame(1, $sync($rows)['updated']);
        $after = $product->fresh()->getAttributes();
        foreach ($before as $field => $value) {
            if (! in_array($field, ['price', 'currency', 'availability', 'stock_free', 'stock_transit', 'updated_at'])) {
                $this->assertSame($value, $after[$field], $field);
            }
        }
        $this->assertSame('250.50', $product->fresh()->price);
        $this->assertSame('0', $product->fresh()->availability);
        $this->assertSame('>10', $product->fresh()->stock_free);
        $this->assertNull($product->fresh()->stock_transit);
        $audit = json_decode(DB::table('catalog_import_rows')->orderByDesc('id')->value('payload'), true);
        $this->assertSame('100.00', $audit['changes']['price']['before']);
        $this->assertSame(0, $sync($rows)['updated']);
        $this->assertDatabaseCount('products', 1);
        $this->assertSame($after, $product->fresh()->getAttributes());
    }

    public function test_sync_holds_duplicates_and_invalid_stock_and_preserves_absent_products(): void
    {
        $this->runImport([$this->row()]);
        $before = Product::first()->getAttributes();
        $sync = fn ($rows) => app(CatalogDraftImport::class)->run($rows, 'test-source', hash('sha256', json_encode($rows)), 'test-v1', true);
        $this->assertSame(2, $sync([$this->row(), $this->row(['row' => 3, 'price' => '300'])])['held']);
        $this->assertSame(1, $sync([$this->row(['stock' => ['unknown', '0', '']])])['held']);
        $this->assertSame(1, $sync([$this->row(['sku' => 'NEW'])])['created_drafts']);
        $this->assertSame($before, Product::first()->getAttributes());
        $this->assertFalse(Product::orderByDesc('id')->first()->is_active);
    }

    public function test_validation_failure_leaves_no_partial_import(): void
    {
        try { $this->runImport([$this->row(), $this->row(['row' => 3, 'price' => '-10'])]); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('catalog_import_batches', 0);
    }

    public function test_supplier_id_survives_title_changes_and_keeps_editorial_fields(): void
    {
        $row = [...$this->row(), 'supplier_id' => '0E-123', 'supplier_data' => ['EAN' => '1234567890123', 'Описание' => 'Описание']];
        $run = fn ($rows) => app(CatalogDraftImport::class)->run($rows, 'supplier-price-32', hash('sha256', json_encode($rows)), 'supplier-test', true);
        $this->assertSame(1, $run([$row])['created_drafts']);
        $p = Product::first();
        $p->update(['name' => 'Ручной заголовок', 'category' => 'Ручная категория']);
        $before = $p->fresh()->getAttributes();
        $row['name'] = 'Ноутбук HP новое описание поставщика';
        $row['price'] = '150';
        $this->assertSame(1, $run([$row])['updated']);
        $this->assertSame('Ручной заголовок', $p->fresh()->name);
        $this->assertSame($before['catalog_category_id'], $p->fresh()->catalog_category_id);
        $this->assertSame('Ручная категория', $p->fresh()->category);
        $this->assertSame(0, $run([$row])['updated']);
        $this->assertDatabaseCount('products', 1);
        $link = DB::table('catalog_source_products')->first();
        $this->assertSame('0E-123', $link->supplier_id);
        $this->assertSame($row['supplier_data'], json_decode($link->supplier_data, true));
        $this->assertSame(2, $run([$row, [...$row, 'row' => 3]])['held']);
        $this->assertSame(1, $run([[...$row, 'supplier_id' => '']])['held']);
    }
}
