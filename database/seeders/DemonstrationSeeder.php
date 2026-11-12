<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemonstrationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Los datos demostrativos solo se permiten en local/testing.');
        }
        $finishes = [
            'DEMO-ROBLE' => ['Roble · demostración', 'Madera clara', true],
            'DEMO-PIEDRA' => ['Piedra clara · demostración', 'Beige', true],
            'DEMO-GRIS' => ['Gris suave · demostración', 'Gris', true],
            'DEMO-BLANCO' => ['Blanco mate · demostración', 'Blanco', true],
            'DEMO-NOGAL' => ['Nogal · demostración', 'Madera oscura', true],
            'DEMO-NEGRO' => ['Negro mate · demostración', 'Negro', true],
            'DEMO-ARENA' => ['Arena · demostración', 'Arena', true],
            'DEMO-CEMENTO' => ['Cemento · demostración', 'Gris cemento', true],
            'DEMO-VERDE' => ['Verde oliva · demostración', 'Verde', false],
            'DEMO-TERRACOTA' => ['Terracota · demostración', 'Terracota', false],
        ];
        foreach ($finishes as $code => [$name, $color, $available]) {
            Product::firstOrCreate(['code' => $code], [
                'name' => $name, 'color' => $color,
                'description' => 'Recurso ficticio de desarrollo. Disponibilidad, apariencia y precio no confirmados.',
                'finish' => 'Demostrativa', 'characteristics' => 'Muestra ficticia para probar el catálogo y las solicitudes. No es un producto comercial confirmado.',
                'available' => $available, 'demonstration' => true,
            ]);
        }
    }
}
