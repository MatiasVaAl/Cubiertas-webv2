<?php

namespace Tests\Feature;

use App\Livewire\CoverConfigurator;
use App\Models\AdditionalWork;
use App\Models\Product;
use App\Models\WorkshopSetting;
use App\Services\CartService;
use App\Services\ConfigurationService;
use App\Services\Pricing\PricingService;
use App\Services\QuoteService;
use App\Services\TrackingService;
use App\Support\Clp;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesCommercialFlow;
use Tests\TestCase;

class CommercialIntegrityTest extends TestCase
{
    use CreatesCommercialFlow, DatabaseTransactions;

    private function pricedConfiguration(): array
    {
        $product = Product::factory()->create();
        WorkshopSetting::updateOrCreate(['id' => 1], ['formula_confirmed' => true]);
        $product->rates()->create(['min_width_cm' => 40, 'max_width_cm' => 60, 'price_per_m2_clp' => 10000, 'confirmed' => true, 'active' => true]);

        return ['kind' => 'standard', 'product_id' => $product->id, 'length_cm' => '150', 'width_cm' => '50', 'quantity' => 2];
    }

    public function test_standard_submission_is_recalculated_after_rate_changes_and_automatically_versioned(): void
    {
        $input = $this->pricedConfiguration();
        app(CartService::class)->add([...$input, 'price' => 1]);
        Product::findOrFail($input['product_id'])->rates()->update(['price_per_m2_clp' => 20000]);
        $result = app(QuoteService::class)->submit(['name' => 'Cliente prueba', 'phone' => '123', 'delivery_method' => 'pickup', 'total_clp' => 1]);
        $quote = $result['quote'];
        $this->assertSame('awaiting_acceptance', $quote->status);
        $this->assertSame(30000, $quote->versions()->first()->total_clp);
        app(TrackingService::class)->authenticate($quote->number, $result['token']);
        $this->get(route('tracking.show', $quote))->assertOk()->assertSee('Aceptar cotización definitiva');
    }

    public function test_additional_prices_need_confirmed_rules_and_group_in_stable_order(): void
    {
        $input = $this->pricedConfiguration();
        $work = AdditionalWork::create(['name' => 'Perforación ficticia', 'pricing_mode' => 'per_unit', 'price_clp' => 1500, 'active' => true, 'confirmed' => true]);
        $manual = AdditionalWork::create(['name' => 'Corte por evaluar', 'active' => true]);
        $pricing = app(PricingService::class);
        $this->assertSame(18000, $pricing->calculate([...$input, 'additional_ids' => [$work->id]])['subtotal_clp']);
        $pending = $pricing->calculate([...$input, 'additional_ids' => [$work->id, $manual->id]]);
        $this->assertNull($pending['subtotal_clp']);
        $this->assertSame(18000, $pending['known_subtotal_clp']);
        $cart = app(CartService::class);
        $cart->add([...$input, 'additional_ids' => [$manual->id, $work->id]]);
        $cart->add([...$input, 'additional_ids' => [$work->id, $manual->id]]);
        $this->assertCount(1, session('cart'));
    }

    public function test_configured_limits_and_unavailable_products_are_server_validated(): void
    {
        $input = $this->pricedConfiguration();
        WorkshopSetting::current()->update(['dimension_limits' => ['length_cm_max' => '140.00']]);
        try {
            app(ConfigurationService::class)->normalize($input);
            $this->fail('No aplicó el límite configurado.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        WorkshopSetting::current()->update(['dimension_limits' => null]);
        Product::findOrFail($input['product_id'])->update(['available' => false]);
        $this->expectException(ValidationException::class);
        app(ConfigurationService::class)->normalize($input);
    }

    public function test_configurator_adds_and_edits_using_server_configuration(): void
    {
        $input = $this->pricedConfiguration();
        Livewire::test(CoverConfigurator::class)->set('product_id', (string) $input['product_id'])->set('length_cm', '150')->set('width_cm', '50')->call('calculate')->call('addToCart')->assertDispatched('cart-updated')->assertSee('Configuración agregada al carrito');
        $key = array_key_first(session('cart'));
        $this->get(route('cart.edit', $key))->assertOk()->assertSee('Edita tu cubierta');
        $this->assertSame('150.00', session('cart')[$key]['length_cm']);
        $cart = app(CartService::class);
        $cart->add([...session('cart')[$key], 'length_cm' => '160'], $key);
        $this->assertCount(1, session('cart'));
        $this->assertSame('160.00', array_values(session('cart'))[0]['length_cm']);
    }

    public function test_special_reference_image_is_private_and_only_staff_can_download(): void
    {
        Storage::fake('local');
        Livewire::test(CoverConfigurator::class)->set('kind', 'special')->set('material_name', 'Material de prueba')->set('length_cm', '150')->set('width_cm', '50')->set('reference', UploadedFile::fake()->image('reference.png'))->call('addToCart')->assertDispatched('cart-updated');
        $result = app(QuoteService::class)->submit(['name' => 'Cliente prueba', 'phone' => '123', 'delivery_method' => 'pickup']);
        $line = $result['quote']->lines()->first();
        Storage::disk('local')->assertExists($line->snapshot['configuration']['reference_path']);
        $this->get(route('admin.references', $line))->assertRedirect(route('login'));
        $this->actingAs($this->worker())->get(route('admin.references', $line))->assertOk();
    }

    public function test_unpaid_acceptance_can_be_revised_without_overwriting_previous_order(): void
    {
        $order = $this->acceptedOrder();
        $quote = $order->quote;
        $quotes = app(QuoteService::class);
        $quotes->changeStatus($quote, 'in_review', 'Cambiar material antes de transferir.');
        $this->assertNotNull($order->fresh()->superseded_at);
        $this->assertNull($quote->fresh()->order);
        $worker = $this->worker();
        $quotes->resolveLine($quote, $quote->lines()->first(), 15000, 'Nueva configuración factible y confirmada', $worker);
        $version = $quotes->issue($quote, 'Versión modificada por el cliente', $worker);
        $newOrder = $quotes->accept($quote, $version->version);
        $this->assertNotSame($order->id, $newOrder->id);
        $this->assertSame(20000, $order->fresh()->total_clp);
        $this->assertSame(30000, $newOrder->total_clp);
        $this->assertSame(2, $quote->orders()->count());
        $this->assertSame(1, $quote->order()->count());
    }

    public function test_postgres_prevents_rewriting_issued_versions(): void
    {
        $order = $this->acceptedOrder();
        $this->expectException(QueryException::class);
        DB::table('quote_versions')->where('id', $order->quote_version_id)->update(['total_clp' => 1]);
    }

    public function test_postgres_prevents_silent_order_total_changes(): void
    {
        $order = $this->acceptedOrder();
        $this->expectException(QueryException::class);
        $order->update(['total_clp' => 1]);
    }

    public function test_expired_credentials_and_credentials_from_another_quote_are_denied(): void
    {
        $first = $this->acceptedOrder();
        $second = $this->acceptedOrder();
        session()->forget('tracking.'.$first->quote_id);
        $this->get(route('tracking.show', $first->quote))->assertForbidden();
        $this->get(route('tracking.show', $second->quote))->assertOk();
        $second->quote->credentials()->update(['expires_at' => now()->subMinute()]);
        $this->get(route('tracking.show', $second->quote))->assertForbidden();
    }

    public function test_tracking_guessing_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->post(route('tracking.authenticate'), ['number' => 'CT-000000', 'code' => str_repeat('0', 64)])->assertSessionHasErrors('code');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->post(route('tracking.authenticate'), ['number' => 'CT-000000', 'code' => str_repeat('0', 64)])->assertStatus(429);
    }

    public function test_amount_overflow_is_a_validation_error_instead_of_float_arithmetic(): void
    {
        $this->expectException(ValidationException::class);
        Clp::sum([1000000000000000, 1]);
    }
}
