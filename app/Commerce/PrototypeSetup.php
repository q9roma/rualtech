<?php

namespace App\Commerce;

use Illuminate\Support\Facades\DB;
use Lunar\Models\{Channel, Country, Currency, CustomerGroup, Language, ProductType, TaxClass, TaxZone};

class PrototypeSetup
{
    public function run(): void
    {
        DB::transaction(function () {
            Channel::firstOrCreate(['handle' => 'altech-prototype'], ['name' => 'Алтех — прототип', 'default' => true, 'url' => config('app.url')]);
            Language::firstOrCreate(['code' => 'ru'], ['name' => 'Русский', 'default' => true]);
            Currency::firstOrCreate(['code' => 'RUB'], ['name' => 'Российский рубль', 'exchange_rate' => 1, 'decimal_places' => 2, 'default' => true, 'enabled' => true]);
            CustomerGroup::firstOrCreate(['handle' => 'altech-guests'], ['name' => 'Посетители', 'default' => true]);
            ProductType::firstOrCreate(['name' => 'Алтех — прайс']);
            TaxClass::firstOrCreate(['name' => 'Прайсовая цена — прототип'], ['default' => false]);
            TaxZone::firstOrCreate(['name' => 'Прототип без выделения налогов'], ['zone_type' => 'country', 'price_display' => 'tax_inclusive', 'default' => true, 'active' => true]);
            Country::firstOrCreate(['iso2' => 'RU'], ['name' => 'Russia', 'iso3' => 'RUS', 'phonecode' => '7', 'capital' => 'Moscow', 'currency' => 'RUB', 'native' => 'Россия', 'emoji' => '🇷🇺', 'emoji_u' => 'U+1F1F7 U+1F1FA']);
        });
    }
}
