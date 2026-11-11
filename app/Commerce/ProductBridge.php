<?php

namespace App\Commerce;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Lunar\FieldTypes\Text;
use Lunar\Models\{Channel, Currency, CustomerGroup, Product as LunarProduct, ProductType, ProductVariant, TaxClass};

class ProductBridge
{
    public static function inStock(Product $product): bool
    {
        $stock = trim($product->availability ?? '');
        if (preg_match('/^>?\s*(\d+)$/u', $stock, $matches)) {
            return (int) $matches[1] > 0 || str_starts_with($stock, '>');
        }
        return (bool) preg_match('/^в наличии(?:\s|$)/ui', $stock);
    }

    public static function eligible(Product $product): bool
    {
        return $product->is_active && self::inStock($product) && $product->currency === 'RUB'
            && $product->price !== null && bccomp($product->price, '0', 2) === 1
            && bccomp($product->price, '999999999', 2) <= 0;
    }

    /** Caller holds a source-product lock. Source IDs, not duplicate supplier SKUs, identify variants. */
    public function sync(Product $source): ProductVariant
    {
        $variantId = DB::table('commerce_product_links')->where('product_id', $source->id)->value('variant_id');
        $variant = $variantId ? ProductVariant::findOrFail($variantId) : null;
        $product = $variant?->product ?? new LunarProduct;
        $product->fill([
            'product_type_id' => ProductType::where('name', 'Алтех — прайс')->firstOrFail()->id,
            'status' => self::eligible($source) ? 'published' : 'draft',
            'attribute_data' => collect(['name' => new Text($source->name)]),
        ])->save();
        $product->channels()->syncWithoutDetaching([Channel::where('handle', 'altech-prototype')->firstOrFail()->id => ['enabled' => true]]);
        $product->customerGroups()->syncWithoutDetaching([CustomerGroup::where('handle', 'altech-guests')->firstOrFail()->id => ['enabled' => true, 'visible' => true, 'purchasable' => true]]);
        if (! $variant) {
            $variant = $product->variants()->create([
                'sku' => 'ALTECH-'.$source->id, 'unit_quantity' => 1,
                'tax_class_id' => TaxClass::where('name', 'Прайсовая цена — прототип')->firstOrFail()->id,
                'shippable' => true, 'purchasable' => 'always', 'stock' => 0,
            ]);
            DB::table('commerce_product_links')->insert(['product_id' => $source->id, 'variant_id' => $variant->id]);
        }
        if (self::eligible($source)) {
            $variant->prices()->updateOrCreate([
                'currency_id' => Currency::where('code', 'RUB')->firstOrFail()->id,
                'customer_group_id' => null, 'min_quantity' => 1,
            ], ['price' => (int) bcmul($source->price, '100', 0)]);
        }
        return $variant->fresh();
    }
}
