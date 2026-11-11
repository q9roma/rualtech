<?php
// Local end-to-end smoke check: upload current workbook, prepare in the real queue,
// then run with --apply to verify an unchanged file without changing catalog content.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{CatalogPriceUpload, User};
use App\Jobs\ProcessCatalogPrice;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

if (! $app->environment('local')) throw new RuntimeException('Local environment only');
$mode = $argv[1] ?? '';
if ($mode === '--upload') {
    $file = $argv[2];
    $upload = CatalogPriceUpload::create(['id' => (string) Str::uuid(), 'user_id' => User::where('is_admin', true)->firstOrFail()->id,
        'name' => basename($file), 'status' => 'queued', 'file_hash' => hash_file('sha256', $file)]);
    $disk = Storage::disk('local');
    $disk->put($upload->directory().'/source.xlsx', file_get_contents($file));
    $disk->put($upload->directory().'/smoke-before.json', DB::table('products')->orderBy('id')->get()->toJson());
    ProcessCatalogPrice::dispatch($upload->id);
    echo $upload->id;
} else {
    $upload = CatalogPriceUpload::findOrFail($argv[2]);
    if ($mode === '--apply') {
        if ($upload->status !== 'ready' || $upload->summary['updated'] || $upload->summary['created_drafts']) throw new RuntimeException('Only an unchanged ready preview can be smoke-applied');
        $upload->update(['status' => 'applying']);
        ProcessCatalogPrice::dispatch($upload->id, true);
    }
    $same = Storage::disk('local')->exists($upload->directory().'/smoke-before.json')
        ? Storage::disk('local')->get($upload->directory().'/smoke-before.json') === DB::table('products')->orderBy('id')->get()->toJson()
        : null; // An admin-uploaded file has no CLI smoke snapshot: unknown is not a detected change.
    echo json_encode(['id' => $upload->id, 'status' => $upload->status, 'summary' => $upload->summary, 'products_unchanged' => $same, 'error' => $upload->error], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
