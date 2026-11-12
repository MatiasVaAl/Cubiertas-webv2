<?php

namespace Tests\Feature;

use App\Models\AdditionalWork;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicFeedbackTest extends TestCase
{
    use DatabaseTransactions;

    public function test_required_server_validation_is_translated_with_readable_attribute_names(): void
    {
        $this->postJson(route('login.store'), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Debes completar correo electrónico.')
            ->assertJsonPath('errors.password.0', 'Debes completar contraseña.');
    }

    public function test_password_rule_errors_are_translated_without_weakening_the_rule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $before = User::count();
        $this->actingAs($admin)->postJson(route('admin.users.save'), [
            'name' => 'Usuario de prueba', 'email' => 'feedback@example.invalid', 'role' => 'worker',
            'password' => 'SoloLetrasLargas', 'password_confirmation' => 'SoloLetrasLargas',
        ])->assertUnprocessable()->assertJsonPath('errors.password.0', 'El campo contraseña debe contener al menos un número.');
        $this->assertSame($before, User::count());
    }

    public function test_pending_cart_and_request_use_correct_plural_and_do_not_present_zero_as_sale_price(): void
    {
        $cart = app(CartService::class);
        $input = $this->configuration();
        $cart->add($input);
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('1 unidad en 1 configuración.')
            ->assertSee('Total definitivo pendiente de confirmación')
            ->assertDontSee('$0 CLP');
        $this->get(route('quotes.create'))->assertOk()
            ->assertSee('1 cubierta · 1 configuración')
            ->assertSee('Aún no hay importes confirmados.')
            ->assertDontSee('$0 CLP');

        $cart->add($input);
        $this->get(route('cart.index'))->assertOk()->assertSee('2 unidades en 1 configuración.');
        $cart->add([...$input, 'notes' => 'Configuración distinta para probar el plural.']);
        $this->get(route('cart.index'))->assertOk()->assertSee('3 unidades en 2 configuraciones.');
        $this->assertCount(2, session('cart'));
    }

    public function test_known_partial_amount_remains_visible_and_is_never_labelled_as_final(): void
    {
        $work = AdditionalWork::create(['name' => 'Adicional ficticio de prueba', 'pricing_mode' => 'per_unit', 'price_clp' => 1500, 'confirmed' => true, 'active' => true]);
        app(CartService::class)->add([...$this->configuration(), 'quantity' => 2, 'additional_ids' => [$work->id]]);

        foreach (['cart.index', 'quotes.create'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('Subtotal conocido: $3.000 CLP')
                ->assertSee('Este importe no es el total definitivo')
                ->assertDontSee('Aún no hay importes confirmados.');
        }
    }

    private function configuration(): array
    {
        return ['kind' => 'standard', 'product_id' => Product::factory()->create()->id, 'length_cm' => '150', 'width_cm' => '60', 'quantity' => 1];
    }
}
