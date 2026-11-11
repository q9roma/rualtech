<?php

namespace Tests\Feature;

use App\Models\{User, Product, ServiceCategory, Service, BlogPost, Page, ContactRequest};
use App\Notifications\AdminImportFinished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Gate, Storage};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReactAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
        return $user;
    }

    public function test_login_requires_staff_and_rotates_session_and_logout_invalidates_access(): void
    {
        $this->get('/admin/products')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertInertia(fn (Assert $p) => $p->component('Admin/Login'));
        $customer = User::factory()->create();
        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $customer->forceFill(['is_admin' => true])->save();
        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password', 'remember' => true])->assertRedirect('/admin/products');
        $this->assertAuthenticatedAs($customer);
        $this->get('/admin')->assertInertia(fn (Assert $p) => $p->component('Admin/Dashboard')->has('stats', 4)->has('series', 30));
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_every_resource_can_be_created_viewed_updated_and_deleted_without_mass_assignment(): void
    {
        $this->staff();
        $category = ServiceCategory::create(['name' => 'Серверы', 'slug' => 'servers', 'is_active' => true]);
        $cases = [
            ['products', Product::class, ['name' => 'Новый сервер', 'sku' => 'S1', 'price' => '2190.50', 'currency' => 'RUB', 'is_active' => true]],
            ['service-categories', ServiceCategory::class, ['name' => 'Сети', 'slug' => '', 'sort_order' => 2, 'is_active' => true]],
            ['services', Service::class, ['name' => 'Настройка', 'slug' => '', 'service_category_id' => (string) $category->id, 'short_description' => 'Кратко', 'description' => '<p>Полное описание</p>', 'price_type' => 'от', 'sort_order' => 0, 'features' => ['Гарантия'], 'is_active' => true, 'is_featured' => true]],
            ['blog-posts', BlogPost::class, ['title' => 'Новая статья', 'slug' => '', 'excerpt' => 'Анонс', 'content' => '<p>Текст</p>', 'tags' => ['Новости'], 'is_published' => true]],
            ['pages', Page::class, ['title' => 'О компании', 'slug' => '', 'content' => '<h2>Алтех</h2>', 'is_active' => true]],
            ['contact-requests', ContactRequest::class, ['name' => 'Клиент', 'email' => 'client@example.test', 'status' => 'new', 'message' => null, 'subject' => null, 'source' => null, 'admin_notes' => 'Заметка']],
        ];
        foreach ($cases as [$resource, $model, $data]) {
            $this->post('/admin/'.$resource, [...$data, 'id' => 999999, 'is_admin' => true])->assertSessionHasNoErrors()->assertRedirect();
            $record = $model::latest('id')->firstOrFail();
            $this->assertNotEquals(999999, $record->id);
            $this->get("/admin/{$resource}/{$record->id}")->assertInertia(fn (Assert $p) => $p->component('Admin/Form')->where('readOnly', true));
            $this->get("/admin/{$resource}/{$record->id}/edit")->assertInertia(fn (Assert $p) => $p->where('record.id', $record->id));
            $key = array_key_exists('name', $data) ? 'name' : 'title';
            $data[$key] .= ' изменено';
            if ($record->slug) $data['slug'] = $record->slug;
            $this->put("/admin/{$resource}/{$record->id}", $data)->assertSessionHasNoErrors();
            $this->assertSame($data[$key], $record->fresh()->$key);
            if ($resource === 'blog-posts') $this->assertNotNull($record->fresh()->published_at);
            $this->delete("/admin/{$resource}/{$record->id}")->assertRedirect('/admin/'.$resource);
            $this->assertNull($model::find($record->id));
        }
    }

    public function test_filters_pagination_column_sort_and_bulk_delete_preserve_scope(): void
    {
        $this->staff();
        for ($i = 0; $i < 15; $i++) Product::create(['name' => 'Сервер '.$i, 'brand' => 'Brand', 'category' => 'Серверы', 'price' => $i, 'is_active' => true]);
        $other = Product::create(['name' => 'Другой', 'brand' => 'Other', 'is_active' => false]);
        $this->get('/admin/products?brand=Brand&search=Сервер&sort=price&direction=desc&per_page=10')->assertInertia(fn (Assert $p) => $p->has('records.data', 10)->where('records.total', 15)->where('records.data.0.name', 'Сервер 14'));
        $this->get('/admin/products?is_active=0')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->where('records.data.0.id', $other->id));
        $ids = Product::where('brand', 'Brand')->limit(2)->pluck('id')->all();
        $this->post('/admin/products/bulk-delete', ['ids' => $ids])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 14);
        $this->assertNotNull($other->fresh());
        $this->get('/admin/products?sort=password&direction=invalid')->assertOk();
    }

    public function test_server_rechecks_policies_and_rejects_customers_on_mutation_endpoints(): void
    {
        $product = Product::create(['name' => 'Protected']);
        $this->actingAs(User::factory()->create());
        foreach (['products', 'services', 'service-categories', 'blog-posts', 'pages', 'contact-requests'] as $resource) {
            $this->post('/admin/'.$resource, ['name' => 'Attack'])->assertForbidden();
            $this->post('/admin/'.$resource.'/bulk-delete', ['ids' => [$product->id]])->assertForbidden();
        }
        $this->post('/admin/imports', [])->assertForbidden();
        $this->staff();
        Gate::policy(Product::class, DenyProductUpdates::class);
        $this->put('/admin/products/'.$product->id, ['name' => 'Denied'])->assertForbidden();
        $this->post('/admin/products/bulk-delete', ['ids' => [$product->id]])->assertForbidden();
        $this->assertSame('Protected', $product->fresh()->name);
    }

    public function test_validation_uploads_and_html_safety(): void
    {
        $this->staff();
        Storage::fake('public');
        $this->post('/admin/products', ['name' => '', 'price' => 'not-price'])->assertSessionHasErrors(['name', 'price']);
        $category = ['name' => 'Фото', 'slug' => 'photo', 'sort_order' => 0, 'is_active' => true];
        $this->post('/admin/service-categories', [...$category, 'image' => UploadedFile::fake()->image('cover.jpg', 800, 450)])->assertSessionHasNoErrors();
        $record = ServiceCategory::firstOrFail();
        Storage::disk('public')->assertExists($record->image);
        $this->put('/admin/service-categories/'.$record->id, [...$category, 'image' => '../secret.txt'])->assertSessionHasErrors('image');
        $this->post('/admin/pages', ['title' => 'HTML', 'slug' => 'html', 'content' => '<p>Safe</p><script>alert(1)</script><img src="x" onerror="alert(1)">', 'is_active' => true])->assertSessionHasNoErrors();
        $content = Page::firstOrFail()->content;
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->post('/admin/pages/attachments', ['file' => UploadedFile::fake()->image('inline.png')])->assertOk()->assertJsonStructure(['url', 'name', 'image']);
        $this->post('/admin/pages/attachments', ['file' => UploadedFile::fake()->create('attack.html', 1, 'text/html')])->assertSessionHasErrors('file');
    }

    public function test_existing_private_and_new_public_covers_remain_accessible_to_staff(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $record = ServiceCategory::create(['name' => 'Legacy', 'slug' => 'legacy', 'image' => 'categories/legacy.jpg', 'is_active' => true]);
        Storage::disk('local')->put($record->image, UploadedFile::fake()->image('legacy.jpg')->get());
        $url = '/admin/service-categories/'.$record->id.'/image/image';
        $this->get($url)->assertRedirect('/admin/login');
        $this->staff();
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/admin/service-categories/'.$record->id.'/image/slug')->assertNotFound();
        $record->update(['image' => 'categories/new.jpg']);
        Storage::disk('public')->put($record->image, UploadedFile::fake()->image('new.jpg')->get());
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_import_mapping_decimal_prices_distinct_skus_errors_and_ownership(): void
    {
        $user = $this->staff();
        Storage::fake('local');
        $csv = "Артикул;Наименование;Цена\nSAME;Первый;2 190,00 ₽\nSAME;Второй;1.234.567,89\nSAME;;19\nSAME;Первый;2191,50\n";
        $response = $this->post('/admin/imports', ['file' => UploadedFile::fake()->createWithContent('price.csv', $csv), 'delimiter' => ';'])->assertRedirect();
        $url = $response->headers->get('Location');
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('upload.mapping.name', '1')->where('upload.mapping.sku', '0'));
        $this->from($url)->post($url.'/run', ['mapping' => ['sku' => '0', 'name' => '1', 'price' => '2']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 2);
        $this->assertSame('2191.50', Product::where('name', 'Первый')->firstOrFail()->price);
        $this->assertSame('1234567.89', Product::where('name', 'Второй')->firstOrFail()->price);
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('upload.status', 'completed')->where('upload.successful', 3)->where('upload.failed', 1));
        $this->assertSame(1, $user->notifications()->count());
        $this->get($url.'/errors')->assertOk()->assertDownload('import-errors.csv');
        $this->post($url.'/run', ['mapping' => ['name' => '1']])->assertStatus(409);
        $this->staff();
        $this->get($url)->assertNotFound();
        $this->get($url.'/errors')->assertNotFound();
    }

    public function test_notifications_are_scoped_to_the_signed_in_staff_member(): void
    {
        $first = $this->staff();
        $first->notify(new AdminImportFinished('Готово', 'Тест'));
        $notification = $first->notifications()->firstOrFail();
        $this->staff();
        $this->get('/admin/notifications')->assertInertia(fn (Assert $p) => $p->has('notifications.data', 0));
        $this->post('/admin/notifications/'.$notification->id.'/read')->assertNotFound();
        $this->actingAs($first)->post('/admin/notifications/'.$notification->id.'/read')->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_rich_text_retains_safe_alignment_subscripts_and_tables_only(): void
    {
        $safe = \App\Admin\RichContent::sanitize('<h2 style="text-align:center;position:fixed">Title</h2><p style="background:url(https://invalid)">H<sub>2</sub>O <sup>2</sup></p><table><tbody><tr><td>Cell</td></tr></tbody></table>');
        $this->assertStringContainsString('text-align: center', $safe);
        $this->assertStringContainsString('<sub>2</sub>', $safe);
        $this->assertStringContainsString('<sup>2</sup>', $safe);
        $this->assertStringContainsString('<td>Cell</td>', $safe);
        $this->assertStringNotContainsString('position', $safe);
        $this->assertStringNotContainsString('background', $safe);
    }

    public function test_dashboard_counts_actual_lunar_orders_as_integers(): void
    {
        $this->staff();
        app(\App\Commerce\PrototypeSetup::class)->run();
        \Lunar\Models\Order::factory()->create(['placed_at' => now(), 'currency_code' => 'RUB']);
        $this->get('/admin?days=7')->assertInertia(fn (Assert $p) => $p->where('stats.2.value', 1)->where('series.6.orders', 1));
    }

    public function test_legacy_import_error_download_remains_owned_by_its_author(): void
    {
        $owner = $this->staff();
        $import = \Filament\Actions\Imports\Models\Import::create(['user_id' => $owner->id, 'file_name' => 'legacy.csv',
            'file_path' => 'unused.csv', 'importer' => \App\Filament\Imports\ProductImporter::class, 'total_rows' => 1, 'processed_rows' => 1, 'successful_rows' => 0]);
        $import->failedRows()->create(['data' => ['name' => '=HYPERLINK("https://invalid")'], 'validation_error' => 'Ошибка']);
        $url = '/admin/imports/legacy/'.$import->id.'/errors';
        $response = $this->get($url)->assertOk()->assertDownload('legacy-import-errors.csv');
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
        $this->staff();
        $this->get($url)->assertNotFound();
    }
}

class DenyProductUpdates extends \App\Policies\AdminResourcePolicy
{
    public function update(User $user, \Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public function delete(User $user, \Illuminate\Database\Eloquent\Model $record): bool { return false; }
}
