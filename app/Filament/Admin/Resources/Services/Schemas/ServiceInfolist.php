<?php

namespace App\Filament\Admin\Resources\Services\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('category.name')->label('Категория'),
                TextEntry::make('name')->label('Название'),
                TextEntry::make('slug')->label('URL'),
                TextEntry::make('icon')->label('Иконка'),
                TextEntry::make('price_from')->label('Цена от')
                    ->numeric(),
                TextEntry::make('price_type')->label('Тип цены'),
                TextEntry::make('sort_order')->label('Порядок')
                    ->numeric(),
                IconEntry::make('is_active')->label('Активна')
                    ->boolean(),
                IconEntry::make('is_featured')->label('Рекомендуемая')
                    ->boolean(),
                TextEntry::make('seo_title')->label('SEO заголовок'),
                TextEntry::make('seo_description')->label('SEO описание'),
                TextEntry::make('created_at')->label('Создана')
                    ->dateTime(),
                TextEntry::make('updated_at')->label('Обновлена')
                    ->dateTime(),
            ]);
    }
}
