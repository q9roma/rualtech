<?php

namespace App\Filament\Admin\Resources\BlogPosts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->wrap()->limit(70)
                    ->searchable(),
                TextColumn::make('slug')->toggleable(isToggledHiddenByDefault: true)
                    ->label('URL')
                    ->searchable(),
                ImageColumn::make('featured_image')
                    ->label('Изображение'),
                IconColumn::make('is_published')
                    ->label('Опубликован')
                    ->boolean(),
                TextColumn::make('published_at')
                    ->label('Дата публикации')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('seo_title')->toggleable(isToggledHiddenByDefault: true)
                    ->label('SEO заголовок')
                    ->searchable(),
                TextColumn::make('seo_description')->toggleable(isToggledHiddenByDefault: true)
                    ->label('SEO описание')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлен')
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
