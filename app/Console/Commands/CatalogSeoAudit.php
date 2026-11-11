<?php

namespace App\Console\Commands;

use App\Models\{Product, ProductCategory};
use App\Support\{ApprovedProductMedia, Storefront};
use Illuminate\Console\Command;

class CatalogSeoAudit extends Command
{
    protected $signature = 'catalog:seo-audit';
    protected $description = 'Read-only catalog content audit; writes a local JSON report, never changes products';

    public function handle(): int
    {
        $groups = ['published' => [], 'draft' => []];
        $names = []; $identities = []; $descriptions = []; $categories = [];
        foreach (Product::with('catalogCategory.parent')->orderBy('id')->cursor() as $product) {
            $description = trim($product->description ?? '') ?: (Storefront::supplierDescription($product) ?? '');
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($description)));
            $issues = [];
            if (!$text) $issues[] = 'missing_description';
            if (!count($product->specifications ?? [])) $issues[] = 'missing_specifications';
            if (!trim($product->sku ?? '')) $issues[] = 'missing_sku';
            if (!trim($product->brand ?? '')) $issues[] = 'missing_brand';
            if (!$product->price || (float) $product->price <= 0) $issues[] = 'price_on_request';
            if (!trim($product->availability ?? '')) $issues[] = 'unknown_availability';
            if (!$product->catalog_category_id) $issues[] = 'no_catalog_category';
            elseif (!$product->catalogCategory || !$product->catalogCategory->is_active || ($product->catalogCategory->parent_id && !$product->catalogCategory->parent?->is_active)) $issues[] = 'inactive_catalog_category';
            $photos = $product->photos !== null ? $product->photos : ApprovedProductMedia::images($product);
            if (!$photos) $issues[] = 'no_production_photos';
            $row = ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku,
                'url' => route('products.show', $product), 'issues' => $issues];
            $groups[$product->is_active ? 'published' : 'draft'][] = $row;
            if (!$product->is_active) continue;
            $normalize = fn ($value) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
            $names[$normalize($product->name)][] = $product->id;
            if ($product->sku) $identities[$normalize(($product->brand ?? '').'|'.$product->sku)][] = $product->id;
            if ($text) $descriptions[hash('sha256', $normalize($text))][] = $product->id;
            $category = $product->catalogCategory?->name ?? $product->category ?? 'Без категории';
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }
        $summary = [];
        foreach ($groups as $status => $rows) {
            $counts = [];
            foreach ($rows as $row) foreach ($row['issues'] as $issue) $counts[$issue] = ($counts[$issue] ?? 0) + 1;
            $summary[$status] = ['total' => count($rows), 'issues' => $counts];
        }
        arsort($categories);
        $landingPlan = [];
        foreach (config('catalog-guides', []) as $name => $guide) {
            $category = ProductCategory::where('name', $name)->where('is_active', true)->first();
            if (!$category) continue;
            $landingPlan[] = ['category' => $name, 'url' => route('catalog.category', $category->slug),
                'published_products' => $categories[$name] ?? 0, 'query_hypotheses' => $guide['queries'],
                'frequency' => null, 'region' => null,
                'next_action' => 'Проверить запросы в Вордстате по региону продаж; заполнить характеристики и подтвердить фотографии моделей. Не создавать дополнительные страницы фильтров до проверки спроса и ассортимента.'];
        }
        usort($landingPlan, fn ($a, $b) => $b['published_products'] <=> $a['published_products']);
        $duplicates = fn ($items) => array_values(array_filter($items, fn ($ids) => count($ids) > 1));
        $report = ['generated_at' => now()->toIso8601String(), 'summary' => $summary,
            'method' => ['scope' => 'All local products; published and draft counted separately.',
                'description' => 'Own description or supplier description fallback.',
                'specifications' => 'Additional structured specifications only; brand, SKU and availability are counted separately.',
                'photos' => 'Assigned gallery or approved production media. Local experimental overlays excluded. Assigned gallery file integrity not checked.',
                'duplicates' => 'Candidates for manual review, never an instruction to merge supply variants.',
                'demand_source' => 'https://yandex.ru/support2/wordstat/ru/interface/new',
                'priority' => 'Assortment coverage, not measured search volume.'],
            'landing_plan' => $landingPlan,
            'published_by_category' => $categories,
            'duplicate_names' => $duplicates($names), 'duplicate_brand_sku' => $duplicates($identities),
            'duplicate_descriptions' => $duplicates($descriptions),
            'taxonomy' => ProductCategory::orderBy('sort_order')->get(['id', 'name', 'slug', 'parent_id', 'is_active'])->toArray(),
            'products' => $groups];
        $path = storage_path('app/private/catalog-seo-audit.json');
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info($path);
        return self::SUCCESS;
    }
}
