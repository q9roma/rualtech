<?php

namespace App\Filament\Admin\Resources\Services\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use App\Helpers\IconHelper;
use App\Models\ServiceCategory;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_category_id')
                    ->label('Категория услуги')
                    ->options(ServiceCategory::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                
                TextInput::make('name')
                    ->label('Название услуги')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                
                TextInput::make('slug')
                    ->label('URL адрес')
                    ->required()
                    ->unique(ignoreRecord: true),
                
                Textarea::make('short_description')
                    ->label('Краткое описание')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull()
                    ->helperText('Краткое описание для карточек и превью'),
                
                RichEditor::make('description')
                    ->label('Полное описание')
                    ->required()
                    ->columnSpanFull(),
                
                TextInput::make('price_from')
                    ->label('Цена от')
                    ->numeric()
                    ->suffix('₽'),
                
                Select::make('price_type')
                    ->label('Тип цены')
                    ->options([
                        'от' => 'от',
                        'за час' => 'за час',
                        'фиксированная' => 'фиксированная',
                        'по запросу' => 'по запросу',
                    ])
                    ->default('от')
                    ->required(),
                
                Select::make('icon')
                    ->label('Иконка услуги')
                    ->options(IconHelper::getAvailableIcons())
                    ->searchable()
                    ->placeholder('Выберите иконку')
                    ->allowHtml()
                    ->getOptionLabelUsing(function ($value) {
                        if (empty($value)) return null;
                        
                        $options = IconHelper::getAvailableIcons();
                        $label = $options[$value] ?? $value;
                        
                        if (str_ends_with($value, '.svg')) {
                            $iconUrl = asset('icons/' . $value);
                            return "<div style='display: flex; align-items: center; gap: 8px;'><img src='{$iconUrl}' style='width: 16px; height: 16px;' alt='Icon'><span>{$label}</span></div>";
                        } else {
                            return "<div style='display: flex; align-items: center; gap: 8px;'><div style='width: 16px; height: 16px; background: #e5e7eb; border-radius: 2px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #6b7280;'>H</div><span>{$label}</span></div>";
                        }
                    })
                    ->getSearchResultsUsing(function (string $search) {
                        $options = IconHelper::getAvailableIcons();
                        
                        return collect($options)
                            ->filter(function ($label, $value) use ($search) {
                                return str_contains(strtolower($label), strtolower($search)) ||
                                       str_contains(strtolower($value), strtolower($search));
                            })
                            ->take(20)
                            ->all();
                    }),
                
                TagsInput::make('features')
                    ->label('Особенности услуги')
                    ->placeholder('Добавить особенность...')
                    ->columnSpanFull(),
                
                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0),
                
                Toggle::make('is_active')
                    ->label('Активна')
                    ->default(true),
                
                Toggle::make('is_featured')
                    ->label('Рекомендуемая')
                    ->default(false)
                    ->helperText('Отображается в блоке рекомендуемых услуг'),
                
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
