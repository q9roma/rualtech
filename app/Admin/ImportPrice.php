<?php

namespace App\Admin;

class ImportPrice
{
    public static function parse(string $raw): ?float
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

}
