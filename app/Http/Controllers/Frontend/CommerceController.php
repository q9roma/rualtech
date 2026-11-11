<?php

namespace App\Http\Controllers\Frontend;

use App\Commerce\{ProductBridge, ShoppingCart};
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Lunar\Models\{Cart, Country, Order};

class CommerceController extends Controller
{
    public function __construct(private ShoppingCart $carts, private ProductBridge $bridge) {}

    private function enabled(): void
    {
        abort_unless(config('commerce.enabled') && config('storefront.inertia'), 404);
    }

    public function index(Request $request)
    {
        $this->enabled();
        return DB::transaction(function () use ($request) {
            $cart = $this->carts->current($request);
            if ($cart) $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $issues = $cart ? $this->carts->refresh($cart) : [];
            return Inertia::render('commerce/cart', ['title' => 'Корзина', 'canonicalUrl' => route('cart.index'), 'cart' => $this->carts->data($cart, $issues)]);
        });
    }

    public function add(Request $request, Product $product)
    {
        $this->enabled();
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.config('commerce.max_quantity')], 'stay' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($request, $product, $data) {
            $cart = $this->carts->current($request, true);
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $source = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! ProductBridge::eligible($source)) $this->carts->fail('Этот товар доступен только по запросу менеджеру.');
            $variant = $this->bridge->sync($source);
            $existing = $cart->lines()->where('purchasable_id', $variant->id)->first();
            if (($existing?->quantity ?? 0) + $data['quantity'] > config('commerce.max_quantity')) $this->carts->fail('Максимум 99 единиц одной позиции.');
            if (! $existing && $cart->lines()->count() >= config('commerce.max_lines')) $this->carts->fail('Максимум 50 позиций в корзине.');
            $cart->add($variant, $data['quantity'], ['product_id' => $source->id, 'sku' => $source->sku]);
        });
        return $request->boolean('stay') ? back() : to_route('cart.index');
    }

    public function update(Request $request, int $line)
    {
        $this->enabled();
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.config('commerce.max_quantity')], 'stay' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($request, $line, $data) {
            $cart = $this->carts->current($request);
            abort_unless($cart, 404);
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $cart->lines()->findOrFail($line);
            $issues = $this->carts->refresh($cart);
            if ($issues) $this->carts->fail(implode(' ', $issues));
            $cart->updateLine($line, $data['quantity']);
        });
        return $request->boolean('stay') ? back() : to_route('cart.index');
    }

    public function remove(Request $request, int $line)
    {
        $this->enabled();
        $request->validate(['stay' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($request, $line) {
            $cart = $this->carts->current($request);
            abort_unless($cart, 404);
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $cart->lines()->findOrFail($line);
            $cart->remove($line, refresh: false);
        });
        return $request->boolean('stay') ? back() : to_route('cart.index');
    }

    public function checkout(Request $request)
    {
        $this->enabled();
        return DB::transaction(function () use ($request) {
            $cart = $this->carts->current($request);
            if (! $cart || ! $cart->lines()->exists()) return to_route('cart.index');
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $issues = $this->carts->refresh($cart);
            if ($issues) return to_route('cart.index')->withErrors(['cart' => implode(' ', $issues)]);
            $keys = $request->session()->get('commerce.checkout_keys', []);
            $key = array_search($cart->id, $keys, true) ?: (string) Str::uuid();
            $keys[$key] = $cart->id;
            $request->session()->put('commerce.checkout_keys', array_slice($keys, -20, null, true));
            return Inertia::render('commerce/checkout', ['title' => 'Оформление заказа', 'canonicalUrl' => route('checkout.index'),
                'cart' => $this->carts->data($cart), 'checkoutKey' => $key, 'buyer' => $request->user()?->only(['name', 'email']), 'carriers' => config('commerce.carriers'), 'pickup' => config('commerce.pickup')]);
        });
    }

    public function place(Request $request)
    {
        $this->enabled();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'checkoutKey' => ['required', 'uuid'], 'fingerprint' => ['required', 'string', 'size:40'],
            'first_name' => ['required', 'string', 'min:2', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'], 'contact_phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:100'], 'line_one' => ['required', 'string', 'max:255'],
            'inn' => ['required', 'regex:/^(?:[0-9]{10}|[0-9]{12})$/'],
            'kpp' => ['nullable', 'regex:/^[0-9]{9}$/'],
            'city' => ['required', 'string', 'max:100'], 'postcode' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'delivery_method' => ['required', 'in:address,terminal,pickup,courier,yandex_pvz'],
            'carrier' => ['exclude_if:delivery_method,pickup,courier,yandex_pvz', 'required', \Illuminate\Validation\Rule::in(array_keys(config('commerce.carriers')))],
            'courier_region' => ['exclude_unless:delivery_method,courier', 'required', 'in:moscow,moscow_region'],
            'shipping_city' => ['exclude_if:delivery_method,pickup', 'required', 'string', 'max:100'],
            'shipping_address' => ['exclude_if:delivery_method,pickup', 'required', 'string', 'max:255'],
            'create_account' => ['sometimes', 'boolean'],
            'password' => ['exclude_unless:create_account,true', 'required', 'confirmed', 'max:128', \Illuminate\Validation\Rules\Password::min(12)],
        ], [
            'company_name.required' => 'Укажите название организации или ИП.',
            'inn.required' => 'Укажите ИНН организации или ИП.',
            'inn.regex' => 'ИНН должен содержать 10 или 12 цифр.',
            'kpp.regex' => 'КПП должен содержать 9 цифр.',
            'carrier.required' => 'Выберите транспортную компанию.',
            'shipping_city.required' => 'Укажите город доставки.',
            'shipping_address.required' => 'Укажите адрес доставки или терминал.',
            'password.confirmed' => 'Пароли не совпадают.',
            'postcode.required' => 'Укажите почтовый индекс юридического адреса.',
            'postcode.regex' => 'Почтовый индекс должен содержать 6 цифр.',
        ]);
        $cartId = $request->session()->get('commerce.checkout_keys', [])[$data['checkoutKey']] ?? null;
        abort_unless($cartId, 404);
        $order = DB::transaction(function () use ($cartId, $data, $request) {
            $cart = Cart::whereKey($cartId)->lockForUpdate()->firstOrFail();
            if ($order = $cart->completedOrders()->first()) {
                abort_unless($order->user_id === null || (int) $order->user_id === $request->user()?->id, 404);
                return $order;
            }
            if (! $cart->lines()->exists()) $this->carts->fail('Корзина пуста.');
            $issues = $this->carts->refresh($cart);
            if ($issues) $this->carts->fail(implode(' ', $issues));
            if (! hash_equals($cart->fingerprint(), $data['fingerprint'])) $this->carts->fail('Цена или состав корзины изменились. Вернитесь в корзину и проверьте обновлённый итог.');
            $address = collect($data)->only(['first_name', 'last_name', 'email', 'contact_phone', 'company_name', 'line_one', 'city', 'postcode'])->all();
            $address['country_id'] = Country::where('iso2', 'RU')->firstOrFail()->id;
            $address['contact_email'] = $data['email'];
            unset($address['email']);
            $cart->setBillingAddress($address);
            $shipping = $address;
            if ($data['delivery_method'] !== 'pickup') {
                $shipping['city'] = $data['shipping_city'];
                $shipping['line_one'] = $data['shipping_address'];
                $shipping['postcode'] = null;
            }
            if ($data['delivery_method'] === 'pickup') {
                $shipping['city'] = config('commerce.pickup.city');
                $shipping['line_one'] = config('commerce.pickup.address');
                $shipping['postcode'] = config('commerce.pickup.postcode');
            }
            $cart->setShippingAddress($shipping);
            $delivery = ['method' => $data['delivery_method'], 'directions' => $data['delivery_method'] === 'pickup' ? config('commerce.pickup.directions') : null, 'pickup_point' => null, 'carrier' => $data['delivery_method'] === 'yandex_pvz' ? 'Яндекс Доставка' : ($data['delivery_method'] === 'courier' ? 'Курьер Алтех' : (config('commerce.carriers')[$data['carrier'] ?? ''] ?? null)), 'region' => $data['courier_region'] ?? null,
                'city' => $data['delivery_method'] === 'pickup' ? config('commerce.pickup.city') : $data['shipping_city'],
                'address' => $data['delivery_method'] === 'pickup' ? config('commerce.pickup.postcode').', '.config('commerce.pickup.address') : $data['shipping_address']];
            $cart->update(['meta' => ['prototype' => false, 'request_type' => 'invoice', 'notes' => $data['notes'] ?? '', 'delivery' => $delivery,
                'company' => ['name' => $data['company_name'], 'inn' => $data['inn'], 'kpp' => $data['kpp'] ?? null], 'fulfillment' => $data['delivery_method']]]);
            $cart->setShippingOption($this->carts->shipping($cart));
            $buyer = $request->user();
            if (! $buyer && ($data['create_account'] ?? false)) {
                if (\App\Models\User::where('email', $data['email'])->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Этот email уже зарегистрирован. Войдите или оформите заказ без создания аккаунта.']);
                }
                $buyer = \App\Models\User::create(['name' => mb_substr($data['first_name'].' '.$data['last_name'], 0, 100), 'email' => $data['email'], 'password' => $data['password']]);
            }
            $order = $cart->createOrder();
            $order->update(['status' => 'prototype-pending-confirmation', 'placed_at' => now(), 'user_id' => $buyer?->id]);
            return $order;
        });
        if (! $request->user() && ($data['create_account'] ?? false) && $order->user_id) {
            \Illuminate\Support\Facades\Auth::loginUsingId($order->user_id);
            $request->session()->regenerate();
        }
        $ids = $request->session()->get('commerce.orders', []);
        $request->session()->put('commerce.orders', array_slice(array_unique([...$ids, $order->id]), -20));
        return to_route('orders.show', $order->id);
    }

    public function order(Request $request, int $commerceOrder)
    {
        $this->enabled();
        $order = $commerceOrder;
        $order = Order::with('lines')->findOrFail($order);
        abort_unless($order->placed_at && ($order->user_id !== null
            ? (int) $order->user_id === $request->user()?->id
            : in_array($order->id, $request->session()->get('commerce.orders', []), true)), 404);
        return Inertia::render('commerce/order', ['title' => 'Заказ №'.$order->reference, 'canonicalUrl' => route('orders.show', $order->id),
            'order' => ['isOwner' => $order->user_id !== null, 'company' => $order->meta['company'] ?? null, 'delivery' => $order->meta['delivery'] ?? null, 'reference' => $order->reference, 'statusLabel' => \App\Commerce\OrderWorkflow::LABELS[$order->status] ?? 'Обрабатывается', 'total' => ShoppingCart::money($order->total->value),
                'lines' => $order->lines->where('type', '!=', 'shipping')->map(fn ($line) => [
                    'id' => $line->id, 'name' => $line->description, 'sku' => $line->meta['sku'] ?? $line->identifier,
                    'quantity' => $line->quantity, 'total' => ShoppingCart::money($line->total->value),
                ])->values()]]);
    }
}
