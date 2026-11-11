<?php

namespace App\Commerce;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lunar\DataTypes\{Price, ShippingOption};
use Lunar\Facades\ShippingManifest;
use Lunar\Models\{Cart, Channel, Currency, TaxClass};

class ShoppingCart
{
    public function __construct(private ProductBridge $bridge) {}

    public function current(Request $request, bool $create = false): ?Cart
    {
        $cart = Cart::find($request->session()->get('commerce.cart_id'));
        if ($cart?->hasCompletedOrders()) $cart = null;
        if (! $cart && $create) {
            $cart = Cart::create([
                'currency_id' => Currency::where('code', 'RUB')->firstOrFail()->id,
                'channel_id' => Channel::where('handle', 'altech-prototype')->firstOrFail()->id,
                'meta' => ['prototype' => true],
            ]);
            $request->session()->put('commerce.cart_id', $cart->id);
        }
        return $cart;
    }

    public function shipping(Cart $cart): ShippingOption
    {
        $option = new ShippingOption(($cart->meta['fulfillment'] ?? 'pickup') === 'pickup' ? 'Самовывоз после согласования' : 'Доставка: стоимость согласуется отдельно', 'Без включения стоимости доставки', 'prototype-pickup',
            new Price(0, $cart->currency, 1), TaxClass::where('name', 'Прайсовая цена — прототип')->firstOrFail(), collect: true);
        ShippingManifest::addOption($option);
        return $option;
    }

    /** Refresh source prices before displaying or placing an order. Missing/disabled items block checkout. */
    public function refresh(Cart $cart): array
    {
        $issues = [];
        foreach ($cart->lines()->orderBy('id')->get() as $line) {
            $sourceId = DB::table('commerce_product_links')->where('variant_id', $line->purchasable_id)->value('product_id');
            $source = Product::whereKey($sourceId)->lockForUpdate()->first();
            if (! $source || ! ProductBridge::eligible($source)) {
                $issues[] = 'Товар «'.($source?->name ?? $line->purchasable?->getDescription() ?? 'Удалённый товар').'» больше недоступен для заказа. Удалите его из корзины.';
                continue;
            }
            $this->bridge->sync($source);
            $line->update(['meta' => ['product_id' => $source->id, 'sku' => $source->sku]]);
        }
        $this->shipping($cart);
        $cart->refresh()->recalculate();
        return $issues;
    }

    public static function money(int $minor): string
    {
        return number_format($minor / 100, 2, ',', ' ').' ₽';
    }

    public function data(?Cart $cart, array $issues = []): array
    {
        if (! $cart) return ['lines' => [], 'total' => self::money(0), 'fingerprint' => '', 'issues' => []];
        $products = Product::with('catalogCategory')->whereIn('id', $cart->lines->map(fn ($line) => $line->meta['product_id'] ?? null)->filter())->get()->keyBy('id');
        return [
            'lines' => $cart->lines->map(function ($line) use ($products) {
                $source = $products->get($line->meta['product_id'] ?? null);
                $product = $source && $source->is_active ? \App\Support\Storefront::product($source) : null;
                $image = $product['images'][0] ?? null;
                return [
                'id' => $line->id, 'name' => $line->purchasable->getDescription(),
                'sku' => $line->meta['sku'] ?? null, 'quantity' => $line->quantity,
                'unitPrice' => self::money($line->unitPrice->value), 'total' => self::money($line->total->value),
                'image' => $image, 'imagePreview' => $product['imagePreviews'][$image] ?? null,
                'imageBackground' => $product['imageBackgrounds'][$image] ?? null,
                'url' => $product['url'] ?? null, 'brand' => $product['brand'] ?? null, 'category' => $product['category'] ?? null,
                ];
            })->values(),
            'total' => self::money($cart->total->value), 'fingerprint' => $cart->fingerprint(), 'issues' => $issues,
        ];
    }

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['cart' => $message]);
    }
}
