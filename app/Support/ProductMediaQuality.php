<?php
namespace App\Support;
use App\Models\Product;
/** Storefront media comes only from explicit assignments, never search manifests. */
class ProductMediaQuality
{
    public static function images(Product $product): ?array
    {
        return ApprovedProductMedia::images($product);
    }
}
