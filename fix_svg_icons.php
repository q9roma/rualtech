<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Исправляем оставшиеся .svg в иконках...\n";

$categories = \App\Models\ServiceCategory::all();

foreach ($categories as $category) {
    if ($category->icon && str_ends_with($category->icon, '.svg')) {
        $oldIcon = $category->icon;
        // Убираем .svg и заменяем на подходящую heroicon
        $newIcon = str_replace('.svg', '', $oldIcon);
        
        // Маппинг для конкретных случаев
        $mapping = [
            'puzzle-piece' => 'puzzle-piece',
            'computer' => 'computer-desktop',
            'cloud' => 'cloud',
            'security' => 'shield-check',
            'tools' => 'wrench-screwdriver',
        ];
        
        $newIcon = $mapping[$newIcon] ?? $newIcon;
        
        $category->icon = $newIcon;
        $category->save();
        
        echo "ID {$category->id}: {$category->name} - исправлено с '{$oldIcon}' на '{$newIcon}'\n";
    }
}

echo "Готово!\n";
