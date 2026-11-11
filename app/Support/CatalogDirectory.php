<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CatalogDirectory
{
    public static function filter(Builder $query, string $value): Builder
    {
        $category = ProductCategory::where('slug', $value)->orWhere('name', $value)->first();
        if (! $category) return $query->whereIn('category', self::categoryValues($value));
        $children = $category->children()->where('is_active', true)->get();
        $ids = [$category->id, ...$children->pluck('id')];
        $names = [$category->name, ...$children->pluck('name')];
        return $query->where(fn ($q) => $q->whereIn('catalog_category_id', $ids)
            ->orWhere(fn ($q) => $q->whereNull('catalog_category_id')->whereIn('category', $names)));
    }

    public static function categoryTone(?string $category): string
    {
        $category = trim($category ?? '');
        foreach (config('storefront.catalog_subcategories', []) as $parent => $children) {
            if (in_array($category, $children, true)) {
                $category = $parent;
                break;
            }
        }

        return config('storefront.category_tones', [])[$category] ?? 'neutral';
    }

    public static function categoryValues(string $name): array
    {
        return [$name, ...(config('storefront.catalog_subcategories', [])[$name] ?? [])];
    }

    public static function categories(bool $includeEmpty = false): Collection
    {
        if (ProductCategory::exists()) {
            $roots = ProductCategory::whereNull('parent_id')->where('is_active', true)->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])->orderBy('sort_order')->get();
            $linked = Product::active()->whereNotNull('catalog_category_id')->selectRaw('catalog_category_id, COUNT(*) as n')->groupBy('catalog_category_id')->pluck('n', 'catalog_category_id');
            $legacy = Product::active()->whereNull('catalog_category_id')->selectRaw('category, COUNT(*) as n')->groupBy('category')->pluck('n', 'category');
            $result = $roots->map(function ($root) use ($linked, $legacy, $includeEmpty) {
                $children = $root->children->map(fn ($c) => [
                    'name' => $c->name, 'url' => route('catalog.category', $c->slug),
                    'count' => (int) ($linked[$c->id] ?? 0) + (int) ($legacy[$c->name] ?? 0),
                ])->filter(fn ($c) => $includeEmpty || $c['count'] > 0)->values();
                return ['category' => $root->name, 'url' => route('catalog.category', $root->slug), 'children' => $children,
                    'products_count' => (int) ($linked[$root->id] ?? 0) + (int) ($legacy[$root->name] ?? 0) + $children->sum('count')];
            })->filter(fn ($r) => $includeEmpty || $r['products_count'] > 0)->values();
            // Keep previously published legacy products accessible without editing them.
            $known = ProductCategory::pluck('name');
            foreach ($legacy as $name => $count) if ($name && ! $known->contains($name)) {
                $result->push(['category' => $name, 'products_count' => (int) $count, 'children' => [], 'url' => route('products.index', ['category' => $name])]);
            }
            return $result;
        }
        $counts = Product::query()->active()
            ->whereNotNull('category')->where('category', '!=', '')
            ->selectRaw('category, COUNT(*) as products_count')
            ->groupBy('category')->orderBy('category')
            ->pluck('products_count', 'category');

        return collect(config('storefront.catalog_categories', []))
            ->merge($counts->keys()->diff(collect(config('storefront.catalog_subcategories', []))->flatten()))->unique()->values()
            ->map(fn ($name) => [
                'category' => $name,
                'products_count' => (int) collect(self::categoryValues($name))->sum(fn ($value) => $counts[$value] ?? 0),
            ]);
    }
}
