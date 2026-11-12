<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class Clp
{
    // Capacidad técnica, no tarifa ni límite comercial del taller.
    public static function integer(string $amount): int
    {
        if (! preg_match('/^\d+$/', $amount) || bccomp($amount, '1000000000000000', 0) > 0) {
            throw ValidationException::withMessages(['amount' => 'El importe excede la capacidad técnica del sistema. Solicita revisión al taller.']);
        }

        return (int) $amount;
    }

    public static function multiply(int $unit, int $quantity): int
    {
        return self::integer(bcmul((string) $unit, (string) $quantity, 0));
    }

    public static function sum(array $amounts): int
    {
        return self::integer(array_reduce($amounts, fn ($sum, $amount) => bcadd($sum, (string) $amount, 0), '0'));
    }
}
