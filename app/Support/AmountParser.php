<?php

namespace App\Support;

class AmountParser
{
    /** "120 K", "3,5k", "1.250.000", "Rp 50.000", 15000 => int rupiah|null */
    public static function parse(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return (int) round($value);
        }
        $value = trim((string) $value);
        $value = preg_replace('/^rp\.?\s*/iu', '', $value);

        if (!preg_match('/^(\d[\d.,]*)\s*(k|rb|ribu|jt|juta)?$/iu', $value, $m)) {
            return null;
        }
        return self::toRupiah($m[1], $m[2] ?? null);
    }

    public static function toRupiah(string $num, ?string $suffix = null): ?int
    {
        $mult = match (strtolower($suffix ?? '')) {
            'k', 'rb', 'ribu' => 1_000,
            'jt', 'juta'      => 1_000_000,
            default           => 1,
        };

        if ($mult > 1 && preg_match('/^\d+[.,]\d{1,2}$/', $num)) {
            $v = (float) str_replace(',', '.', $num);              // 3,5 K => 3.5
        } elseif (preg_match('/^\d{1,3}([.,]\d{3})+$/', $num)) {
            $v = (float) preg_replace('/[.,]/', '', $num);         // 1.250.000
        } elseif (preg_match('/^\d+[.,]\d+$/', $num)) {
            $v = (float) str_replace(',', '.', $num);
        } else {
            $v = (float) preg_replace('/\D/', '', $num);
        }

        $result = (int) round($v * $mult);
        return $result > 0 ? $result : null;
    }
}
