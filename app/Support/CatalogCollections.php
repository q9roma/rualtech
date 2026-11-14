<?php

namespace App\Support;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;

class CatalogCollections
{
    public static function workplace(): array
    {
        return [
            'key' => 'workplace',
            'name' => 'Рабочее место',
            'description' => 'Компьютеры, мониторы, клавиатуры и мыши — всё для рабочего места в одной подборке.',
            'categories' => [
                'computers' => 'Компьютеры и ноутбуки',
                'peripherals-monitory' => 'Мониторы',
                'peripherals-klaviatury' => 'Клавиатуры',
                'peripherals-mysi-i-trekpady' => 'Мыши и трекпады',
                'peripherals-komplekty-klaviatura-i-mys' => 'Комплекты клавиатура и мышь',
            ],
        ];
    }

    public static function filter(Builder $query, array $collection): Builder
    {
        $categories = ProductCategory::whereIn('code', array_keys($collection['categories']))->with('children', 'parent')->get()->keyBy('code');
        $ids = [];
        $names = [];
        foreach ($collection['categories'] as $code => $fallbackName) {
            $category = $categories->get($code);
            if ($category) {
                if (! $category->is_active || ($category->parent_id && ! $category->parent?->is_active)) continue;
                $included = collect([$category])->merge($category->children->where('is_active', true));
                $ids = [...$ids, ...$included->pluck('id')];
                $names = [...$names, ...$included->pluck('name')];
            } else {
                $names[] = $fallbackName;
                if ($code === 'computers') $names = [...$names, 'Мини-ПК', 'Моноблоки', 'Настольные компьютеры', 'Ноутбуки'];
            }
        }
        return $query->where(fn ($q) => $q->whereIn('catalog_category_id', $ids)
            ->orWhere(fn ($q) => $q->whereNull('catalog_category_id')->whereIn('category', array_unique($names))));
    }
}
