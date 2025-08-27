<?php

namespace App\Filament\Admin\Resources\Pages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                            
                        TextInput::make('slug')
                            ->label('ЧПУ (URL)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Автоматически создается из заголовка'),
                            
                        RichEditor::make('content')
                            ->label('Содержимое')
                            ->columnSpanFull(),
                            
                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true),
                    ])
                    ->columns(2),
                    
                Section::make('SEO настройки')
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('SEO заголовок')
                            ->helperText('Если не заполнено, будет использован основной заголовок'),
                            
                        TextInput::make('seo_description')
                            ->label('SEO описание')
                            ->helperText('Описание для поисковых систем'),
                    ])
                    ->columns(1)
                    ->collapsed(),
            ]);
    }
}
