<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('category')->label('Категория')->maxLength(255),
                TextInput::make('brand')->label('Бренд')->maxLength(255),
                TextInput::make('sku')->label('Артикул')->maxLength(255),
                TextInput::make('name')->label('Наименование')->required()->maxLength(500)->columnSpanFull(),
                TextInput::make('slug')
                    ->label('URL (slug)')
                    ->maxLength(255)
                    ->helperText('Оставьте пустым — сформируется из названия и артикула.')
                    ->nullable()
                    ->unique('products', 'slug')
                    ->columnSpanFull(),
                TextInput::make('availability')->label('Наличие')->maxLength(100),
                TextInput::make('price')->label('Цена')->numeric()->prefix('₽'),
                TextInput::make('currency')->label('Валюта')->default('RUB')->maxLength(10),
                Toggle::make('is_active')->label('Активен')->default(true),
            ]);
    }
}
