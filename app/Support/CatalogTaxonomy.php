<?php

namespace App\Support;

use App\Models\ProductCategory;

class CatalogTaxonomy
{
    public static function seed(): void
    {
        $tree = json_decode(file_get_contents(config_path('catalog-taxonomy.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($tree as $index => $node) {
            // Existing IDs, names, slugs and manual ordering always survive re-runs.
            $root = ProductCategory::firstOrCreate(['code' => $node['code']], [
                'name' => $node['name'], 'slug' => $node['code'], 'sort_order' => $index,
            ]);
            foreach ($node['children'] as $position => $child) {
                ProductCategory::firstOrCreate(['code' => $child['code']], [
                    'name' => $child['name'], 'slug' => $child['code'], 'parent_id' => $root->id, 'sort_order' => $position,
                ]);
            }
        }
    }

    public static function categoryFor(string $section, string $subcategory): ?ProductCategory
    {
        // Resolve the versioned names from the classification to immutable codes;
        // staff may rename a category without breaking subsequent imports.
        $tree = json_decode(file_get_contents(config_path('catalog-taxonomy.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($tree as $root) {
            if ($root['name'] !== $section) continue;
            foreach ($root['children'] as $child) {
                if ($child['name'] === $subcategory) return ProductCategory::where('code', $child['code'])->first();
            }
        }
        return null;
    }
}
