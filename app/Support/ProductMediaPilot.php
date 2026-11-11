<?php

namespace App\Support;

use App\Models\Product;

/** Local-only, explicit pilot manifest; independent of daily price imports. */
class ProductMediaPilot
{
    public static function rows(): array
    {
        if (! app()->environment('local')) return [];
        $path = \Illuminate\Support\Facades\Storage::disk('local')->path('icecat-pilot/preview.json');
        return is_file($path) ? json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : [];
    }

    public static function entry(Product $product): ?array
    {
        foreach (self::rows() as $row) {
            if ($row['id'] === $product->id && $row['sku'] === $product->sku && $row['brand'] === $product->brand
                && $row['exact_ean'] && ! $row['ean_conflict']) return $row;
        }
        return null;
    }

    public static function images(Product $product): array
    {
        $row = self::entry($product);
        if (! $row || ! $product->is_active) return [];
        return array_map(fn ($index) => route('products.pilot-image', [$product, $index]), array_keys($row['preview_images']));
    }
}
