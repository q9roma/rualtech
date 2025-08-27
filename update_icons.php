<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Обновляем иконки в базе данных...\n";

$updates = [
    2 => 'wrench-screwdriver', // Техническая поддержка
    3 => 'server', // Виртуализация (заменяем server-stack на server)
    4 => 'server', // Сборка серверов
    5 => 'computer-desktop', // Рабочие станции
    6 => 'signal', // Сетевые решения
    7 => 'shield-check', // Информационная безопасность
];

foreach ($updates as $id => $newIcon) {
    $category = \App\Models\ServiceCategory::find($id);
    if ($category) {
        $oldIcon = $category->icon;
        $category->icon = $newIcon;
        $category->save();
        echo "ID {$id}: {$category->name} - обновлено с '{$oldIcon}' на '{$newIcon}'\n";
    }
}

echo "\nОбновленные иконки:\n";
$categories = \App\Models\ServiceCategory::all(['id', 'name', 'icon']);
foreach ($categories as $category) {
    echo "ID: {$category->id}, Категория: {$category->name}, Иконка: " . ($category->icon ?? 'не задана') . "\n";
}
