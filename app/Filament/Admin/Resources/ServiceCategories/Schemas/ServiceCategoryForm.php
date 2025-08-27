<?php

namespace App\Filament\Admin\Resources\ServiceCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use App\Helpers\IconHelper;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServiceCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название категории')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                
                TextInput::make('slug')
                    ->label('URL адрес')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Автоматически генерируется из названия'),
                
                Textarea::make('description')
                    ->label('Описание')
                    ->rows(3)
                    ->columnSpanFull(),
                
                FileUpload::make('image')
                    ->label('Обложка категории')
                    ->image()
                    ->directory('categories')
                    ->visibility('public')
                    ->imageEditor()
                    ->imageCropAspectRatio('16:9')
                    ->imageResizeTargetWidth('800')
                    ->imageResizeTargetHeight('450')
                    ->helperText('Рекомендуемый размер: 800x450 пикселей')
                    ->columnSpanFull(),
                
                Select::make('icon')
                    ->label('Иконка категории')
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
                    })
                    ->helperText('Иконка для отображения в карточке категории'),
                
                TextInput::make('sort_order')
                    ->label('Порядок сортировки')
                    ->numeric()
                    ->default(0),
                
                Toggle::make('is_active')
                    ->label('Активна')
                    ->default(true),
                
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
