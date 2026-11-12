<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\UsesPostgres;

abstract class TestCase extends BaseTestCase
{
    use UsesPostgres;

    protected function setUpTraits()
    {
        // Se comprueba ANTES de que cualquier trait pueda migrar o abrir transacciones.
        $this->assertSafeDatabase();

        return parent::setUpTraits();
    }
}
