<?php
namespace App\Admin;

use App\Models\CatalogPriceUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{Cache, DB, Storage};

class CatalogPresence
{
    public const SOURCES = ['supplier-price-32' => 'Новый прайс — ID и склады', 'altech-price' => 'Прежний прайс Алтех', 'unlinked' => 'Без источника'];

    public static function source(Builder $query, string $source): void
    {
        $callback = fn ($q) => $q->selectRaw('1')->from('catalog_source_products as cs')->whereColumn('cs.product_id', 'products.id')
            ->when($source !== 'unlinked', fn ($q) => $q->where('cs.source', $source));
        if ($source === 'unlinked') $query->whereNotExists($callback);
        else $query->whereExists($callback);
    }

    public static function current(string $source): array
    {
        // Only completed full uploads define presence. Partial reviewed batches do not.
        $upload = CatalogPriceUpload::where('source_profile', $source)->where('status', 'completed')->latest('updated_at')->orderByDesc('id')->first();
        if (! $upload) return ['upload' => null, 'hashes' => []];
        $hashes = Cache::remember('catalog-presence:'.$upload->id.':'.$upload->staged_hash, 300, function () use ($upload, $source) {
            $json = Storage::disk('local')->get($upload->directory().'/staged-products.json');
            if (! $json || ! hash_equals($upload->staged_hash, hash('sha256', $json))) return null;
            $rows = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            return array_values(array_unique(array_map(fn ($r) => $source === 'supplier-price-32'
                ? hash('sha256', 'supplier-id:'.trim($r['supplier_id'] ?? ''))
                : CatalogDraftImport::identity($r['brand'] ?? '', $r['sku'] ?? '', $r['name']), $rows)));
        });
        return ['upload' => $hashes === null ? null : $upload, 'hashes' => $hashes ?? []];
    }

    public static function apply(Builder $query, string $source, string $presence, array $current): void
    {
        if (! $current['upload']) { $query->whereRaw('1 = 0'); return; }
        $matches = DB::table('catalog_source_products')->select('product_id')->where('source', $source)->whereIn('identity_hash', $current['hashes']);
        if ($presence === 'present') $query->whereIn('products.id', $matches);
        else $query->whereNotIn('products.id', $matches);
    }
}
