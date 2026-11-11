<?php
namespace App\Admin;
use Illuminate\Support\Facades\DB;

/** Called only by full-price application, inside its transaction. */
class CatalogMissingStock
{
    public static function apply(array $rows,string $source, ?int $batch = null): int
    {
        if (!$rows) throw new CatalogPriceException('Пустой прайс нельзя использовать для обновления наличия.');
        $hashes=array_map(fn($r)=>$source==='supplier-price-32' ? hash('sha256','supplier-id:'.trim($r['supplier_id'] ?? '')) : CatalogDraftImport::identity($r['brand'] ?? '',$r['sku'] ?? '',$r['name']),$rows);
        $present=DB::table('catalog_source_products')->where('source',$source)->whereIn('identity_hash',$hashes)->pluck('product_id');
        $missing=DB::table('catalog_source_products')->where('source',$source)->whereNotIn('identity_hash',$hashes)->whereNotIn('product_id',$present)->pluck('product_id')->unique();
        $newlyMissing=DB::table('catalog_source_products')->where('source',$source)->whereIn('product_id',$missing)->whereNull('missing_since')->pluck('product_id')->unique();
        DB::table('catalog_source_products')->where('source',$source)->whereIn('product_id',$missing)->whereNull('missing_since')->update(['missing_since'=>now()]);
        foreach ($newlyMissing as $id) CatalogHistory::record($id,'missing',[],[],$source,$batch);
        // Preserve another source's more recent stock update.
        $latest=DB::table('catalog_import_rows')->selectRaw('MAX(id) as id')->whereIn('product_id',$missing)->whereIn('operation',['updated','created_drafts','existing_untouched'])->groupBy('product_id');
        $owned=DB::table('catalog_import_rows as r')->join('catalog_import_batches as b','b.id','=','r.batch_id')->whereIn('r.id',$latest)->where('b.source',$source)->pluck('r.product_id');
        foreach (DB::table('products')->whereIn('id',$owned)->get() as $product) {
            $changes=[];
            foreach (['availability','stock_free','stock_transit'] as $field) if ($product->$field !== '0') $changes[$field]=['before'=>$product->$field,'after'=>'0'];
            if ($changes) CatalogHistory::record($product->id,'stock_cleared',$changes,[],$source,$batch);
        }
        return DB::table('products')->whereIn('id',$owned)->where(fn($q)=>$q->where('availability','!=','0')->orWhereNull('availability')->orWhere('stock_free','!=','0')->orWhereNull('stock_free')->orWhere('stock_transit','!=','0')->orWhereNull('stock_transit'))
            ->update(['availability'=>'0','stock_free'=>'0','stock_transit'=>'0','updated_at'=>now()]);
    }
}
