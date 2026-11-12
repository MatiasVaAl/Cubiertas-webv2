<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\CartService;
use App\Services\QuoteService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CartQuoteTest extends TestCase
{
    use DatabaseTransactions;

    protected function input(array $override = []): array
    {
        $product = Product::factory()->create();

        return [...['kind' => 'standard', 'product_id' => $product->id, 'length_cm' => '150', 'width_cm' => '50', 'quantity' => 1], ...$override];
    }

    public function test_cart_groups_canonical_configuration_and_preserves_differences(): void
    {
        $cart = app(CartService::class);
        $input = $this->input();
        $cart->add($input);
        $cart->add([...$input, 'length_cm' => '150,00', 'quantity' => 2]);
        $this->assertCount(1, session('cart'));
        $key = array_key_first(session('cart'));
        $this->assertSame(3, session('cart')[$key]['quantity']);
        $cart->add([...$input, 'notes' => 'Perforación pendiente']);
        $cart->add($this->input());
        $this->assertCount(3, session('cart'));
        $newKey = $cart->split($key);
        $this->assertSame(1, session('cart')[$newKey]['quantity']);
        $cart->updateQuantity($key, 4);
        $cart->remove($newKey);
        $this->assertSame(6, $cart->summary()['quantity']);
        $this->get('/carrito')->assertOk()->assertSee('Precio pendiente');
    }

    public function test_special_and_transport_remain_pending_and_cannot_be_issued(): void
    {
        app(CartService::class)->add($this->input(['kind' => 'special', 'material_name' => 'Material especial de prueba']));
        $result = app(QuoteService::class)->submit(['name' => 'Cliente prueba', 'phone' => '+56900000000', 'delivery_method' => 'transport', 'city' => 'Destino de prueba']);
        $quote = $result['quote'];
        $this->assertNull($quote->transport_clp);
        $this->assertSame('pending_review', $quote->status);
        $this->assertNotSame($result['token'], $quote->credentials()->first()->getRawOriginal('token_hash'));
        $this->expectException(ValidationException::class);
        app(QuoteService::class)->issue($quote, 'Prueba', User::factory()->create());
    }

    public function test_versions_acceptance_and_history_remain_fixed_after_catalog_changes(): void
    {
        app(CartService::class)->add($this->input());
        $service = app(QuoteService::class);
        $result = $service->submit(['name' => 'Cliente prueba', 'phone' => '12345', 'delivery_method' => 'pickup']);
        $quote = $result['quote'];
        $worker = User::factory()->create();
        $service->resolveLine($quote, $quote->lines()->first(), 10000, 'Factibilidad confirmada en prueba', $worker);
        $first = $service->issue($quote, 'Propuesta inicial', $worker);
        $service->resolveLine($quote, $quote->lines()->first(), 12000, 'Cambio solicitado de prueba', $worker);
        $second = $service->issue($quote, 'Nueva versión de prueba', $worker);
        app(TrackingService::class)->authenticate($quote->number, $result['token']);
        $this->post(route('quotes.accept', $quote), ['version' => $first->version, 'acceptance' => 1])->assertSessionHasErrors('quote');
        $order = $service->accept($quote, $second->version);
        $this->assertSame($order->id, $service->accept($quote, $second->version)->id);
        $this->assertSame(10000, $first->fresh()->total_clp);
        $this->assertSame(12000, $order->total_clp);
        $quote->lines()->first()->product->update(['name' => 'Nombre cambiado']);
        $this->assertSame('Acabado de prueba', $second->fresh()->snapshot['lines'][0]['configuration']['name']);
        $this->assertSame(1, $quote->order()->count());
    }

    public function test_public_cannot_access_another_quote_or_accept_without_secret(): void
    {
        app(CartService::class)->add($this->input());
        $result = app(QuoteService::class)->submit(['name' => 'Cliente prueba', 'phone' => '123', 'delivery_method' => 'pickup']);
        $quote = $result['quote'];
        $this->get(route('tracking.show', $quote))->assertForbidden();
        $this->post(route('tracking.authenticate'), ['number' => $quote->number, 'code' => str_repeat('0', 64)])->assertSessionHasErrors('code');
        $this->post(route('tracking.authenticate'), ['number' => $quote->number, 'code' => $result['token']])->assertRedirect(route('tracking.show', $quote));
        $this->get(route('tracking.show', $quote))->assertOk()->assertSee('Tus cubiertas')->assertDontSee('name="receipt"', false);
    }

    public function test_submission_endpoint_persists_and_does_not_accept_browser_price(): void
    {
        app(CartService::class)->add($this->input());
        $this->post(route('quotes.store'), ['name' => 'Prueba', 'phone' => '123', 'delivery_method' => 'transport', 'city' => 'Comuna', 'total_clp' => 1])->assertRedirect(route('quotes.received'));
        $this->assertNull(Quote::latest('id')->first()->transport_clp);
        $this->assertEmpty(session('cart', []));
        $this->get(route('quotes.received'))->assertOk()->assertSee('Código privado');
    }
}
