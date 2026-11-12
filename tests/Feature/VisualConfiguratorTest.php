<?php

namespace Tests\Feature;

use App\Livewire\CartCounter;
use App\Livewire\CoverConfigurator;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkshopSetting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VisualConfiguratorTest extends TestCase
{
    use DatabaseTransactions;

    private function pricedProduct(): Product
    {
        WorkshopSetting::updateOrCreate(['id' => 1], ['formula_confirmed' => true]);
        $product = Product::factory()->create();
        $product->rates()->create(['min_width_cm' => 40, 'max_width_cm' => 80, 'price_per_m2_clp' => 10000, 'active' => true, 'confirmed' => true]);

        return $product;
    }

    public function test_metres_are_converted_exactly_and_summary_is_repriced_on_changes(): void
    {
        $product = $this->pricedProduct();
        $component = Livewire::test(CoverConfigurator::class)->set('product_id', (string) $product->id)
            ->set('lengthInput', '2,40')->set('width_cm', '60')
            ->assertSet('length_cm', '240.00')->assertSet('preview.unit_price_clp', 14400);
        $component->set('lengthUnit', 'cm')->assertSet('lengthInput', '240')->assertSet('preview.unit_price_clp', 14400);
        $component->set('lengthUnit', 'm')->assertSet('lengthInput', '2.4')->assertSet('length_cm', '240.00');
        $component->set('lengthInput', '2,4001')->assertSet('length_cm', '240.01')->assertSet('preview.unit_price_clp', 14401);
        $component->set('quantity', '2')->assertSet('preview.subtotal_clp', 28802);
        $component->set('lengthInput', 'no válido')->assertSet('preview', null)->call('addToCart')->assertHasErrors('length_cm');
        $this->assertEmpty(session('cart', []));
    }

    public function test_add_keeps_configurator_open_groups_identical_covers_and_updates_counter(): void
    {
        $product = $this->pricedProduct();
        $component = Livewire::test(CoverConfigurator::class)->set('product_id', (string) $product->id)->set('lengthInput', '1,50')->set('width_cm', '60');
        $component->call('addToCart')->assertDispatched('cart-updated')->assertSet('added', true);
        $component->call('addToCart')->assertDispatched('cart-updated');
        $this->assertCount(1, session('cart'));
        $this->assertSame(2, array_values(session('cart'))[0]['quantity']);
        Livewire::test(CartCounter::class)->assertSee('2 cubiertas');
    }

    public function test_quantity_controls_and_unavailable_finish_keep_server_validation(): void
    {
        $product = Product::factory()->create(['available' => false]);
        Livewire::test(CoverConfigurator::class)->call('adjustQuantity', -1)->assertSet('quantity', '1')
            ->call('adjustQuantity', 1)->assertSet('quantity', '2')
            ->set('product_id', (string) $product->id)->set('lengthInput', '1.50')->set('width_cm', '60')
            ->assertSet('preview', null)->call('addToCart')->assertHasErrors('product_id');
        $this->assertEmpty(session('cart', []));
    }

    public function test_editing_and_splitting_preserve_other_cart_lines(): void
    {
        $product = $this->pricedProduct();
        $input = ['kind' => 'standard', 'product_id' => $product->id, 'length_cm' => '150', 'width_cm' => '60', 'quantity' => 2];
        $cart = app(CartService::class);
        $cart->add($input);
        $cart->add([...$input, 'length_cm' => '200', 'quantity' => 1]);
        $keys = array_keys(session('cart'));
        $split = $cart->split($keys[0]);
        Livewire::test(CoverConfigurator::class, ['line' => $split])->assertSet('lengthInput', '1.5')
            ->set('lengthInput', '1.80')->call('addToCart')->assertRedirect(route('cart.index'));
        $this->assertCount(3, session('cart'));
        $this->assertSame('150.00', session('cart')[$keys[0]]['length_cm']);
        $this->assertSame('200.00', session('cart')[$keys[1]]['length_cm']);
        $this->assertTrue(collect(session('cart'))->contains(fn ($line) => $line['length_cm'] === '180.00' && $line['quantity'] === 1));
    }

    public function test_special_reference_is_private_and_repeated_add_reuses_the_same_reference(): void
    {
        Storage::fake('local');
        $component = Livewire::test(CoverConfigurator::class)->set('kind', 'special')->set('material_name', 'Material ficticio')
            ->set('material_description', 'Descripción específica para probar la revisión.')
            ->set('lengthInput', '1.50')->set('width_cm', '60')->set('reference', UploadedFile::fake()->image('reference.png'));
        $component->call('addToCart')->assertSee('Precio por confirmar');
        $component->call('addToCart');
        $this->assertCount(1, session('cart'));
        $line = array_values(session('cart'))[0];
        $this->assertSame(2, $line['quantity']);
        Storage::disk('local')->assertExists($line['reference_path']);
        $this->get(route('cart.index'))->assertOk()->assertSee('Descripción específica para probar la revisión.')->assertDontSee('$0 CLP');
    }

    public function test_contact_configuration_accepts_https_links_and_rejects_unsafe_schemes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.settings.update'), ['contact' => ['instagram' => 'javascript:alert(1)']])->assertSessionHasErrors('contact.instagram');
        $this->post(route('admin.settings.update'), ['contact' => ['instagram' => 'https://example.invalid/demo-profile']])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('configurator'))->assertOk()->assertSee('https://example.invalid/demo-profile', false)->assertSee('Pendiente de configuración');
    }
}
