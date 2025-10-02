<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ServiceCategory;

echo "Тест склонения продуктов:\n";

function formatProductsCount($count) {
    if ($count % 10 == 1 && $count % 100 != 11) {
        return $count . ' продукт';
    } elseif (in_array($count % 10, [2, 3, 4]) && !in_array($count % 100, [12, 13, 14])) {
        return $count . ' продукта';
    } else {
        return $count . ' продуктов';
    }
}

for ($i = 0; $i <= 25; $i++) {
    echo "Число $i: " . formatProductsCount($i) . "\n";
}

echo "\nРеальные данные из базы:\n";

$categories = ServiceCategory::withCount(['services' => function($query) {
    $query->where('is_active', true);
}])->get();

foreach ($categories as $cat) {
    echo $cat->name . ': ' . formatProductsCount($cat->services_count) . "\n";
}
