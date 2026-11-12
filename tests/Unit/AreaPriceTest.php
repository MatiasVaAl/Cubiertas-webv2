<?php

namespace Tests\Unit;

use App\Services\Pricing\AreaPriceStrategy;
use PHPUnit\Framework\TestCase;

class AreaPriceTest extends TestCase
{
    public function test_exact_area_and_provisional_half_up_rounding(): void
    {
        $strategy = new AreaPriceStrategy;
        $this->assertSame('0.90000000', $strategy->area('150.00', '60.00'));
        $this->assertSame(9000, $strategy->unitPrice('150.00', '60.00', 10000));
        $this->assertSame(1, $strategy->unitPrice('100.00', '50.00', 1));
        $this->assertSame(1236, $strategy->unitPrice('123.45', '100.10', 1000));
    }
}
