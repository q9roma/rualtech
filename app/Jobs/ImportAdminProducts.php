<?php

namespace App\Jobs;

use App\Admin\{CsvImport, ImportPrice};
use App\Models\{Product, User};
use App\Notifications\AdminImportFinished;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\{Gate, Storage, Validator};

class ImportAdminProducts implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public int $userId, public string $id, public array $mapping) {}

    public function middleware(): array
    {
        // Do not run a redelivered long import alongside its original worker.
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('admin-import:'.$this->userId.':'.$this->id))->dontRelease()->expireAfter(660)];
    }

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        Gate::forUser($user)->authorize('create', Product::class);
        $dir = CsvImport::directory($this->userId, $this->id);
        $disk = Storage::disk('local');
        $meta = json_decode($disk->get($dir.'/meta.json'), true, flags: JSON_THROW_ON_ERROR);
        $errors = fopen('php://temp', 'w+');
        fwrite($errors, "\xEF\xBB\xBF");
        fputcsv($errors, [...$meta['headers'], 'Ошибка'], ';', '"', '');
        $success = $failed = 0;
        foreach (CsvImport::rows($dir.'/source.csv', $meta['delimiter']) as $index => $row) {
            if ($index === 0) continue;
            $data = [];
            foreach ($this->mapping as $key => $column) if ($column !== '' && $column !== null) $data[$key] = ($row[(int) $column] ?? '') ?: null;
            if (array_key_exists('price', $data)) $data['price'] = ImportPrice::parse((string) $data['price']);
            $validator = Validator::make($data, ['name' => 'required|string|max:2000', 'category' => 'nullable|string|max:255', 'brand' => 'nullable|string|max:255',
                'sku' => 'nullable|string|max:255', 'availability' => 'nullable|string|max:255', 'price' => 'nullable|numeric', 'currency' => 'nullable|string|max:10']);
            if ($validator->fails()) {
                fputcsv($errors, array_map([CsvImport::class, 'safeCell'], [...$row, implode('; ', $validator->errors()->all())]), ';', '"', '');
                $failed++;
                continue;
            }
            // A repeated SKU with a different name remains a distinct catalogue position.
            $product = ! empty($data['sku']) ? Product::where('sku', trim($data['sku']))->where('name', $data['name'])->first() : null;
            Gate::forUser($user->fresh())->authorize($product ? 'update' : 'create', $product ?? Product::class);
            $product ??= new Product;
            if (! $product->exists) $product->fill(['currency' => 'RUB', 'is_active' => false]);
            // The legacy CSV flow must not overwrite a reviewed taxonomy assignment.
            if ($product->catalog_category_id && array_key_exists('category', $data)) {
                $data['source_category'] = $data['category'];
                unset($data['category']);
            }
            if (array_key_exists('currency', $data) && $data['currency'] === null) $data['currency'] = 'RUB';
            try {
                $product->fill($data)->save();
                $success++;
            } catch (\Illuminate\Database\QueryException $e) {
                report($e);
                fputcsv($errors, array_map([CsvImport::class, 'safeCell'], [...$row, 'Не удалось сохранить строку. Проверьте значения и длину полей.']), ';', '"', '');
                $failed++;
            }
        }
        rewind($errors);
        $disk->put($dir.'/errors.csv', $errors);
        fclose($errors);
        $meta['status'] = 'completed';
        $meta['successful'] = $success;
        $meta['failed'] = $failed;
        $disk->put($dir.'/meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE));
        $user->notify(new AdminImportFinished('Импорт завершён', $meta['name']."\nУспешно: {$success}. С ошибками: {$failed}.", $failed ? $this->id : null));
    }

    public function failed(?\Throwable $exception): void
    {
        $dir = CsvImport::directory($this->userId, $this->id);
        $disk = Storage::disk('local');
        $meta = json_decode($disk->get($dir.'/meta.json'), true);
        $meta['status'] = 'failed';
        $disk->put($dir.'/meta.json', json_encode($meta));
        User::find($this->userId)?->notify(new AdminImportFinished('Импорт остановлен', 'Некоторые строки могли быть сохранены. Проверьте файл и загрузите повторно. Существующие пары артикула и названия будут обновлены.'));
    }
}
