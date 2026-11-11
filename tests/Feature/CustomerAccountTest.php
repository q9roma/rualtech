<?php

namespace Tests\Feature;

use App\Commerce\PrototypeSetup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\Order;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.enabled' => true, 'storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
        $this->withoutVite();
        app(PrototypeSetup::class)->run();
    }

    private function order(?User $user = null): Order
    {
        return Order::factory()->create(['currency_code' => 'RUB', 'placed_at' => now(),
            'status' => 'prototype-confirmed', 'user_id' => $user?->id, 'meta' => ['prototype' => true]]);
    }

    public function test_registration_login_logout_and_staff_separation(): void
    {
        $this->get('/account')->assertRedirect('/login');
        $this->get('/register')->assertInertia(fn (Assert $p) => $p->component('account/auth')->where('register', true));
        $this->post('/register', ['name' => 'Покупатель', 'email' => 'BUYER@example.test', 'password' => 'long-password-123',
            'password_confirmation' => 'long-password-123', 'is_admin' => true])->assertRedirect('/account');
        $user = User::sole();
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('long-password-123', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->get('/admin/orders')->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'long-password-123'])->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
    }

    public function test_claim_requires_session_and_never_reassigns_an_owned_order(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $guest = $this->order();
        $foreign = $this->order($other);
        $this->actingAs($user)->post('/account/claim', ['order_id' => $guest->id])->assertNotFound();
        $this->withSession(['commerce.orders' => [$guest->id, $foreign->id]]);
        $this->post('/account/claim', ['order_id' => $foreign->id])->assertNotFound();
        $this->get('/orders/'.$foreign->id)->assertNotFound();
        $this->post('/account/claim', ['order_id' => $guest->id])->assertRedirect('/account');
        $this->post('/account/claim', ['order_id' => $guest->id])->assertRedirect('/account');
        $this->assertEquals($user->id, $guest->fresh()->user_id);
        $this->assertEquals($other->id, $foreign->fresh()->user_id);
        $this->post('/logout');
        $this->get('/orders/'.$guest->id)->assertNotFound();
        $this->actingAs($user)->withSession(['commerce' => []])->get('/orders/'.$guest->id)->assertOk();
    }

    public function test_orders_are_scoped_paginated_and_status_is_current_without_email_linking(): void
    {
        $user = User::factory()->create();
        $mine = $this->order($user);
        $foreign = $this->order(User::factory()->create());
        $guest = $this->order();
        $guest->addresses()->create(['type' => 'billing', 'contact_email' => $user->email]);
        $this->actingAs($user)->get('/account')->assertInertia(fn (Assert $p) => $p->component('account/index')
            ->has('orders.data', 1)->where('orders.data.0.id', $mine->id)->where('orders.data.0.status', 'Подтверждён')->has('guestOrders', 0));
        $this->get('/orders/'.$foreign->id)->assertNotFound();
        $this->get('/orders/'.$guest->id)->assertNotFound();
        $mine->update(['status' => 'prototype-completed']);
        $this->get('/orders/'.$mine->id)->assertInertia(fn (Assert $p) => $p->where('order.statusLabel', 'Завершён'));
        for ($i = 0; $i < 15; $i++) $this->order($user);
        $this->get('/account?page=2')->assertInertia(fn (Assert $p) => $p->has('orders.data', 1)->where('orders.current_page', 2));
    }

    public function test_registration_preserves_guest_proof_but_requires_explicit_claim(): void
    {
        $guest = $this->order();
        $this->withSession(['commerce.orders' => [$guest->id]])->post('/register', ['name' => 'Тест', 'email' => 'new@example.test',
            'password' => 'long-password-123', 'password_confirmation' => 'long-password-123'])->assertRedirect('/account');
        $this->assertNull($guest->fresh()->user_id);
        $this->get('/account')->assertInertia(fn (Assert $p) => $p->has('guestOrders', 1)->has('orders.data', 0));
    }

    public function test_invalid_registration_and_login_rate_limit(): void
    {
        $this->post('/register', ['name' => 'Тест', 'email' => 'bad', 'password' => 'short', 'password_confirmation' => 'different'])
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertSame(0, User::count());
        for ($i = 0; $i < 6; $i++) $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
    }
}
