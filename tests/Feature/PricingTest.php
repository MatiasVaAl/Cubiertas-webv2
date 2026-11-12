<?php

namespace Tests\Feature;

use App\Livewire\CoverConfigurator;
use App\Models\Product;
use App\Models\WidthRate;
use App\Models\WorkshopSetting;
use App\Services\ConfigurationService;
use App\Services\Pricing\PricingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use DatabaseTransactions;

    private function input(Product $product, array $overrides = []): array
    {
        return [...['kind' => 'standard', 'product_id' => $product->id, 'length_cm' => '150', 'width_cm' => '50', 'quantity' => 2], ...$overrides];
    }

    public function test_confirmed_ranges_are_half_open_and_prices_are_server_calculated(): void
    {
        $product = Product::factory()->create();
        WorkshopSetting::create(['id' => 1, 'formula_confirmed' => true]);
        foreach ([[40, 50, 10000], [50, 60, 20000]] as [$min, $max, $price]) {
            $product->rates()->create(['min_width_cm' => $min, 'max_width_cm' => $max, 'price_per_m2_clp' => $price, 'active' => true, 'confirmed' => true]);
        }
        $pricing = app(PricingService::class);
        $this->assertSame(30000, $pricing->calculate($this->input($product, ['unit_price_clp' => 1]))['subtotal_clp']);
        $this->assertSame(12000, $pricing->calculate($this->input($product, ['width_cm' => '40']))['subtotal_clp']);
        $this->assertNull($pricing->calculate($this->input($product, ['width_cm' => '60']))['subtotal_clp']);
        $product->rates()->where('min_width_cm', 50)->update(['price_per_m2_clp' => 30000]);
        $this->assertSame(45000, $pricing->calculate($this->input($product))['subtotal_clp']);
    }

    public function test_formula_is_off_and_special_is_pending(): void
    {
        $product = Product::factory()->create();
        $this->assertNull(app(PricingService::class)->calculate($this->input($product))['subtotal_clp']);
        $this->assertNotEmpty(app(PricingService::class)->calculate($this->input($product, ['kind' => 'special', 'material_name' => 'Material de prueba']))['pending_reasons']);
        $this->get('/catalogo')->assertOk();
        $this->get('/cotizar')->assertOk();
        Livewire::test(CoverConfigurator::class)->set('product_id', (string) $product->id)->set('length_cm', '150')->set('width_cm', '50')->call('calculate')->assertSee('Precio por confirmar');
    }

    public function test_invalid_dimensions_and_quantity_are_rejected(): void
    {
        $product = Product::factory()->create();
        foreach ([['length_cm' => '0'], ['width_cm' => '-1'], ['width_cm' => '10e2'], ['quantity' => 0], ['quantity' => '1.5'], ['length_cm' => '1.234']] as $override) {
            try {
                app(ConfigurationService::class)->normalize($this->input($product, $override));
                $this->fail('Se admitió una configuración inválida.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_postgres_rejects_overlapping_active_ranges(): void
    {
        $product = Product::factory()->create();
        $attributes = ['product_id' => $product->id, 'min_width_cm' => 40, 'max_width_cm' => 60, 'price_per_m2_clp' => 10000, 'active' => true];
        WidthRate::create($attributes);
        $this->expectException(QueryException::class);
        WidthRate::create([...$attributes, 'min_width_cm' => 50, 'max_width_cm' => 70]);
    }
}
