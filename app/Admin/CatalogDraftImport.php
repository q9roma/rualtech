<?php

namespace App\Admin;

use App\Models\{Product, ProductCategory, CatalogImportMapping};
use App\Support\CatalogTaxonomy;
use Illuminate\Support\Facades\{DB, Validator};

/** Reviewed staging JSON -> drafts, with optional price/stock synchronization. */
class CatalogDraftImport
{
    public static function decisionIdentity(array $row): string
    {
        return hash('sha256',json_encode(array_map(fn($value)=>mb_strtolower(trim(preg_replace('/\s+/u',' ',(string)$value))),[$row['brand'] ?? '',$row['sku'] ?? '',$row['supplier_data']['EAN'] ?? '',self::identity($row['brand'] ?? '',$row['sku'] ?? '',$row['name'] ?? '')])));
    }
    public static function itemKey(string $source, array $row): string
    {
        $identity = $source === 'supplier-price-32'
            ? 'supplier-id:'.trim($row['supplier_id'] ?? '')
            : self::identity($row['brand'] ?? '', $row['sku'] ?? '', $row['name']);
        return hash('sha256', 'item:'.$source.':'.$identity);
    }
    public static function identity(string $brand, string $sku, string $name): string
    {
        preg_match('/формат поставки\s*[-—:]?\s*(.*?)(?:\s*\(вкл\.|$)/iu', $name, $match);
        $normal = fn ($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
        return hash('sha256', json_encode(array_map($normal, [$brand, $sku, $match[1] ?? '']), JSON_UNESCAPED_UNICODE));
    }

    public function run(array $rows, string $source, string $fileHash, string $version, bool $updateExisting = false): array
    {
        $rules = [
            'source' => 'required|string|max:100', 'hash' => 'required|regex:/^[a-f0-9]{64}$/',
            'version' => 'required|string|max:100', 'rows' => 'required|array|min:1|max:50000',
            'rows.*' => 'required|array', 'rows.*.row' => 'required|integer|min:2|distinct',
            'rows.*.brand' => 'present|nullable|string|max:255', 'rows.*.sku' => 'present|nullable|string|max:255',
            'rows.*.name' => 'required|string|max:2000', 'rows.*.source_category' => 'present|nullable|string|max:255',
            'rows.*.status' => 'required|in:include,review,exclude,request', 'rows.*.rule' => 'required|string|max:100',
            'rows.*.section' => 'present|nullable|string|max:255', 'rows.*.subcategory' => 'present|nullable|string|max:255',
            'rows.*.errors' => 'present|array', 'rows.*.errors.*' => 'string|max:255',
            'rows.*.stock' => 'required|array|size:3', 'rows.*.stock.*' => 'present|nullable|string|max:255',
            'rows.*.price' => 'nullable|numeric|gt:0|max:9999999999.99', 'rows.*.currency' => 'required|string|max:10',
            'rows.*.supplier_id' => 'sometimes|nullable|string|max:255',
            'rows.*.supplier_data' => 'sometimes|array',
        ];
        // Expand wildcard rules per row, avoiding quadratic expansion on full price lists.
        Validator::make(['rows' => $rows, 'source' => $source, 'hash' => $fileHash, 'version' => $version],
            array_filter($rules, fn ($key) => ! str_starts_with($key, 'rows.*'), ARRAY_FILTER_USE_KEY))->validate();
        $rowRules = [];
        foreach ($rules as $key => $rule) {
            if (str_starts_with($key, 'rows.*.')) $rowRules[substr($key, 7)] = str_replace('|distinct', '', $rule);
        }
        $seen = [];
        foreach ($rows as $index => $row) {
            Validator::make(is_array($row) ? $row : [], $rowRules)->validate();
            if (isset($seen[$row['row']])) throw \Illuminate\Validation\ValidationException::withMessages(['rows' => 'Номер строки повторяется.']);
            $seen[$row['row']] = true;
        }

        return DB::transaction(function () use ($rows, $source, $fileHash, $version, $updateExisting) {
            CatalogTaxonomy::seed();
            // Serialize catalog draft imports on a shared root row, including different files.
            ProductCategory::orderBy('id')->lockForUpdate()->firstOrFail();
            $batch = DB::table('catalog_import_batches')->insertGetId([
                'source' => $source, 'file_hash' => $fileHash, 'rules_version' => $version,
                'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $summary = ['batch_id' => $batch, 'rows' => count($rows), 'created_drafts' => 0, 'existing_untouched' => 0, 'updated' => 0, 'held' => 0, 'excluded' => 0, 'request_only' => 0];
            $existing = Product::all()->groupBy(fn ($p) => self::identity($p->brand ?? '', $p->sku ?? '', $p->name));
            $keys = array_map(fn ($r) => $source === 'supplier-price-32'
                ? hash('sha256', 'supplier-id:'.trim($r['supplier_id'] ?? ''))
                : self::identity($r['brand'] ?? '', $r['sku'] ?? '', $r['name']), $rows);
            $duplicates = array_count_values($keys);
            $mappingCache = [];
            $itemDecisions = CatalogImportMapping::where('source', $source)->where('rule_code', 'item-decision')->with('category.parent')->get()->keyBy('mapping_key');
            foreach ($rows as $index => $r) {
                $identity = $keys[$index];
                $mappingKey = hash('sha256', json_encode([$source, $r['source_category'], $r['rule']], JSON_UNESCAPED_UNICODE));
                if (! isset($mappingCache[$mappingKey])) {
                    $category = CatalogTaxonomy::categoryFor($r['section'] ?? '', $r['subcategory'] ?? '');
                    $mapping = CatalogImportMapping::firstOrCreate(['mapping_key' => $mappingKey], [
                        'source' => $source, 'source_category' => $r['source_category'] ?? '', 'rule_code' => $r['rule'],
                        'name' => mb_substr(($r['source_category'] ?: 'Без категории').' / '.$r['rule'], 0, 255),
                        // Data quality holds belong to rows, not the category rule.
                        'decision' => $r['status'] === 'review' && $category && count($r['errors']) ? 'include' : $r['status'],
                        'catalog_category_id' => $category?->id,
                    ]);
                    $mappingCache[$mappingKey] = $mapping;
                }
                $mapping = $itemDecisions->get(self::itemKey($source, $r)) ?? $mappingCache[$mappingKey];
                $decision = $mapping->decision;
                $issues = $r['errors'];
                if ($mapping->decision_identity && !hash_equals($mapping->decision_identity,self::decisionIdentity($r))) {
                    $issues[]='saved_decision_identity_changed';
                    $decision='review';
                }
                if ($source === 'supplier-price-32' && ! filled($r['supplier_id'] ?? null)) $issues[] = 'missing_supplier_id';
                $link = DB::table('catalog_source_products')->where('source', $source)->where('identity_hash', $identity)->first();
                if ($link && $source === 'supplier-price-32') {
                    $previous = json_decode($link->supplier_data ?? '{}', true);
                    $normal = fn($value) => mb_strtolower(trim(preg_replace('/\s+/u',' ',(string)$value)));
                    foreach (['Бренд'=>$r['brand'], 'Артикул'=>$r['sku'], 'EAN'=>$r['supplier_data']['EAN'] ?? ''] as $field=>$value) {
                        if (filled($previous[$field] ?? null) && $normal($previous[$field]) !== $normal($value)) $issues[] = 'supplier_identity_changed';
                    }
                }
                // A stored source identity survives editorial title/category changes.
                if ($link) $issues = array_values(array_diff($issues, ['title_changed_verify_identity']));
                foreach ($r['stock'] as $value) {
                    if ($value !== null && $value !== '' && ! preg_match('/^(?:>\s*)?\d+$/', $value)) $issues[] = 'invalid_stock';
                }
                if ($duplicates[$identity] > 1) $issues[] = 'ambiguous_source_identity';
                if (! filled($r['brand']) || ! filled($r['sku']) || ! $r['price'] || $r['currency'] !== 'RUB') $issues[] = 'invalid_identity_or_price';
                $category = $mapping->category;
                if (! $link && $decision === 'include' && (! $category?->parent_id || ! $category->is_active || ! $category->parent?->is_active)) $issues[] = 'missing_or_inactive_category';
                if ($issues && $decision === 'include') $decision = 'review';
                $productId = null;
                $changes = [];
                $operation = match ($decision) { 'exclude' => 'excluded', 'request' => 'request_only', default => 'held' };
                if ($decision === 'include') {
                    $link = DB::table('catalog_source_products')->where('source', $source)->where('identity_hash', $identity)->first();
                    $productIdentity = self::identity($r['brand'] ?? '', $r['sku'] ?? '', $r['name']);
                    $matches = $existing->get($productIdentity, collect());
                    if ($link) {
                        $productId = $link->product_id;
                        $operation = 'existing_untouched';
                    } elseif ($matches->count() > 1 || ($matches->count() === 1 && trim($matches->first()->name) !== $r['name'])) {
                        $decision = 'review';
                        $issues[] = 'verify_database_identity';
                    } else {
                        $product = $matches->first();
                        if (! $product) {
                            $product = Product::create([
                                'name' => $r['name'], 'brand' => $r['brand'], 'sku' => $r['sku'],
                                'category' => $category->name, 'catalog_category_id' => $category->id,
                                'source_category' => $r['source_category'], 'availability' => $r['stock'][0] === '' ? null : $r['stock'][0],
                                'stock_free' => $r['stock'][1] === '' ? null : $r['stock'][1],
                                'stock_transit' => $r['stock'][2] === '' ? null : $r['stock'][2],
                                'price' => $r['price'], 'currency' => $r['currency'], 'is_active' => false,
                            ]);
                            $operation = 'created_drafts';
                            DB::table('catalog_product_events')->where('product_id',$product->id)->where('kind','created')->whereNull('batch_id')->update(['source'=>$source,'batch_id'=>$batch]);
                            $existing->put($productIdentity, collect([$product]));
                        } else $operation = 'existing_untouched';
                        $productId = $product->id;
                        DB::table('catalog_source_products')->insert([
                            'source' => $source, 'identity_hash' => $identity, 'product_id' => $productId,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        CatalogHistory::record($productId, 'source_linked', [], ['supplier_id'=>$r['supplier_id'] ?? null], $source, $batch);
                    }
                }
                if ($productId && $source === 'supplier-price-32' && $decision === 'include') {
                    DB::table('catalog_source_products')->where('source', $source)->where('identity_hash', $identity)->update([
                        'supplier_id' => $r['supplier_id'],
                        'supplier_data' => json_encode($r['supplier_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ]);
                }
                if ($link) {
                    DB::table('catalog_source_products')->where('id',$link->id)->update(['last_seen_at'=>now()]);
                }
                if ($productId && $decision === 'include' && ($updateExisting || $operation === 'created_drafts')) {
                    if ($link?->missing_since) CatalogHistory::record($productId,'returned',[],[], $source,$batch);
                    DB::table('catalog_source_products')->where('source',$source)->where('identity_hash',$identity)->update([
                        'last_seen_at'=>now(),'last_validated_at'=>now(),'missing_since'=>null,
                    ]);
                }
                if ($updateExisting && $operation === 'existing_untouched' && $productId) {
                    $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();
                    $values = ['price' => $r['price'], 'currency' => $r['currency'],
                        'availability' => $r['stock'][0] === '' ? null : $r['stock'][0],
                        'stock_free' => $r['stock'][1] === '' ? null : $r['stock'][1],
                        'stock_transit' => $r['stock'][2] === '' ? null : $r['stock'][2]];
                    $product->fill($values);
                    foreach ($values as $field => $value) {
                        if ($product->isDirty($field)) $changes[$field] = ['before' => $product->getOriginal($field), 'after' => $product->$field];
                    }
                    if ($changes) {
                        // Query update deliberately bypasses saving hooks: only these fields may change.
                        DB::table('products')->where('id', $productId)->update([...$values, 'updated_at' => now()]);
                        $operation = 'updated';
                        CatalogHistory::record($productId,'price_updated',$changes,[],$source,$batch);
                    }
                }
                $summary[$operation]++;
                DB::table('catalog_import_rows')->insert([
                    'batch_id' => $batch, 'source_row' => $r['row'], 'decision' => $decision, 'operation' => $operation,
                    'product_id' => $productId, 'payload' => json_encode([...$r, 'changes' => $changes, 'import_issues' => array_values(array_unique($issues))], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('catalog_import_batches')->where('id', $batch)->update(['status' => 'completed', 'summary' => json_encode($summary), 'updated_at' => now()]);
            return $summary;
        });
    }
}
