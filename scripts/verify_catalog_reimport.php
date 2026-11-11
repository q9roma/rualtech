<?php

// Executes two authorized synchronization passes and retains before/after evidence.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Admin\CatalogDraftImport;

[$script, $input, $output] = $argv;
if (! is_dir($output) && ! mkdir($output, 0777, true)) throw new RuntimeException('Cannot create evidence directory');
$save = fn ($name, $data) => file_put_contents($output.'/'.$name.'.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$snapshot = function () {
    $tables = [];
    foreach (['products', 'product_categories', 'catalog_import_mappings', 'catalog_source_products'] as $table) {
        $tables[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }
    $files = [];
    foreach (['public/images', 'storage/app/public'] as $directory) {
        $path = base_path($directory);
        if (! is_dir($path)) continue;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) $files[$file->getPathname()] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($files);
    return ['tables' => $tables, 'media_files' => $files];
};
$before = $snapshot();
$save('before', $before);
echo 'Snapshot saved: '.count($before['tables']['products'])." products\n";
$rows = json_decode(file_get_contents($input), true, flags: JSON_THROW_ON_ERROR);
$results = [];
for ($pass = 1; $pass <= 2; $pass++) {
    echo "Starting pass $pass\n";
    $results[$pass] = app(CatalogDraftImport::class)->run($rows, 'altech-price', hash_file('sha256', $input), 'b2b-2026-11-08-v1', true);
    $after = $snapshot();
    $save('after-'.$pass, $after);
    $save('result-'.$pass, $results[$pass]);
    echo json_encode($results[$pass], JSON_UNESCAPED_UNICODE)."\n";
    if ($pass === 1) $first = $after;
}
$editable = array_flip(['price', 'currency', 'availability', 'stock_free', 'stock_transit', 'updated_at']);
$protected = fn ($snapshot) => array_map(fn ($row) => array_diff_key($row, $editable), $snapshot['tables']['products']);
$checks = [
    'same_product_ids_and_protected_fields' => $protected($before) === $protected($after),
    'category_tree_unchanged' => $before['tables']['product_categories'] === $after['tables']['product_categories'],
    'mappings_unchanged' => $before['tables']['catalog_import_mappings'] === $after['tables']['catalog_import_mappings'],
    'source_links_unchanged' => $before['tables']['catalog_source_products'] === $after['tables']['catalog_source_products'],
    'media_files_unchanged' => $before['media_files'] === $after['media_files'],
    'second_pass_products_identical' => $first['tables']['products'] === $after['tables']['products'],
    'no_new_products' => $results[1]['created_drafts'] === 0 && $results[2]['created_drafts'] === 0,
    'second_pass_no_updates' => $results[2]['updated'] === 0,
];
$report = ['checks' => $checks, 'results' => $results, 'product_count' => count($after['tables']['products']),
    'media_file_count' => count($after['media_files']),
    'product_media_columns' => array_values(array_filter(Schema::getColumnListing('products'), fn ($c) => preg_match('/image|photo|gallery|media/i', $c)))];
$save('verification', $report);
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
exit(in_array(false, $checks, true) ? 1 : 0);
