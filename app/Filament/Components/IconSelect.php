<?php

namespace App\Filament\Components;

use App\Helpers\IconHelper;
use Filament\Forms\Components\Select;

class IconSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->options(IconHelper::getAvailableIcons())
            ->searchable()
            ->allowHtml()
            ->getOptionLabelUsing(function ($value) {
                if (empty($value)) {
                    return null;
                }
                
                $options = IconHelper::getAvailableIcons();
                $label = $options[$value] ?? $value;
                
                if (IconHelper::isFileIcon($value)) {
                    // Для SVG файлов показываем превью
                    $iconPath = IconHelper::getIconPath($value);
                    return "<div class='flex items-center gap-2'>
                        <img src='{$iconPath}' class='w-5 h-5' alt='{$label}'>
                        <span>{$label}</span>
                    </div>";
                } else {
                    // Для Heroicons показываем иконку
                    return "<div class='flex items-center gap-2'>
                        <x-icon name='{$value}' class='w-5 h-5' />
                        <span>{$label}</span>
                    </div>";
                }
            })
            ->getSearchResultsUsing(function (string $search) {
                $options = IconHelper::getAvailableIcons();
                
                return collect($options)
                    ->filter(function ($label, $value) use ($search) {
                        return str_contains(strtolower($label), strtolower($search)) ||
                               str_contains(strtolower($value), strtolower($search));
                    })
                    ->take(50)
                    ->all();
            });
    }
}
