<?php

namespace Tests\Feature;

use App\Commerce\{OrderWorkflow, PrototypeSetup};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lunar\Models\{Order, Country};
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        app(PrototypeSetup::class)->run();
        $order = Order::factory()->create(['currency_code' => 'RUB', 'placed_at' => now(),
            'status' => 'prototype-pending-confirmation', 'meta' => ['prototype' => true]]);
        \Lunar\Models\OrderLine::factory()->create(['order_id' => $order->id, 'purchasable_id' => 999,
            'description' => 'Снимок товара', 'meta' => ['sku' => 'TEST-SKU'], 'unit_price' => 123456,
            'quantity' => 2, 'total' => 246912]);
        $order->addresses()->create(['type' => 'billing', 'first_name' => 'Тест', 'last_name' => 'Покупатель',
            'contact_email' => 'buyer@example.test', 'contact_phone' => '+70000000000', 'city' => 'Москва',
            'line_one' => 'Тестовая, 1', 'postcode' => '000000', 'country_id' => Country::where('iso2', 'RU')->firstOrFail()->id]);
        return $order;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
        return $user;
    }

    public function test_staff_can_view_contacts_filter_orders_and_change_status_with_audit(): void
    {
        $this->withoutVite();
        $order = $this->order();
        $staff = $this->staff();
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('order.contact.contact_email', 'buyer@example.test')->where('order.contact.contact_phone', '+70000000000')
            ->where('order.lines.0.description', 'Снимок товара')->where('order.lines.0.sku', 'TEST-SKU'));
        $this->get('/admin/orders')->assertInertia(fn ($page) => $page->has('records.data', 1));
        $this->get('/admin/orders?status=prototype-completed')->assertInertia(fn ($page) => $page->has('records.data', 0));
        $this->from('/admin/orders/'.$order->id)->post('/admin/orders/'.$order->id.'/status',
            ['expected' => $order->status, 'status' => 'prototype-confirmed', 'reason' => 'Согласовано'])->assertSessionHasNoErrors();
        $this->get('/admin/orders/'.$order->id)->assertInertia(fn ($page) => $page->where('order.history.0.reason', 'Согласовано'));
        $this->assertSame('prototype-confirmed', $order->fresh()->status);
        $log = $order->activities()->where('log_name', 'order-status')->sole();
        $this->assertEquals($staff->id, $log->causer_id);
        $this->assertSame('prototype-pending-confirmation', $log->properties['from']);
        $this->assertSame('Согласовано', $log->properties['reason']);
        $this->assertFalse(Gate::allows('delete', $order));
        $this->withSession(['commerce.orders' => [$order->id]]);
        config(['commerce.enabled' => true, 'storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
        $this->get('/orders/'.$order->id)->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) =>
            $page->where('order.statusLabel', 'Подтверждён'));
    }

    public function test_stale_and_invalid_transitions_do_not_overwrite_status_or_audit(): void
    {
        $order = $this->order();
        $staff = $this->staff();
        $workflow = app(OrderWorkflow::class);
        $workflow->change($order, $staff, $order->status, 'prototype-confirmed', 'Проверено');
        foreach (['prototype-confirmed', 'prototype-completed', 'paid'] as $status) {
            try {
                $workflow->change($order, $staff, 'prototype-pending-confirmation', $status, 'Устаревшее окно');
                $this->fail('Stale transition accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('status', $e->errors());
            }
        }
        $this->assertSame(1, $order->activities()->where('log_name', 'order-status')->count());
        $workflow->change($order, $staff, 'prototype-confirmed', 'prototype-ready', 'Готово');
        $workflow->change($order, $staff, 'prototype-ready', 'prototype-completed', 'Выдано');
        $this->get('/admin/orders/'.$order->id)->assertInertia(fn ($page) => $page->has('transitions', 0));
    }

    public function test_guests_customers_and_drafts_are_denied(): void
    {
        $order = $this->order();
        $this->get('/admin/orders/'.$order->id)->assertRedirect('/admin/login');
        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/admin/orders')->assertForbidden();
        $this->get('/admin/orders/'.$order->id)->assertForbidden();
        $this->assertFalse(Gate::forUser($customer)->allows('update', $order));
        $this->staff();
        $order->update(['placed_at' => null]);
        $this->get('/admin/orders/'.$order->id)->assertNotFound();
    }

    public function test_invalid_targets_and_missing_comments_are_rejected_and_cancellation_is_terminal(): void
    {
        $order = $this->order();
        $staff = $this->staff();
        foreach ([['paid', 'Тест'], ['prototype-completed', 'Тест'], ['prototype-confirmed', '   ']] as [$status, $reason]) {
            try {
                app(OrderWorkflow::class)->change($order, $staff, $order->status, $status, $reason);
                $this->fail('Invalid transition accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, $order->activities()->where('log_name', 'order-status')->count());
        app(OrderWorkflow::class)->change($order, $staff, $order->status, 'prototype-cancelled', 'Отказ покупателя');
        $this->assertSame([], OrderWorkflow::options($order->fresh()->status));
        $this->assertSame('TEST-SKU', $order->lines->first()->meta['sku']);
    }

    public function test_service_rechecks_authorization(): void
    {
        $order = $this->order();
        $customer = User::factory()->create();
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(OrderWorkflow::class)->change($order, $customer, $order->status, 'prototype-confirmed', 'Недопустимо');
    }
}
