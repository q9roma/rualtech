<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Проверяем иконки услуг:\n";

$services = \App\Models\Service::all(['id', 'name', 'icon']);

foreach ($services as $service) {
    echo "ID: {$service->id}, Услуга: {$service->name}, Иконка: " . ($service->icon ?? 'не задана') . "\n";
    
    if ($service->icon && str_ends_with($service->icon, '.svg')) {
        $oldIcon = $service->icon;
        $newIcon = str_replace('.svg', '', $oldIcon);
        
        // Маппинг для конкретных случаев
        $mapping = [
            'computer' => 'computer-desktop',
            'cloud' => 'cloud',
            'security' => 'shield-check',
            'tools' => 'wrench-screwdriver',
            'puzzle-piece' => 'puzzle-piece',
        ];
        
        $newIcon = $mapping[$newIcon] ?? $newIcon;
        
        $service->icon = $newIcon;
        $service->save();
        
        echo "  → Исправлено с '{$oldIcon}' на '{$newIcon}'\n";
    }
}
