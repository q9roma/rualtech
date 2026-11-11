<?php

namespace App\Support;

use App\Models\{Product, CatalogPriceUpload};
use Illuminate\Support\Facades\DB;

class CatalogPhotoAssignment
{
    public function assign(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $candidate = DB::table('catalog_photo_tasks')->find($id);
            if (!$candidate) return false;
            $product = Product::whereKey($candidate->product_id)->lockForUpdate()->first();
            $task = DB::table('catalog_photo_tasks')->where('id', $id)->lockForUpdate()->first();
            if (!$product || $product->photos !== null || $task->status !== 'ready') return false;
            $payload = json_decode($task->payload, true);
            $result = json_decode($task->result ?? '{}', true);
            if (!empty($payload['ean_conflict'])) return false;
            foreach (['name', 'brand', 'sku'] as $key) {
                if (($payload[$key] ?? '') !== (string) $product->$key) return false;
            }
            $upload = $task->upload_id ? CatalogPriceUpload::find($task->upload_id) : null;
            if ($upload) {
                $codes = DB::table('catalog_source_products')->where('product_id', $product->id)->where('source', $upload->source_profile)->pluck('supplier_data')
                    ->map(fn ($data) => (string) (json_decode($data ?? '{}', true)['EAN'] ?? ''))->filter()->unique()->values();
                if ($codes->count() > 1 || ($codes->first() ?? '') !== ($payload['ean'] ?? '')) return false;
            }
            $images = $result['images'] ?? [];
            $confirmed = in_array($result['manual_review']['decision'] ?? '', ['accept', 'accept_all', 'accept_match'], true);
            if (!$images || collect($images)->contains(fn ($image) => !ApprovedProductMedia::valid($image) || (!$confirmed && ($image['identity'] ?? '') !== 'confirmed'))) return false;
            if (!ApprovedProductMedia::files($product)) {
                DB::table('catalog_approved_media')->updateOrInsert(['product_id' => $product->id], [
                    'origin' => 'automatic', 'task_id' => $id, 'user_id' => null,
                    'identity' => $task->payload, 'images' => json_encode($images, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('catalog_product_events')->insert([
                    'product_id' => $product->id, 'kind' => 'photos_assigned', 'changes' => '{}',
                    'context' => json_encode(['task_id' => $id, 'origin' => 'automatic']),
                    'source' => $upload?->source_profile, 'batch_id' => $upload?->summary['batch_id'] ?? null,
                    'user_id' => null, 'created_at' => now(),
                ]);
            }
            DB::table('catalog_photo_tasks')->where('id', $id)->update(['status' => 'preserved', 'available_at' => null, 'updated_at' => now()]);
            return true;
        });
    }
}
