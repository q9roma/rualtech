<?php

// Read-only input for catalog_preview.py. Redirect stdout to a private JSON file.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = Illuminate\Support\Facades\DB::table('products')->orderBy('id')->get([
    'id', 'brand', 'sku', 'name', 'price', 'availability', 'category', 'is_active', 'updated_at',
]);

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
