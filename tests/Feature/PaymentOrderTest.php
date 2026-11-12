<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesCommercialFlow;
use Tests\TestCase;

class PaymentOrderTest extends TestCase
{
    use CreatesCommercialFlow, DatabaseTransactions;

    public function test_complete_pickup_flow_requires_verified_full_payment_and_is_idempotent(): void
    {
        Storage::fake('local');
        $this->bankConfigured();
        $order = $this->acceptedOrder();
        $this->get(route('tracking.show', $order->quote))->assertOk()->assertSee('NO TRANSFERIR')->assertSee('name="receipt"', false);
        $this->post(route('payments.store', $order), ['receipt' => UploadedFile::fake()->image('receipt.png')])->assertRedirect();
        $payment = $order->payments()->firstOrFail();
        Storage::disk('local')->assertExists($payment->path);
        $this->assertSame('under_review', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->manufacturing_status);
        $this->get(route('payments.receipt', $payment))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $worker = $this->worker(true);
        $this->actingAs($worker)->post(route('admin.orders.transition', $order), ['action' => 'start'])->assertSessionHasErrors('order');
        $payments = app(PaymentService::class);
        $payments->verify($payment, $worker, true, 'Ingreso bancario verificado en prueba', true);
        $payments->verify($payment, $worker, true, 'Reintento', true);
        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', 'paid')->count());
        $operations = app(OrderService::class);
        foreach (['start', 'finish', 'ready_pickup', 'deliver'] as $action) {
            $operations->transition($order, $action, [], $worker);
        }
        $this->assertSame('delivered', $order->fresh()->delivery_status);
        $this->assertSame('completed', $order->fresh()->manufacturing_status);
        $this->get(route('admin.quotes.show', $order->quote))->assertOk()->assertSee('Historial y responsables');
    }

    public function test_worker_without_explicit_permission_cannot_verify(): void
    {
        Storage::fake('local');
        $this->bankConfigured();
        $order = $this->acceptedOrder();
        $payment = app(PaymentService::class)->upload($order, UploadedFile::fake()->image('receipt.png'));
        $this->actingAs($this->worker())->post(route('admin.payments.verify', $payment), ['decision' => 'approve', 'bank_received' => 1])->assertForbidden();
        $this->assertSame('under_review', $order->fresh()->payment_status);
    }

    public function test_observed_payment_accepts_new_receipt_but_not_same_file(): void
    {
        Storage::fake('local');
        $this->bankConfigured();
        $order = $this->acceptedOrder();
        $file = UploadedFile::fake()->image('receipt.png', 30, 30);
        $payment = app(PaymentService::class)->upload($order, $file);
        app(PaymentService::class)->verify($payment, $this->worker(true), false, 'No se encontró ingreso bancario.', false);
        $this->assertSame('observed', $order->fresh()->payment_status);
        $this->post(route('payments.store', $order), ['receipt' => $file])->assertSessionHasErrors('receipt');
        $this->post(route('payments.store', $order), ['receipt' => UploadedFile::fake()->image('new.png', 45, 45)])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $order->payments()->count());
    }

    public function test_invalid_and_disguised_receipts_are_rejected_and_private_access_is_enforced(): void
    {
        Storage::fake('local');
        $this->bankConfigured();
        $order = $this->acceptedOrder();
        $this->post(route('payments.store', $order), ['receipt' => UploadedFile::fake()->createWithContent('receipt.pdf', '<?php echo "bad";')])->assertSessionHasErrors('receipt');
        $this->post(route('payments.store', $order), ['receipt' => UploadedFile::fake()->create('receipt.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('receipt');
        $payment = app(PaymentService::class)->upload($order, UploadedFile::fake()->image('receipt.png'));
        session()->forget('tracking');
        $this->get(route('payments.receipt', $payment))->assertForbidden();
        $this->post(route('payments.store', $order), ['receipt' => UploadedFile::fake()->image('other.png')])->assertForbidden();
        $this->get('/storage/'.$payment->path)->assertNotFound();
    }

    public function test_bank_receipt_confirmation_is_required(): void
    {
        Storage::fake('local');
        $this->bankConfigured();
        $order = $this->acceptedOrder();
        $payment = app(PaymentService::class)->upload($order, UploadedFile::fake()->image('receipt.png'));
        $this->expectException(ValidationException::class);
        app(PaymentService::class)->verify($payment, $this->worker(true), true, 'Solo tengo imagen', false);
    }

    public function test_missing_bank_data_blocks_receipts_and_paid_quotes_cannot_be_changed(): void
    {
        Storage::fake('local');
        $order = $this->acceptedOrder();
        $this->post(route('payments.store', $order), ['receipt' => UploadedFile::fake()->image('receipt.png')])->assertSessionHasErrors('receipt');
        $this->bankConfigured();
        $payment = app(PaymentService::class)->upload($order, UploadedFile::fake()->image('receipt.png'));
        $this->post(route('quotes.revision', $order->quote), ['reason' => 'Cambiar medidas después de adjuntar comprobante'])->assertSessionHasErrors('quote');
        app(PaymentService::class)->verify($payment, $this->worker(true), true, '', true);
        $this->post(route('quotes.revision', $order->quote), ['reason' => 'Intento de cambiar total ya pagado'])->assertSessionHasErrors('quote');
        $this->assertSame(20000, $order->fresh()->total_clp);
    }

    public function test_pdf_receipt_is_validated_and_stored_privately(): void
    {
        Storage::fake('local');
        $this->bankConfigured();
        $order = $this->acceptedOrder();
        $pdf = "%PDF-1.4\n";
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] >>'];
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 4\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }
        $pdf .= "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
        $this->post(route('payments.store', $order), ['receipt' => UploadedFile::fake()->createWithContent('receipt.pdf', $pdf)])->assertRedirect()->assertSessionHasNoErrors();
        $payment = $order->payments()->firstOrFail();
        $this->assertSame('application/pdf', $payment->mime);
        $this->get(route('payments.receipt', $payment))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_transport_delivery_needs_completed_order_and_evidence(): void
    {
        Storage::fake('local');
        $this->bankConfigured();
        $order = $this->acceptedOrder('transport');
        $worker = $this->worker(true);
        $this->assertSame(25000, $order->total_clp);
        $payment = app(PaymentService::class)->upload($order, UploadedFile::fake()->image('receipt.png'));
        app(PaymentService::class)->verify($payment, $worker, true, '', true);
        $operations = app(OrderService::class);
        $this->actingAs($worker)->post(route('admin.orders.transition', $order), ['action' => 'dispatch'])->assertSessionHasErrors('order');
        $operations->transition($order, 'hold', ['reason' => 'Aclaración de prueba'], $worker);
        $this->post(route('admin.orders.transition', $order), ['action' => 'start'])->assertSessionHasErrors('order');
        $operations->transition($order, 'resume', ['reason' => 'Aclaración resuelta'], $worker);
        $operations->transition($order, 'start', [], $worker);
        $operations->transition($order, 'finish', [], $worker);
        $operations->transition($order, 'dispatch', ['carrier' => 'Transportista de prueba', 'destination' => 'Destino de prueba', 'dispatched_at' => now()->toDateString()], $worker);
        $this->assertSame('handed_to_carrier', $order->fresh()->delivery_status);
        $this->assertArrayNotHasKey('tracking_number', $order->fresh()->dispatch_details);
        $this->post(route('admin.orders.transition', $order), ['action' => 'confirm_delivery'])->assertSessionHasErrors('evidence');
        $operations->transition($order, 'confirm_delivery', ['evidence' => 'Recepción confirmada en prueba por el cliente.'], $worker);
        $this->assertSame('delivery_confirmed',$order->fresh()->delivery_status);
    }
}
