<?php

namespace Tests\Unit;

use App\Support\Clp;
use PHPUnit\Framework\TestCase;

class ClpTest extends TestCase
{
    public function test_large_values_remain_exact(): void
    {
        $this->assertSame(999999999999000, Clp::multiply(999999999999, 1000));
        $this->assertSame(1000000000000000, Clp::sum([999999999999999, 1]));
    }
}
