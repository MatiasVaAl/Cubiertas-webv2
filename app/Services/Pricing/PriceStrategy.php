<?php

namespace App\Services\Pricing;

interface PriceStrategy
{
    public function unitPrice(string $lengthCm, string $widthCm, int $rateClp): int;
}
