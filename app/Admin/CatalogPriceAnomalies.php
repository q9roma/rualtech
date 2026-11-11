<?php
namespace App\Admin;

class CatalogPriceAnomalies
{
    public const LABELS = [
        'price' => 'Цена изменилась более чем на 30%',
        'stock' => 'Наличие стало нулевым',
        'missing' => 'Нет в новом прайсе',
        'data' => 'Ошибки и неоднозначные данные',
    ];

    public static function matches(array $row, string $kind): bool
    {
        if ($kind === 'missing') return ($row['operation'] ?? '') === 'absent';
        if ($kind === 'data') return !empty($row['issues']);
        if ($kind === 'price') {
            $change = $row['changes']['price'] ?? [];
            $before = $change['before'] ?? null;
            $after = $change['after'] ?? null;
            if (!is_numeric($before) || !is_numeric($after) || (float)$before <= 0) return false;
            return abs((float)$after - (float)$before) / (float)$before > 0.300000001;
        }
        if ($kind === 'stock') {
            $change = $row['changes']['availability'] ?? [];
            $before = trim((string)($change['before'] ?? ''));
            $after = trim((string)($change['after'] ?? ''));
            return preg_match('/^(?:>\s*)?\d+$/', $before)
                && ((float)ltrim($before, '> ') > 0 || str_starts_with($before, '>'))
                && is_numeric($after) && (float)$after === 0.0;
        }
        return false;
    }
}
