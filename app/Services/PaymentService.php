<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkshopSetting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentService
{
    public function __construct(private TrackingService $tracking, private HistoryService $history) {}

    public function upload(Order $order, UploadedFile $file): Payment
    {
        abort_unless($this->tracking->canView($order->quote), 403);
        Validator::make(['receipt' => $file], ['receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:5120']])->validate();
        $mime = $file->getMimeType();
        if ($mime === 'application/pdf') {
            $handle = fopen($file->getRealPath(), 'rb');
            $head = fread($handle, 8);
            fseek($handle, max(0, $file->getSize() - 4096));
            $tail = stream_get_contents($handle);
            fclose($handle);
            $this->check(str_starts_with($head, '%PDF-') && str_contains($tail, '%%EOF'), 'El PDF está incompleto o no es válido.');
        } else {
            $this->check(@getimagesize($file->getRealPath()) !== false, 'La imagen no es válida o está dañada.');
        }
        $sha256 = hash_file('sha256', $file->getRealPath());
        $path = null;
        try {
            return DB::transaction(function () use ($order, $file, $mime, $sha256, &$path) {
                $order = Order::lockForUpdate()->findOrFail($order->id);
                $this->check($order->superseded_at === null, 'Este pedido corresponde a una propuesta reemplazada. Revisa la cotización vigente.');
                $this->check(in_array($order->payment_status, ['pending', 'observed'], true), 'El pedido ya tiene un pago verificado o un comprobante en revisión.');
                $this->check(WorkshopSetting::current()->bankIsReady(), 'El taller aún no configura los datos bancarios completos.');
                $this->check(! Payment::where('sha256', $sha256)->exists(), 'Este comprobante ya está registrado. No puede reutilizarse.');
                $path = $file->store('receipts', 'local');
                $payment = $order->payments()->create(['expected_clp' => $order->total_clp, 'path' => $path, 'sha256' => $sha256, 'mime' => $mime]);
                $order->update(['payment_status' => 'under_review']);
                $this->history->record($order->quote, 'receipt_uploaded', 'Comprobante recibido. El ingreso del dinero aún no está verificado.');

                return $payment;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['receipt' => 'El comprobante ya fue registrado en otra operación.']);
            }
            throw $exception;
        }
    }

    public function verify(Payment $payment, User $actor, bool $approved, string $observations, bool $bankReceived): void
    {
        Gate::forUser($actor)->authorize('verify', $payment);
        DB::transaction(function () use ($payment, $actor, $approved, $observations, $bankReceived) {
            // Orden de bloqueo común: pedido, luego comprobante.
            $order = Order::lockForUpdate()->findOrFail($payment->order_id);
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'paid' && $approved) {
                return;
            }
            $this->check($payment->status === 'under_review' && $order->payment_status === 'under_review', 'El comprobante no está pendiente de verificación.');
            $this->check(! $approved || ($bankReceived && $payment->expected_clp === $order->total_clp), 'Confirma que ingresó el 100 % del importe esperado en la cuenta bancaria.');
            $this->check($approved || trim($observations) !== '', 'Debes explicar por qué el pago queda observado.');
            $status = $approved ? 'paid' : 'observed';
            $payment->update(['status' => $status, 'verified_by' => $actor->id, 'verified_at' => now(), 'observations' => $observations]);
            $order->update(['payment_status' => $status]);
            $this->history->record($order->quote, 'payment_'.$status, $approved ? 'El equipo verificó el ingreso del pago total.' : 'Pago observado: '.$observations, $actor);
        });
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['receipt' => $message]);
        }
    }
}
