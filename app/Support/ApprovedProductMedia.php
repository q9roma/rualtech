<?php
namespace App\Support;
use App\Models\Product;
use Illuminate\Support\Facades\{DB,Storage};
class ApprovedProductMedia {
    public static function blockingFlags(array $image): array {
        return array_values(array_diff($image['flags'] ?? [], ['shared_across_models','perceptual_match']));
    }
    public static function files(Product $product): array {
        $row=DB::table('catalog_approved_media')->where('product_id',$product->id)->first();
        if (!$row) return [];
        $identity=json_decode($row->identity,true);
        foreach (['name','brand','sku'] as $key) if (($identity[$key] ?? '') !== (string)$product->$key) return [];
        return array_values(array_filter(json_decode($row->images,true),fn($im)=>self::valid($im)));
    }
    public static function valid(array $image): bool {
        return preg_match('/\A[a-f0-9]{64}\.(jpg|png|webp)\z/',$image['file'] ?? '')
            && !self::blockingFlags($image) && Storage::disk('local')->exists('catalog-media-queue/media/'.$image['file']);
    }
    public static function images(Product $product): array {
        return array_map(fn($i)=>request()->is('admin/*') ? url('/admin/products/'.$product->id.'/assigned-photo/'.$i) : route('catalog.approved-image',[$product->id,$i]),array_keys(self::files($product)));
    }
}
