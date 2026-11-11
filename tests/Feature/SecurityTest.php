<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\BlogPost;
use App\Models\ContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Middleware\TrustProxies;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_panel_or_resource_routes(): void
    {
        $customer = User::factory()->create();
        $this->assertFalse($customer->canAccessAdmin());
        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->get('/admin/products')->assertForbidden();
        $this->get('/admin/products/create')->assertForbidden();
    }

    public function test_staff_flag_cannot_be_mass_assigned_and_access_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $user->fill(['is_admin' => true])->save();
        $this->assertFalse($user->refresh()->is_admin);
        $this->artisan('admin:access', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->refresh()->canAccessAdmin());
        $this->actingAs($user)->get('/admin/products')->assertOk();
        $this->artisan('admin:access', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->actingAs($user->refresh())->get('/admin/products')->assertForbidden();
    }

    public function test_all_business_resources_have_staff_only_policies(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();
        $staff->is_admin = true;
        $staff->save();
        foreach ([Product::class, Page::class, Service::class, ServiceCategory::class, BlogPost::class, ContactRequest::class] as $model) {
            foreach (['viewAny', 'create', 'deleteAny', 'forceDeleteAny'] as $ability) {
                $this->assertFalse(Gate::forUser($customer)->allows($ability, $model));
                $this->assertTrue(Gate::forUser($staff)->allows($ability, $model));
            }
            foreach (['view', 'update', 'delete'] as $ability) {
                $this->assertFalse(Gate::forUser($customer)->allows($ability, new $model));
                $this->assertTrue(Gate::forUser($staff)->allows($ability, new $model));
            }
        }
    }

    public function test_debug_login_is_unavailable_and_does_not_authenticate(): void
    {
        $this->get('/test-login')->assertNotFound();
        $this->post('/test-login', ['email' => 'nobody@example.com', 'password' => 'password'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_untrusted_proxy_headers_cannot_spoof_client_ip_or_scheme(): void
    {
        config(['security.trusted_proxies' => []]);
        $request = Request::create('http://localhost/catalog', server: [
            'REMOTE_ADDR' => '203.0.113.20', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_HOST' => 'attacker.invalid',
        ]);
        (new TrustProxies)->handle($request, function (Request $request) {
            $this->assertSame('203.0.113.20', $request->ip());
            $this->assertFalse($request->isSecure());
            $this->assertSame('localhost', $request->host());
            return response('ok');
        });
    }

    public function test_contact_endpoint_limits_repeated_requests(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/contact', [], ['X-Requested-With' => 'XMLHttpRequest'])->assertUnprocessable();
        }
        $this->postJson('/contact', [], ['X-Requested-With' => 'XMLHttpRequest'])->assertTooManyRequests();
    }

    public function test_production_rejects_an_unconfigured_host(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->get('http://attacker.invalid/ready')->assertStatus(400);
    }

    public function test_readiness_hides_database_errors(): void
    {
        $this->get('/ready')->assertOk()->assertJsonPath('status', 'ok');
        DB::shouldReceive('select')->once()->with('SELECT 1')->andThrow(new \RuntimeException('secret connection details'));
        $this->get('/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }

    public function test_deployment_check_rejects_local_configuration(): void
    {
        $this->artisan('deploy:check')->assertFailed();
    }

    public function test_admin_creation_uses_a_hidden_validated_password_and_grants_only_new_account(): void
    {
        $this->artisan('admin:create', ['name' => 'Staff', 'email' => 'staff@example.com'])
            ->expectsQuestion('Password (at least 12 characters)', 'OnlyTest-Password42!')
            ->expectsQuestion('Repeat password', 'OnlyTest-Password42!')
            ->assertSuccessful();
        $user = User::where('email', 'staff@example.com')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('OnlyTest-Password42!', $user->password));
        $this->artisan('admin:create', ['name' => 'Duplicate', 'email' => 'staff@example.com'])->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_staff_can_open_all_resource_lists_and_create_forms_after_upgrade(): void
    {
        $staff = User::factory()->create();
        $staff->is_admin = true;
        $staff->save();
        $this->actingAs($staff);
        foreach (['products', 'pages', 'services', 'service-categories', 'blog-posts', 'contact-requests'] as $resource) {
            $this->get('/admin/'.$resource)->assertOk();
            $this->get('/admin/'.$resource.'/create')->assertOk();
        }
    }
}
