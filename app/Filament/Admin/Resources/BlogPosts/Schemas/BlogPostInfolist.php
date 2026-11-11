<?php

namespace App\Filament\Admin\Resources\BlogPosts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BlogPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title')->label('Заголовок'),
                TextEntry::make('slug')->label('URL'),
                ImageEntry::make('featured_image')->label('Изображение'),
                IconEntry::make('is_published')->label('Опубликована')
                    ->boolean(),
                TextEntry::make('published_at')->label('Дата публикации')
                    ->dateTime(),
                TextEntry::make('seo_title')->label('SEO заголовок'),
                TextEntry::make('seo_description')->label('SEO описание'),
                TextEntry::make('created_at')->label('Создана')
                    ->dateTime(),
                TextEntry::make('updated_at')->label('Обновлена')
                    ->dateTime(),
            ]);
    }
}
