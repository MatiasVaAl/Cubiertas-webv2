<?php

namespace App\Services\Pricing;

use Illuminate\Validation\ValidationException;

class AreaPriceStrategy implements PriceStrategy
{
    public function area(string $lengthCm, string $widthCm): string
    {
        return bcdiv(bcmul($lengthCm, $widthCm, 4), '10000', 8);
    }

    public function unitPrice(string $lengthCm, string $widthCm, int $rateClp): int
    {
        $amount = bcadd(bcmul($this->area($lengthCm, $widthCm), (string) $rateClp, 8), '0.5', 8);
        if (bccomp($amount, '1000000000000', 8) >= 0) {
            throw ValidationException::withMessages(['dimensions' => 'El importe excede la capacidad técnica del cotizador; solicita revisión al taller.']);
        }

        return (int) bcdiv($amount, '1', 0);
    }
}
