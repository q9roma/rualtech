<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Текущие иконки в базе данных:\n";

$categories = \App\Models\ServiceCategory::all(['id', 'name', 'icon']);

foreach ($categories as $category) {
    echo "ID: {$category->id}, Категория: {$category->name}, Иконка: " . ($category->icon ?? 'не задана') . "\n";
}
