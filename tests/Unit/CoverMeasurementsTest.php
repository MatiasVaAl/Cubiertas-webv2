<?php

namespace Tests\Unit;

use App\Support\CoverMeasurements;
use PHPUnit\Framework\TestCase;

class CoverMeasurementsTest extends TestCase
{
    public function test_area_is_exact_and_invalid_dimensions_do_not_produce_a_surface(): void
    {
        $this->assertSame('1.44000000', CoverMeasurements::area('240.00', '60'));
        $this->assertSame('1,44', CoverMeasurements::display(CoverMeasurements::area('240', '60')));
        $this->assertSame('150', CoverMeasurements::display('150'));
        $this->assertNull(CoverMeasurements::area('-1', '60'));
        $this->assertNull(CoverMeasurements::area('0', '60'));
        $this->assertNull(CoverMeasurements::area('2e2', '60'));
    }
}
