<?php

namespace Tests\Feature;

use App\Models\OperationAudit;
use App\Models\Product;
use App\Models\WorkshopSetting;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesCommercialFlow;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use CreatesCommercialFlow, DatabaseTransactions;

    public function test_admin_maintenance_and_worker_permissions(): void
    {
        $admin = $this->worker(true, true);
        $this->actingAs($admin);
        foreach (['admin.dashboard', 'admin.catalog', 'admin.rates', 'admin.settings', 'admin.quotes', 'admin.orders', 'admin.payments', 'admin.deliveries'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->post(route('admin.products.save'), ['name' => 'Acabado de prueba', 'code' => 'TEST-ADMIN', 'available' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $product = Product::where('code', 'TEST-ADMIN')->firstOrFail();
        $this->post(route('admin.rates.save'), ['product_id' => $product->id, 'min_width_cm' => 40, 'max_width_cm' => 50, 'price_per_m2_clp' => 10000, 'active' => 1, 'confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.rates.save'), ['product_id' => $product->id, 'min_width_cm' => 45, 'max_width_cm' => 60, 'price_per_m2_clp' => 20000, 'active' => 1, 'confirmed' => 1])->assertSessionHasErrors('min_width_cm');
        $this->post(route('admin.settings.update'), ['contact' => ['phone' => 'TELÉFONO FICTICIO DE PRUEBA'], 'formula_confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(WorkshopSetting::current()->formula_confirmed);
        $this->get('/')->assertOk()->assertSee('TELÉFONO FICTICIO DE PRUEBA');
        $this->assertGreaterThanOrEqual(3, OperationAudit::count());
        $this->actingAs($this->worker());
        foreach (['admin.catalog', 'admin.rates', 'admin.settings'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('admin.products.save'), ['name' => 'Ataque', 'code' => 'NO'])->assertForbidden();
    }

    public function test_banking_is_encrypted_and_audit_does_not_include_values(): void
    {
        $this->actingAs($this->worker(true, true));
        $details = ['holder' => 'PRUEBA PRIVADA', 'rut' => 'NO REAL', 'bank' => 'FICTICIO', 'account_type' => 'TEST', 'account_number' => 'SECRETO FICTICIO', 'email' => 'test@example.invalid'];
        $this->post(route('admin.settings.update'), ['bank_details' => $details])->assertRedirect()->assertSessionHasNoErrors();
        $settings = WorkshopSetting::current();
        $this->assertStringNotContainsString('SECRETO FICTICIO', $settings->getRawOriginal('bank_details'));
        $this->assertSame('SECRETO FICTICIO', $settings->bank_details['account_number']);
        $this->assertStringNotContainsString('SECRETO FICTICIO', OperationAudit::latest('id')->first()->toJson());
    }

    public function test_last_admin_cannot_be_deactivated_and_users_can_be_created_without_default_password(): void
    {
        $admin = $this->worker(true, true);
        $this->actingAs($admin)->post(route('admin.users.save', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'worker', 'active' => 1])->assertSessionHasErrors('role');
        $this->post(route('admin.users.save'), ['name' => 'Trabajador de prueba', 'email' => 'worker@example.invalid', 'role' => 'worker', 'active' => 1, 'password' => 'UniquePassword123!', 'password_confirmation' => 'UniquePassword123!'])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_rotating_credentials_revokes_previous_sessions(): void
    {
        $order = $this->acceptedOrder();
        $quote = $order->quote;
        $tracking = app(TrackingService::class);
        $this->assertTrue($tracking->canView($quote));
        $token = $tracking->rotate($quote, $this->worker(), 'Identidad verificada en prueba');
        $this->assertFalse($tracking->canView($quote));
        $tracking->authenticate($quote->number, $token);
        $this->assertTrue($tracking->canView($quote));
    }
}
