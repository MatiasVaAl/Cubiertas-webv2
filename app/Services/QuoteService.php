<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteVersion;
use App\Models\User;
use App\Support\Clp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function __construct(private CartService $cart, private HistoryService $history, private TrackingService $tracking) {}

    public function submit(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $summary = $this->cart->summary();
            $this->require(count($summary['lines']) > 0, 'El carrito está vacío.');
            $customer = Customer::create(array_intersect_key($data, array_flip(['name', 'phone', 'email'])));
            $quote = Quote::create(['customer_id' => $customer->id, 'delivery_method' => $data['delivery_method'], 'delivery_details' => array_intersect_key($data, array_flip(['city', 'carrier', 'destination', 'delivery_notes'])), 'transport_clp' => $data['delivery_method'] === 'pickup' ? 0 : null, 'notes' => $data['notes'] ?? null]);
            foreach ($summary['lines'] as $line) {
                $quote->lines()->create(['product_id' => $line['configuration']['product_id'], 'snapshot' => $line, 'quantity' => $line['configuration']['quantity'], 'unit_price_clp' => $line['unit_price_clp'], 'known_subtotal_clp' => $line['known_subtotal_clp'], 'pending_reasons' => $line['pending_reasons']]);
            }
            $this->history->record($quote, 'submitted', 'Solicitud registrada para evaluación.');
            if (! $summary['pending'] && $quote->transport_clp !== null) {
                $this->issueLocked($quote, null, 'Propuesta estándar calculada con tarifas confirmadas.');
            }
            $token = $this->tracking->issue($quote);

            return ['quote' => $quote, 'token' => $token];
        });
    }

    public function resolveLine(Quote $quote, QuoteLine $line, int $unitPrice, string $reason, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $quote);
        DB::transaction(function () use ($quote, $line, $unitPrice, $reason, $actor) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            $this->editable($quote);
            $this->require($line->quote_id === $quote->id && $unitPrice >= 0 && trim($reason) !== '', 'La resolución requiere precio válido, confirmación de factibilidad y justificación.');
            $line->update(['unit_price_clp' => $unitPrice, 'known_subtotal_clp' => Clp::multiply($unitPrice, $line->quantity), 'pending_reasons' => [], 'resolution' => $reason]);
            $quote->update(['status' => 'in_review']);
            $this->history->record($quote, 'line_resolved', 'Se confirmó precio y factibilidad de una configuración: '.$reason, $actor);
        });
    }

    public function confirmTransport(Quote $quote, int $amount, string $conditions, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $quote);
        DB::transaction(function () use ($quote, $amount, $conditions, $actor) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            $this->editable($quote);
            $this->require($quote->delivery_method === 'transport' && $amount >= 0 && trim($conditions) !== '', 'El transporte requiere importe y condiciones confirmados.');
            $quote->update(['transport_clp' => $amount, 'transport_conditions' => $conditions, 'status' => 'in_review']);
            $this->history->record($quote, 'transport_confirmed', 'Condiciones de transporte confirmadas: '.$conditions, $actor);
        });
    }

    public function issue(Quote $quote, string $reason, User $actor): QuoteVersion
    {
        Gate::forUser($actor)->authorize('update', $quote);

        return DB::transaction(function () use ($quote, $reason, $actor) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            $this->editable($quote);

            return $this->issueLocked($quote, $actor, $reason);
        });
    }

    private function issueLocked(Quote $quote, ?User $actor, string $reason): QuoteVersion
    {
        $lines = $quote->lines()->get();
        $this->require(trim($reason) !== '' && $lines->isNotEmpty() && $quote->transport_clp !== null, 'Faltan justificación, productos o importe de transporte.');
        $this->require(! $lines->contains(fn ($line) => $line->unit_price_clp === null || count($line->pending_reasons) > 0), 'Existen configuraciones pendientes de revisión.');
        $snapshotLines = $lines->map(fn ($line) => ['id' => $line->id, 'configuration' => $line->snapshot, 'quantity' => $line->quantity, 'unit_price_clp' => $line->unit_price_clp, 'subtotal_clp' => $line->unit_price_clp * $line->quantity, 'resolution' => $line->resolution])->all();
        $total = Clp::sum([...array_column($snapshotLines, 'subtotal_clp'), $quote->transport_clp]);
        $this->require($total > 0, 'El total definitivo debe ser mayor que cero.');
        $version = $quote->versions()->create(['version' => $quote->current_version + 1, 'snapshot' => ['lines' => $snapshotLines, 'delivery_method' => $quote->delivery_method, 'delivery_details' => $quote->delivery_details, 'transport_clp' => $quote->transport_clp, 'transport_conditions' => $quote->transport_conditions, 'currency' => 'CLP', 'rounding' => 'half_up_unit_then_quantity'], 'total_clp' => $total, 'issued_by' => $actor?->id, 'justification' => $reason]);
        $quote->update(['current_version' => $version->version, 'status' => 'awaiting_acceptance']);
        $this->history->record($quote, 'version_issued', 'Se emitió la propuesta versión '.$version->version.'. '.$reason, $actor);

        return $version;
    }

    public function accept(Quote $quote, int $versionNumber): Order
    {
        $this->require($this->tracking->canView($quote), 'Acceso privado no autorizado.');

        return DB::transaction(function () use ($quote, $versionNumber) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            if ($quote->status === 'accepted' && $quote->current_version === $versionNumber) {
                return $quote->order()->firstOrFail();
            }
            $this->require($quote->status === 'awaiting_acceptance' && $quote->current_version === $versionNumber, 'La propuesta cambió o no está disponible para aceptación. Revisa la versión vigente.');
            $version = $quote->versions()->where('version', $versionNumber)->firstOrFail();
            $order = Order::create(['quote_id' => $quote->id, 'quote_version_id' => $version->id, 'total_clp' => $version->total_clp, 'delivery_status' => $quote->delivery_method === 'pickup' ? 'pending_pickup' : 'pending_dispatch']);
            $quote->update(['status' => 'accepted', 'accepted_at' => now()]);
            $this->history->record($quote, 'accepted', 'Cliente aceptó la versión '.$versionNumber.' y el total íntegro. Pendiente de transferencia verificada.');

            return $order;
        });
    }

    public function changeStatus(Quote $quote, string $status, string $reason, ?User $actor = null): void
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('update', $quote);
        } else {
            $this->require($this->tracking->canView($quote) && $status === 'in_review', 'Acción no autorizada.');
        }
        DB::transaction(function () use ($quote, $status, $reason, $actor) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            if ($quote->status === 'accepted' && $status === 'in_review') {
                $order = $quote->order()->lockForUpdate()->firstOrFail();
                $this->require($order->payment_status === 'pending' && ! $order->payments()->exists() && $order->manufacturing_status === 'pending', 'No se puede cambiar una aceptación con comprobantes o pagos. Requiere resolución comercial fuera del flujo MVP.');
                $order->update(['superseded_at' => now(), 'superseded_reason' => $reason, 'on_hold' => true, 'hold_reason' => 'Propuesta en revisión. Este pedido conserva el total histórico y no admite operaciones.']);
                $quote->status = 'in_review';
                $quote->accepted_at = null;
            }
            $this->editable($quote);
            $this->require(in_array($status, ['in_review', 'awaiting_information', 'rejected'], true) && trim($reason) !== '', 'Estado o motivo inválido.');
            $quote->update(['status' => $status]);
            $this->history->record($quote, 'review', $reason, $actor);
        });
    }

    private function editable(Quote $quote): void
    {
        $this->require(! in_array($quote->status, ['accepted', 'rejected'], true), 'Una cotización aceptada o rechazada no puede modificarse.');
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['quote' => $message]);
        }
    }
}
