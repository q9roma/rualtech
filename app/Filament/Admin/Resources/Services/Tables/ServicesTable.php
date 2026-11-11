<?php

namespace App\Filament\Admin\Resources\Services\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('category.name')->label('Категория')->sortable()->visibleFrom('md'),
                TextColumn::make('slug')->toggleable(isToggledHiddenByDefault: true)
                    ->label('URL')
                    ->searchable(),
                TextColumn::make('icon')->toggleable(isToggledHiddenByDefault: true)
                    ->label('Иконка')
                    ->searchable(),
                TextColumn::make('price_from')
                    ->label('Цена от')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_type')
                    ->label('Тип цены')
                    ->searchable(),
                TextColumn::make('sort_order')->toggleable(isToggledHiddenByDefault: true)
                    ->label('Порядок')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                IconColumn::make('is_featured')
                    ->label('Рекомендуемая')
                    ->boolean(),
                TextColumn::make('seo_title')->toggleable(isToggledHiddenByDefault: true)
                    ->label('SEO заголовок')
                    ->searchable(),
                TextColumn::make('seo_description')->toggleable(isToggledHiddenByDefault: true)
                    ->label('SEO описание')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
