<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('category')
                ->label('Категория')
                ->guess(['Вид оборудования'])
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('brand')
                ->label('Бренд')
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('sku')
                ->label('Артикул')
                ->guess(['PN', 'Part Number', 'артикул', 'SKU', 'sku'])
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('name')
                ->label('Наименование')
                ->guess(['Наименование', 'Название', 'Name', 'name'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:2000']),

            ImportColumn::make('availability')
                ->label('Наличие')
                ->helperText('По умолчанию подставляется «Есть»; при необходимости выберите «Свободно» или «Транзит».')
                ->guess(['Есть', 'Свободно', 'Транзит', 'Наличие'])
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('price')
                ->label('Цена')
                ->guess(['Цена', 'Price', 'price', ' Цена '])
                ->castStateUsing(function (mixed $originalState): ?float {
                    if ($originalState === null || $originalState === '') {
                        return null;
                    }

                    return self::parsePriceFromImportString((string) $originalState);
                })
                ->rules(['nullable', 'numeric']),

            ImportColumn::make('currency')
                ->label('Валюта')
                ->guess(['Валюта', 'Currency'])
                ->rules(['nullable', 'string', 'max:10']),
        ];
    }

    /**
     * Парсит цену из прайса: «2 190,00 ₽», «2190.5», «1.234.567,89» (запятая — десятичная).
     * Без этого Filament numeric() выкидывает запятую и «2 190,00» превращается в 219000.
     */
    protected static function parsePriceFromImportString(string $raw): ?float
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }

        $s = preg_replace('/\s*₽\s*$/u', '', $s) ?? $s;
        $s = preg_replace('/\s*(?:руб|РУБ|rub|RUB)\.?\s*$/ui', '', $s) ?? $s;
        $s = trim($s);

        $s = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $s);
        $s = preg_replace('/[^\d\s,.\-]/u', '', $s) ?? $s;
        $s = trim($s);

        if ($s === '' || $s === '-') {
            return null;
        }

        $s = preg_replace('/(?<=\d)\s+(?=\d)/', '', $s) ?? $s;

        if (preg_match('/^(-?)([\d.]+),(\d{1,2})$/', $s, $m)) {
            $intPart = str_replace('.', '', $m[2]);

            return (float) ($m[1] . $intPart . '.' . $m[3]);
        }

        if (preg_match('/^(-?)(\d+)\.(\d+)$/', $s, $m)) {
            return (float) ($m[1] . $m[2] . '.' . $m[3]);
        }

        if (preg_match('/^-?\d+$/', $s)) {
            return (float) $s;
        }

        $digits = preg_replace('/[^\d\-]/', '', $s) ?? '';

        return $digits !== '' && $digits !== '-' ? (float) $digits : null;
    }

    public function resolveRecord(): ?Product
    {
        $sku = trim((string) ($this->data['sku'] ?? ''));
        $name = $this->data['name'] ?? null;
        $nameColumnMapped = filled($this->columnMap['name'] ?? null);

        // После castData() пустая строка имени становится null. Fallback firstOrNew(['sku']) сливал
        // «конверт / наклейка / BOX» с одним PN в одну позицию — не используем его, если имя замаплено.
        if ($nameColumnMapped && ! filled($name)) {
            return new Product();
        }

        // Один PN может быть у разных позиций — пара (sku, name), без слияния только по sku
        if ($sku !== '' && filled($name)) {
            return Product::query()
                ->where('sku', $sku)
                ->where('name', $name)
                ->first()
                ?? new Product();
        }

        if ($sku !== '' && ! $nameColumnMapped) {
            return Product::firstOrNew(['sku' => $sku]);
        }

        return new Product();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $total = (int) $import->total_rows;
        $ok = (int) $import->successful_rows;
        $failedByMath = max(0, $total - $ok);
        $failedStored = $import->failedRows()->count();

        $lines = [
            'Файл: ' . $import->file_name,
            'Строк в файле: ' . number_format($total),
            'Успешно импортировано: ' . number_format($ok),
            'С ошибками (не записано): ' . number_format(max($failedByMath, $failedStored)),
        ];

        if ($failedStored > 0) {
            $lines[] = 'Подробности по ошибкам — в CSV неуспешных строк (кнопка в этом уведомлении).';
        }

        return implode("\n", $lines);
    }
}
