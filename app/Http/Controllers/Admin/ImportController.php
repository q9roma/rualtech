<?php

namespace App\Http\Controllers\Admin;

use App\Admin\CsvImport;
use App\Http\Controllers\Controller;
use App\Jobs\ImportAdminProducts;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Gate, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function create()
    {
        Gate::authorize('create', Product::class);
        return Inertia::render('Admin/Import', ['upload' => null, 'fields' => CsvImport::FIELDS]);
    }

    public function preview(Request $request)
    {
        Gate::authorize('create', Product::class);
        $request->validate(['file' => 'required|file|extensions:csv,txt|max:20480', 'delimiter' => ['required', Rule::in([';', ',', 'tab'])]]);
        $id = (string) Str::uuid();
        $dir = CsvImport::directory($request->user()->id, $id);
        $request->file('file')->storeAs($dir, 'source.csv', 'local');
        $delimiter = $request->input('delimiter') === 'tab' ? "\t" : $request->input('delimiter');
        $sample = [];
        foreach (CsvImport::rows($dir.'/source.csv', $delimiter) as $row) { $sample[] = $row; if (count($sample) === 6) break; }
        if (count($sample) < 2 || count($sample[0]) > 100) {
            Storage::disk('local')->delete($dir.'/source.csv');
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'Нужны заголовки колонок и хотя бы одна строка данных (до 100 колонок).']);
        }
        $meta = ['id' => $id, 'name' => $request->file('file')->getClientOriginalName(), 'delimiter' => $delimiter, 'headers' => $sample[0], 'sample' => array_slice($sample, 1), 'status' => 'preview'];
        Storage::disk('local')->put($dir.'/meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE));
        return redirect('/admin/imports/'.$id);
    }

    public function show(Request $request, string $id)
    {
        Gate::authorize('create', Product::class);
        $meta = $this->meta($request, $id);
        return Inertia::render('Admin/Import', ['upload' => [...$meta, 'mapping' => CsvImport::guess($meta['headers'])], 'fields' => CsvImport::FIELDS]);
    }

    private function meta(Request $request, string $id): array
    {
        $path = CsvImport::directory($request->user()->id, $id).'/meta.json';
        abort_unless(Storage::disk('local')->exists($path), 404);
        return json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function run(Request $request, string $id)
    {
        Gate::authorize('create', Product::class);
        $meta = $this->meta($request, $id);
        $rules = ['mapping' => 'required|array:'.implode(',', array_keys(CsvImport::FIELDS))];
        foreach (CsvImport::FIELDS as $key => $label) $rules['mapping.'.$key] = [$key === 'name' ? 'required' : 'nullable', 'integer', 'min:0', 'max:'.(count($meta['headers']) - 1)];
        $mapping = $request->validate($rules)['mapping'];
        $lock = Cache::lock('admin-import:'.$request->user()->id.':'.$id, 30);
        abort_unless($lock->get(), 409);
        try {
            $meta = $this->meta($request, $id);
            abort_unless($meta['status'] === 'preview', 409, 'Этот файл уже отправлен на импорт.');
            $meta['status'] = 'queued';
            Storage::disk('local')->put(CsvImport::directory($request->user()->id, $id).'/meta.json', json_encode($meta));
            ImportAdminProducts::dispatch($request->user()->id, $id, $mapping);
        } finally { $lock->release(); }
        return back()->with('success', 'Импорт запущен. Результат появится здесь и в уведомлениях.');
    }

    public function errors(Request $request, string $id)
    {
        Gate::authorize('create', Product::class);
        $path = CsvImport::directory($request->user()->id, $id).'/errors.csv';
        abort_unless(Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->download($path, 'import-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function example()
    {
        Gate::authorize('create', Product::class);
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values(CsvImport::FIELDS), ';', '"', '');
            fputcsv($out, ['Серверы', 'Бренд', 'EXAMPLE-001', 'Пример товара', 'Есть', '2 190,00', 'RUB'], ';', '"', '');
            fclose($out);
        }, 'products-example.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function legacyErrors(Request $request, string $import)
    {
        Gate::authorize('create', Product::class);
        $import = \Filament\Actions\Imports\Models\Import::where('user_id', $request->user()->id)->findOrFail($import);
        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $keys = array_keys($import->failedRows()->first()?->data ?? []);
            fputcsv($out, [...$keys, 'Ошибка'], ';', '"', '');
            foreach ($import->failedRows()->cursor() as $row) fputcsv($out, array_map([CsvImport::class, 'safeCell'], [...array_map(fn ($key) => $row->data[$key] ?? '', $keys), $row->validation_error]), ';', '"', '');
            fclose($out);
        }, 'legacy-import-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
