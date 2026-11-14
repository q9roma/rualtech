<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

class MicrosoftBrandZone
{
    public static function tabs(): Collection
    {
        return collect([
            ['value' => 'windows', 'label' => 'Windows', 'search' => 'Windows'],
            ['value' => '365', 'label' => 'Microsoft 365', 'search' => '365'],
            ['value' => 'office', 'label' => 'Office', 'search' => 'Office'],
            ['value' => 'server', 'label' => 'Windows Server', 'search' => 'Windows Server'],
        ])->map(function ($tab) {
            $query = CatalogDirectory::filter(Product::active(), 'Программное обеспечение')
                ->where('brand', 'Microsoft')->search($tab['search']);
            if ($tab['value'] === 'windows') $query->where('name', 'not like', '%Server%');
            if ($tab['value'] === 'office') $query->where('name', 'not like', '%365%');
            return [
                ...$tab,
                'url' => route('products.index', ['category' => 'Программное обеспечение', 'brands' => ['Microsoft'], 'search' => $tab['search']]),
                'products' => $query->with('catalogCategory')->orderBy('id')->limit(4)->get()->map(Storefront::cartProduct(...)),
            ];
        });
    }
}
