<?php

namespace App\Helpers;

use Illuminate\Support\Facades\File;

class IconHelper
{
    /**
     * Получить список доступных иконок из каталога
     *
     * @return array
     */
    public static function getAvailableIcons(): array
    {
        // Простой список популярных Heroicons для IT-услуг
        return [
            'computer-desktop' => 'Компьютер',
            'server' => 'Сервер',
            'cloud' => 'Облако',
            'shield-check' => 'Защита',
            'wrench-screwdriver' => 'Инструменты',
            'signal' => 'Сеть',
            'code-bracket' => 'Код',
            'cog-6-tooth' => 'Настройки',
            'globe-alt' => 'Интернет',
            'device-phone-mobile' => 'Мобильные',
            'briefcase' => 'Бизнес',
            'chart-bar' => 'Аналитика',
            'cpu-chip' => 'Процессор',
            'wifi' => 'WiFi',
            'lock-closed' => 'Безопасность',
            'rocket-launch' => 'Быстро',
            'puzzle-piece' => 'Интеграция',
            'eye' => 'Мониторинг',
            'phone' => 'Поддержка',
            'building-office' => 'Офис',
        ];
    }
    
    /**
     * Получить путь к иконке
     *
     * @param string $icon
     * @return string
     */
    public static function getIconPath(string $icon): string
    {
        // Если это файл из каталога
        if (str_ends_with($icon, '.svg')) {
            return asset('icons/' . $icon);
        }
        
        // Если это Heroicon, возвращаем CSS класс
        return $icon;
    }
    
    /**
     * Проверить, является ли иконка файлом из каталога
     *
     * @param string $icon
     * @return bool
     */
    public static function isFileIcon(string $icon): bool
    {
        return str_ends_with($icon, '.svg');
    }
}
