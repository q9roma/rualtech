<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ServiceCategory;

echo "Проверка количества продуктов по категориям:\n";

$categories = ServiceCategory::withCount('services')->get();

foreach ($categories as $cat) {
    echo $cat->name . ': ' . $cat->services_count . " продуктов\n";
}

echo "\nАктивные продукты по категориям:\n";

$categories = ServiceCategory::withCount(['services' => function($query) {
    $query->where('is_active', true);
}])->get();

foreach ($categories as $cat) {
    echo $cat->name . ': ' . $cat->services_count . " активных продуктов\n";
}
