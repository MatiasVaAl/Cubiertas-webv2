<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

trait UsesPostgres
{
    protected function assertSafeDatabase(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql' || ! str_ends_with($connection->getDatabaseName(), '_test')) {
            throw new RuntimeException('Las pruebas requieren PostgreSQL dedicado con nombre terminado en _test.');
        }
        $actual = $connection->selectOne('select current_database() as name')->name;
        if (! str_ends_with($actual, '_test')) {
            throw new RuntimeException('La base efectiva no es una base de pruebas dedicada.');
        }
    }
}
