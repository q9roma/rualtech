<?php

namespace App\Admin;

use Illuminate\Support\Facades\Storage;

class CsvImport
{
    public const FIELDS = ['category' => 'Категория', 'brand' => 'Бренд', 'sku' => 'Артикул', 'name' => 'Наименование', 'availability' => 'Наличие', 'price' => 'Цена', 'currency' => 'Валюта'];

    public static function directory(int $userId, string $id): string
    {
        abort_unless(\Illuminate\Support\Str::isUuid($id), 404);
        return 'admin-imports/'.$userId.'/'.$id;
    }

    public static function rows(string $path, string $delimiter): \Generator
    {
        $stream = Storage::disk('local')->readStream($path);
        try {
            while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                if ($row === [null]) continue;
                yield array_map(fn ($cell) => trim(preg_replace('/^\x{FEFF}/u', '', mb_convert_encoding((string) $cell, 'UTF-8', mb_check_encoding((string) $cell, 'UTF-8') ? 'UTF-8' : 'Windows-1251'))), $row);
            }
        } finally { fclose($stream); }
    }

    public static function guess(array $headers): array
    {
        $aliases = ['category' => ['категория', 'вид оборудования'], 'brand' => ['бренд'], 'sku' => ['артикул', 'pn', 'part number'],
            'name' => ['наименование', 'название'], 'availability' => ['есть', 'свободно', 'транзит', 'наличие'], 'price' => ['цена'], 'currency' => ['валюта']];
        $map = [];
        foreach (self::FIELDS as $key => $label) {
            $map[$key] = '';
            foreach ($headers as $index => $header) if (in_array(mb_strtolower(trim($header)), [$key, ...$aliases[$key]], true)) { $map[$key] = (string) $index; break; }
        }
        return $map;
    }

    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
    }
}
