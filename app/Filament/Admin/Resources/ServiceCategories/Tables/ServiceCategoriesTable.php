<?php

namespace App\Filament\Admin\Resources\ServiceCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

class ServiceCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Обложка')
                    ->circular()
                    ->size(50),
                    
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('slug')
                    ->label('URL')
                    ->searchable()
                    ->copyable()
                    ->limit(30),
                    
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->sortable(),
                    
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                    
                TextColumn::make('custom_services_text')
                    ->label('Услуг')
                    ->html()
                    ->formatStateUsing(function ($record) {
                        $count = \App\Models\Service::where('service_category_id', $record->id)
                            ->where('is_active', true)
                            ->count();
                        
                        $text = '';
                        if ($count % 10 == 1 && $count % 100 != 11) {
                            $text = $count . ' услуга';
                        } elseif (in_array($count % 10, [2, 3, 4]) && !in_array($count % 100, [12, 13, 14])) {
                            $text = $count . ' услуги';
                        } else {
                            $text = $count . ' услуг';
                        }
                        
                        return '<span>' . $text . '</span>';
                    }),
                    
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}
