<?php

namespace Tests\Feature;

use App\Commerce\PrototypeSetup;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\{Cart, Order};
use Tests\TestCase;

class LunarCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.enabled' => true, 'storefront.inertia' => true, 'inertia.ssr.enabled' => false]);
        $this->withoutVite();
        app(PrototypeSetup::class)->run();
        Mail::fake();
    }

    private function product(array $attributes = []): Product
    {
        return Product::create([...['name' => 'Тестовый сервер', 'sku' => 'DUPLICATE-SKU', 'price' => '1234.56', 'currency' => 'RUB', 'availability' => '10', 'is_active' => true], ...$attributes]);
    }

    private function checkout(): array
    {
        $response = $this->get('/checkout')->assertOk();
        $props = $response->viewData('page')['props'];
        return ['checkoutKey' => $props['checkoutKey'], 'fingerprint' => $props['cart']['fingerprint'], 'first_name' => 'Тест', 'last_name' => 'Покупатель',
            'email' => 'buyer@example.test', 'contact_phone' => '+70000000000', 'city' => 'Тестовый город',
            'line_one' => 'Тестовая улица, 1', 'postcode' => '000000', 'company_name' => 'ООО Проверка', 'inn' => '7707083893', 'notes' => 'Тест', 'delivery_method' => 'pickup'];
    }

    public function test_delivery_and_account_are_saved_with_order(): void
    {
        $product = $this->product();
        $this->post('/cart/products/'.$product->id, ['quantity' => 1]);
        $data = [...$this->checkout(), 'delivery_method' => 'terminal', 'carrier' => 'cdek',
            'shipping_city' => 'Москва', 'shipping_address' => 'Терминал на улице Лесной',
            'create_account' => true, 'password' => 'long-secure-password', 'password_confirmation' => 'long-secure-password'];
        $this->post('/checkout', $data)->assertRedirect('/orders/'.Order::sole()->getKey());
        $order = Order::sole();
        $this->assertAuthenticatedAs(\App\Models\User::findOrFail($order->user_id));
        $this->assertSame('СДЭК', $order->meta['delivery']['carrier']);
        $this->assertSame('Москва', $order->shippingAddress->city);
        $this->assertSame('Тестовый город', $order->billingAddress->city);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('long-secure-password', \App\Models\User::findOrFail($order->user_id)->password));
        $this->post('/checkout', $data)->assertRedirect('/orders/'.Order::sole()->getKey());
        $this->assertSame(1, Order::count());
    }

    public function test_manual_yandex_pickup_saves_address_without_widget_data(): void
    {
        $this->post('/cart/products/'.$this->product()->id, ['quantity' => 1]);
        $data = [...$this->checkout(), 'delivery_method' => 'yandex_pvz'];
        $this->post('/checkout', $data)->assertSessionHasErrors(['shipping_city', 'shipping_address']);
        $this->post('/checkout', [...$data, 'shipping_city' => 'Москва', 'shipping_address' => 'Никитский бульвар, 15/16', 'pickup_point' => ['id' => 'stale'], 'carrier' => 'cdek'])->assertRedirect('/orders/'.Order::sole()->getKey());
        $order = Order::sole();
        $this->assertNull($order->meta['delivery']['pickup_point']);
        $this->assertSame('Яндекс Доставка', $order->meta['delivery']['carrier']);
        $this->assertSame('Никитский бульвар, 15/16', $order->shippingAddress->line_one);
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertInertia(fn (Assert $page) => $page
            ->where('order.delivery.method', 'yandex_pvz')
            ->where('order.delivery.city', 'Москва')
            ->where('order.delivery.address', 'Никитский бульвар, 15/16')
            ->where('order.company.inn', '7707083893'));
    }

    public function test_other_delivery_methods_discard_stale_pickup_selection(): void
    {
        $this->post('/cart/products/'.$this->product()->id, ['quantity' => 1]);
        $this->post('/checkout', [...$this->checkout(), 'pickup_point' => ['id' => 'stale-point']])->assertRedirect('/orders/'.Order::sole()->getKey());
        $this->assertNull(Order::sole()->meta['delivery']['pickup_point']);
        $this->assertSame(config('commerce.pickup.address'), Order::sole()->shippingAddress->line_one);
        $this->assertSame('141007', Order::sole()->shippingAddress->postcode);
        $this->assertSame(config('commerce.pickup.directions'), Order::sole()->meta['delivery']['directions']);
    }

    public function test_delivery_requires_valid_carrier_and_destination(): void
    {
        $product = $this->product();
        $this->post('/cart/products/'.$product->id, ['quantity' => 1]);
        $this->post('/checkout', [...$this->checkout(), 'delivery_method' => 'address', 'carrier' => 'unknown'])
            ->assertSessionHasErrors(['carrier', 'shipping_city', 'shipping_address']);
        $this->assertSame(0, Order::count());
    }

    public function test_missing_billing_postcode_returns_validation_instead_of_server_error(): void
    {
        $product = $this->product();
        $this->post('/cart/products/'.$product->id, ['quantity' => 1]);
        $this->post('/checkout', [...$this->checkout(), 'postcode' => ''])->assertSessionHasErrors('postcode');
        $this->assertSame(0, Order::count());
    }

    public function test_courier_requires_region_and_saves_without_transport_company(): void
    {
        $product = $this->product();
        $this->post('/cart/products/'.$product->id, ['quantity' => 1]);
        $data = [...$this->checkout(), 'delivery_method' => 'courier', 'shipping_city' => 'Москва', 'shipping_address' => 'Адрес, 1'];
        $this->post('/checkout', $data)->assertSessionHasErrors('courier_region');
        $this->post('/checkout', [...$data, 'courier_region' => 'moscow'])->assertRedirect('/orders/'.Order::sole()->getKey());
        $this->assertSame('Курьер Алтех', Order::sole()->meta['delivery']['carrier']);
    }

    public function test_existing_account_cannot_be_claimed_by_checkout_email(): void
    {
        $user = \App\Models\User::factory()->create(['email' => 'buyer@example.test']);
        $product = $this->product();
        $this->post('/cart/products/'.$product->id, ['quantity' => 1]);
        $this->post('/checkout', [...$this->checkout(), 'create_account' => true,
            'password' => 'long-secure-password', 'password_confirmation' => 'long-secure-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(0, Order::count());
    }

    public function test_home_cards_expose_cart_actions_only_for_purchasable_products(): void
    {
        $product = $this->product();
        $this->product(['name' => 'По запросу', 'price' => null]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('catalogProducts.0.canAddToCart', true)
            ->where('catalogProducts.0.addToCartUrl', route('cart.add', $product))
            ->where('catalogProducts.1.canAddToCart', false)
            ->where('cartUrl', route('cart.index')));
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertRedirect(route('cart.index'));
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('cart.lines', 1));
        config(['commerce.enabled' => false]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('catalogProducts.0.canAddToCart', false));
    }

    public function test_inline_add_returns_to_source_and_shares_persistent_cart_summary(): void
    {
        $product = $this->product();
        $this->from('/?category=servers')->post(route('cart.add', $product), ['quantity' => 2, 'stay' => true])
            ->assertRedirect('/?category=servers');
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('cartSummary.count', 2)
            ->where('cartSummary.addedUrls.0', route('cart.add', $product)));
        $line = Cart::sole()->lines()->firstOrFail();
        $this->from('/')->patch('/cart/lines/'.$line->id, ['quantity' => 3, 'stay' => true])->assertRedirect('/');
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('cartSummary.count', 3)->where('cartSummary.lines.0.quantity', 3)
            ->where('cartSummary.lines.0.updateUrl', route('cart.update', $line->id)));
        $this->from('/')->patch('/cart/lines/'.$line->id, ['quantity' => 1, 'stay' => true])->assertRedirect('/');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('cartSummary.count', 1));
        $this->from('/')->delete('/cart/lines/'.$line->id, ['stay' => true])->assertRedirect('/');
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('cartSummary.count', 0)->has('cartSummary.addedUrls', 0));
    }

    public function test_signed_in_checkout_assigns_owner_and_survives_a_new_session(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        $product = $this->product();
        $this->post('/cart/products/'.$product->id, ['quantity' => 1])->assertRedirect('/cart');
        $this->post('/checkout', $this->checkout())->assertRedirect('/orders/'.Order::sole()->getKey());
        $this->assertEquals($user->id, Order::sole()->user_id);
        $this->withSession(['commerce' => []])->get('/orders/'.Order::sole()->getKey())->assertOk();
        $other = \App\Models\User::factory()->create();
        $this->actingAs($other)->withSession(['commerce.orders' => [Order::sole()->getKey()]])->get('/orders/'.Order::sole()->getKey())->assertNotFound();
    }

    public function test_native_lunar_cart_checkout_and_order_are_idempotent_and_keep_snapshots(): void
    {
        $product = $this->product();
        $this->post('/cart/products/'.$product->id, ['quantity' => 2, 'price' => 1])->assertRedirect('/cart');
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->component('commerce/cart')->has('cart.lines', 1)->where('cart.total', '2 469,12 ₽'));
        $data = $this->checkout();
        $this->post('/checkout', $data)->assertRedirect('/orders/'.Order::sole()->getKey());
        $order = Order::firstOrFail();
        $this->assertSame(246912, $order->total->value);
        $this->assertNotNull($order->placed_at);
        $this->assertSame('buyer@example.test', $order->billingAddress->contact_email);
        $this->assertSame('prototype-pending-confirmation', $order->status);
        $this->post('/checkout', $data)->assertRedirect('/orders/'.Order::sole()->getKey());
        $this->assertSame(1, Order::count());
        $product->update(['name' => 'Новое имя', 'price' => '9999.99']);
        $this->get('/orders/'.Order::sole()->getKey())->assertInertia(fn (Assert $page) => $page->component('commerce/order')
            ->where('order.total', '2 469,12 ₽')->where('order.lines.0.name', 'Тестовый сервер')->where('order.lines.0.sku', 'DUPLICATE-SKU'));
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('cart.lines', 0));
        Mail::assertNothingSent();
    }

    public function test_price_changes_require_review_and_inactive_products_block_checkout(): void
    {
        $product = $this->product();
        $this->post('/cart/products/'.$product->id, ['quantity' => 1])->assertRedirect();
        $data = $this->checkout();
        $product->update(['price' => '2000.00']);
        $this->post('/checkout', $data)->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('cart.total', '2 000,00 ₽'));
        $product->update(['is_active' => false]);
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('cart.issues', 1));
        $this->get('/checkout')->assertRedirect('/cart')->assertSessionHasErrors('cart');
    }

    public function test_quantities_duplicates_and_removal_use_native_lunar_lines(): void
    {
        $first = $this->product();
        $second = $this->product(['name' => 'Другой товар']);
        $this->post('/cart/products/'.$first->id, ['quantity' => 1])->assertRedirect();
        $this->post('/cart/products/'.$first->id, ['quantity' => 2])->assertRedirect();
        $this->post('/cart/products/'.$second->id, ['quantity' => 1])->assertRedirect();
        $cart = Cart::firstOrFail();
        $this->assertCount(2, $cart->lines);
        $line = $cart->lines->first();
        $this->assertSame(3, $line->quantity);
        $this->patch('/cart/lines/'.$line->id, ['quantity' => 4])->assertRedirect();
        $this->assertSame(4, $line->fresh()->quantity);
        $this->patch('/cart/lines/'.$line->id, ['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->post('/cart/products/'.$first->id, ['quantity' => 99])->assertSessionHasErrors('cart');
        $this->delete('/cart/lines/'.$line->id)->assertRedirect();
        $this->assertSame(1, $cart->lines()->count());
    }

    public function test_foreign_sessions_cannot_read_orders_or_change_lines_or_replay_keys(): void
    {
        $product = $this->product();
        $this->post('/cart/products/'.$product->id, ['quantity' => 1]);
        $line = Cart::firstOrFail()->lines->first();
        $data = $this->checkout();
        $this->post('/checkout', $data)->assertRedirect('/orders/'.Order::sole()->getKey());
        $this->withSession(['commerce' => []]);
        $this->get('/orders/'.Order::sole()->getKey())->assertNotFound();
        $this->patch('/cart/lines/'.$line->id, ['quantity' => 1])->assertNotFound();
        $this->delete('/cart/lines/'.$line->id)->assertNotFound();
        $this->post('/checkout', $data)->assertNotFound();
        $this->assertSame(1, Order::count());
    }

    public function test_quote_only_products_and_disabled_prototype_cannot_be_ordered(): void
    {
        foreach ([['price' => null], ['price' => '0.00'], ['currency' => 'USD'], ['is_active' => false], ['availability' => '0'], ['availability' => null], ['availability' => 'Под заказ']] as $attrs) {
            $product = $this->product($attrs);
            $this->post('/cart/products/'.$product->id, ['quantity' => 1])->assertSessionHasErrors('cart');
        }
        $this->get('/checkout')->assertRedirect('/cart');
        config(['commerce.enabled' => false]);
        $this->get('/cart')->assertNotFound();
        $this->post('/cart/products/'.$product->id, ['quantity' => 1])->assertNotFound();
    }

    public function test_stock_is_rechecked_before_invoice_request(): void
    {
        $product = $this->product(['availability' => '>50']);
        $this->assertTrue(\App\Commerce\ProductBridge::inStock($product));
        $this->post('/cart/products/'.$product->id, ['quantity' => 1])->assertRedirect();
        $data = $this->checkout();
        $product->update(['availability' => '0']);
        $this->post('/checkout', $data)->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('cart.issues', 1));
    }
}
