<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ServiceCategory;

echo "Тест склонения услуг:\n";

function formatServicesCount($count) {
    if ($count % 10 == 1 && $count % 100 != 11) {
        return $count . ' услуга';
    } elseif (in_array($count % 10, [2, 3, 4]) && !in_array($count % 100, [12, 13, 14])) {
        return $count . ' услуги';
    } else {
        return $count . ' услуг';
    }
}

for ($i = 0; $i <= 25; $i++) {
    echo "Число $i: " . formatServicesCount($i) . "\n";
}

echo "\nРеальные данные из базы:\n";

$categories = ServiceCategory::withCount(['services' => function($query) {
    $query->where('is_active', true);
}])->get();

foreach ($categories as $cat) {
    echo $cat->name . ': ' . formatServicesCount($cat->services_count) . "\n";
}
