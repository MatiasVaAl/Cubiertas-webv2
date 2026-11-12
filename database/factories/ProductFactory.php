<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Acabado de prueba', 'code' => fake()->unique()->bothify('TEST-####-????'), 'available' => true, 'demonstration' => false];
    }
}
