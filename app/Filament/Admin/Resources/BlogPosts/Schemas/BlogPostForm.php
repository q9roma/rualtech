<?php

namespace App\Filament\Admin\Resources\BlogPosts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Заголовок')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                    
                TextInput::make('slug')
                    ->label('URL адрес')
                    ->required()
                    ->unique(ignoreRecord: true),
                    
                Textarea::make('excerpt')
                    ->label('Краткое описание')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Краткое описание для превью статьи'),
                    
                RichEditor::make('content')
                    ->label('Содержание')
                    ->required()
                    ->columnSpanFull(),
                    
                FileUpload::make('featured_image')
                    ->label('Изображение')
                    ->image()
                    ->directory('blog')
                    ->helperText('Основное изображение для статьи'),
                    
                TagsInput::make('tags')
                    ->label('Теги')
                    ->columnSpanFull(),
                    
                Toggle::make('is_published')
                    ->label('Опубликовано')
                    ->default(false)
                    ->helperText('При включении автоматически устанавливается дата публикации'),
                    
                DateTimePicker::make('published_at')
                    ->label('Дата публикации')
                    ->helperText('Оставьте пустым для автоматической установки при публикации'),
                    
                TextInput::make('seo_title')
                    ->label('SEO заголовок')
                    ->maxLength(60),
                    
                Textarea::make('seo_description')
                    ->label('SEO описание')
                    ->rows(3)
                    ->maxLength(160),
            ])
            ->columns(2);
    }
}
