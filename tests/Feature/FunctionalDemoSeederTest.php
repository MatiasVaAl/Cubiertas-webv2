<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TrackingCredential;
use App\Models\User;
use App\Models\WidthRate;
use App\Models\WorkshopSetting;
use App\Services\QuoteService;
use App\Services\TrackingService;
use Database\Seeders\FunctionalDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class FunctionalDemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    private function reportPath(): string
    {
        return 'demo/'.rawurlencode(DB::connection()->getDatabaseName()).'/accesos.json';
    }

    public function test_it_creates_14_coherent_scenarios_and_keeps_settings_and_session_untouched(): void
    {
        Storage::fake('local');
        $settings = WorkshopSetting::updateOrCreate(['id' => 1], ['formula_confirmed' => false, 'dimension_limits' => ['length_cm_min' => '100.00', 'length_cm_max' => '180.00'], 'contact' => ['about' => 'Configuración de prueba que debe conservarse.']]);
        $originalSettings = $settings->fresh()->getRawOriginal();
        $originalQuotes = Quote::count();
        $originalCustomers = Customer::count();
        $originalOrders = Order::count();
        $originalPayments = Payment::count();
        $originalRates = WidthRate::count();
        session(['cart' => ['existing' => ['quantity' => 4]], 'tracking' => ['existing' => ['until' => 123]]]);
        $originalSession = session()->all();

        $this->seed(FunctionalDemoSeeder::class);

        $this->assertSame($originalQuotes + 14, Quote::count());
        $this->assertSame($originalCustomers + 14, Customer::count());
        $this->assertSame($originalOrders + 9, Order::count());
        $this->assertSame($originalPayments + 8, Payment::count());
        $this->assertSame($originalRates, WidthRate::count());
        $this->assertSame($originalSettings, $settings->fresh()->getRawOriginal());
        $this->assertSame($originalSession, session()->all());
        $this->assertSame(10, Product::where('code', 'like', 'DEMO-%')->where('demonstration', true)->count());
        $this->assertSame(2, Product::where('code', 'like', 'DEMO-%')->where('available', false)->count());

        $report = json_decode(Storage::disk('local')->get($this->reportPath()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(3, $report['users']);
        $this->assertCount(14, $report['scenarios']);
        foreach ($report['users'] as $account) {
            $user = User::where('email', $account['email'])->firstOrFail();
            $this->assertTrue(Hash::check($account['password'], $user->password));
        }
        $this->assertFalse(User::where('email', 'operations.demo@example.invalid')->firstOrFail()->can_verify_payments);
        $this->assertTrue(User::where('email', 'payments.demo@example.invalid')->firstOrFail()->can_verify_payments);
        foreach ($report['scenarios'] as $reference => $access) {
            $quote = Quote::where('notes', 'like', '['.$reference.']%')->firstOrFail();
            $this->assertSame(hash('sha256', $access['code']), $quote->credentials()->firstOrFail()->getRawOriginal('token_hash'));
            app(TrackingService::class)->authenticate($access['number'], $access['code']);
            $this->get(route('tracking.show', $quote))->assertOk();
            foreach ($quote->lines as $line) {
                $this->assertLessThanOrEqual(0, bccomp($line->snapshot['configuration']['length_cm'], '180.00', 2));
            }
            if ($quote->order) {
                $this->assertSame($quote->order->version->total_clp, $quote->order->total_clp);
                if ($quote->order->manufacturing_status !== 'pending') {
                    $this->assertSame('paid', $quote->order->payment_status);
                }
                foreach ($quote->order->payments as $payment) {
                    $pdf = Storage::disk('local')->get($payment->path);
                    $this->assertStringContainsString('DEMOSTRACION - SIN VALIDEZ DE PAGO', $pdf);
                    $this->assertSame($payment->sha256, hash('sha256', $pdf));
                    $this->get(route('payments.receipt', $payment))->assertOk();
                }
            }
        }
        $proposal = Quote::where('notes', 'like', '[DEMO-FLOW-05]%')->firstOrFail();
        $this->assertSame(2, $proposal->lines()->count());
        $this->assertSame(4, $proposal->lines()->first()->quantity);
        $this->assertSame(70000, $proposal->versions()->firstOrFail()->total_clp);
    }

    public function test_repeating_the_seeder_preserves_accesses_records_and_manual_progress(): void
    {
        Storage::fake('local');
        $this->seed(FunctionalDemoSeeder::class);
        $report = Storage::disk('local')->get($this->reportPath());
        $counts = [Quote::count(), Order::count(), Payment::count(), User::count(), TrackingCredential::count()];
        $fileCount = count(Storage::disk('local')->allFiles('demo'));
        $quote = Quote::where('notes', 'like', '[DEMO-FLOW-06]%')->firstOrFail();
        $worker = User::where('email', 'operations.demo@example.invalid')->firstOrFail();
        app(QuoteService::class)->changeStatus($quote, 'in_review', 'DEMO · Cambio manual de prueba que debe conservarse.', $worker);

        $this->seed(FunctionalDemoSeeder::class);

        $this->assertSame($report, Storage::disk('local')->get($this->reportPath()));
        $this->assertSame($counts, [Quote::count(), Order::count(), Payment::count(), User::count(), TrackingCredential::count()]);
        $this->assertSame($fileCount, count(Storage::disk('local')->allFiles('demo')));
        $this->assertSame('in_review', $quote->fresh()->status);
        $this->assertNotNull($quote->orders()->firstOrFail()->superseded_at);
    }

    public function test_it_refuses_production_before_writing_any_data(): void
    {
        Storage::fake('local');
        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');
        try {
            $this->expectException(RuntimeException::class);
            (new FunctionalDemoSeeder)->run();
        } finally {
            app()->instance('env', $originalEnvironment);
            $this->assertSame([], Storage::disk('local')->allFiles('demo'));
        }
    }

    public function test_failed_generation_rolls_back_data_and_removes_new_receipts(): void
    {
        Storage::fake('local');
        User::factory()->create(['name' => 'Cuenta existente sin permiso', 'email' => 'payments.demo@example.invalid', 'can_verify_payments' => false]);
        $counts = [Quote::count(), Product::count(), User::count()];
        try {
            $this->seed(FunctionalDemoSeeder::class);
            $this->fail('Debe respetar la cuenta existente sin permiso para verificar.');
        } catch (AuthorizationException) {
            $this->assertSame($counts, [Quote::count(), Product::count(), User::count()]);
            $this->assertSame([], Storage::disk('local')->allFiles('demo'));
        }
    }
}
