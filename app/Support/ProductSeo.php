<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Str;

class ProductSeo
{
    public static function metadata(Product $product): array
    {
        $description = trim($product->description ?? '') ?: (Storefront::supplierDescription($product) ?? '');
        $summary = trim(preg_replace('/\s+/u', ' ', strip_tags($description)));
        $url = route('products.show', $product);
        $data = ['@context' => 'https://schema.org', '@type' => 'Product',
            'name' => $product->name, 'url' => $url];
        if ($summary !== '') $data['description'] = $summary;
        if ($product->sku) $data['sku'] = $product->sku;
        if ($product->brand) $data['brand'] = ['@type' => 'Brand', 'name' => $product->brand];
        $images = Storefront::product($product)['images'];
        if ($images) $data['image'] = array_map(fn ($image) => url($image), $images);
        if ($product->price !== null && (float) $product->price > 0 && $product->currency) {
            $data['offers'] = ['@type' => 'Offer', 'url' => $url,
                'price' => $product->price, 'priceCurrency' => $product->currency];
            if (\App\Commerce\ProductBridge::inStock($product)) {
                $data['offers']['availability'] = 'https://schema.org/InStock';
            } elseif (preg_match('/^(нет в наличии|нет|отсутствует|0)$/ui', trim($product->availability ?? ''))) {
                $data['offers']['availability'] = 'https://schema.org/OutOfStock';
            }
        }
        return ['title' => $product->name, 'description' => Str::limit($summary !== '' ? $product->name.'. '.$summary : implode('. ', array_filter([
            $product->name, $product->sku ? 'Артикул '.$product->sku : null,
            $product->brand, $product->formatted_price,
        ])), 180), 'canonicalUrl' => $url, 'structuredData' => $data];
    }
}
