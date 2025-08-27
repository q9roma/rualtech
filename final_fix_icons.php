<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Финальное исправление иконок...\n";

$correctIcons = [
    1 => 'puzzle-piece', // ИТ
    2 => 'wrench-screwdriver', // Техническая поддержка  
    3 => 'cloud', // Виртуализация
    4 => 'server', // Сборка серверов
    5 => 'computer-desktop', // Рабочие станции
    6 => 'signal', // Сетевые решения
    7 => 'shield-check', // Информационная безопасность
];

foreach ($correctIcons as $id => $icon) {
    $category = \App\Models\ServiceCategory::find($id);
    if ($category) {
        $category->icon = $icon;
        $category->save();
        echo "ID {$id}: {$category->name} - установлена иконка '{$icon}'\n";
    }
}

echo "Готово!\n";
