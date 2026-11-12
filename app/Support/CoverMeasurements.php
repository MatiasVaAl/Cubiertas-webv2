<?php

namespace App\Support;

class CoverMeasurements
{
    /** Geometría de presentación; no calcula ni confirma precios. */
    public static function area(string $lengthCm, string $widthCm): ?string
    {
        foreach ([$lengthCm, $widthCm] as $dimension) {
            if (! preg_match('/^\d{1,10}(?:[.,]\d{1,2})?$/D', $dimension)) {
                return null;
            }
        }
        $lengthCm = str_replace(',', '.', $lengthCm);
        $widthCm = str_replace(',', '.', $widthCm);
        if (bccomp($lengthCm, '0', 2) <= 0 || bccomp($widthCm, '0', 2) <= 0) {
            return null;
        }

        return bcdiv(bcmul($lengthCm, $widthCm, 4), '10000', 8);
    }

    public static function display(?string $value): string
    {
        return $value === null ? '—' : str_replace('.', ',', str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value);
    }
}
