<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private HistoryService $history) {}

    public function transition(Order $order, string $action, array $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $order);
        DB::transaction(function () use ($order, $action, $data, $actor) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $this->check($order->superseded_at === null, 'Este pedido es histórico y fue reemplazado por una revisión.');
            if ($action === 'hold' || $action === 'resume') {
                Validator::make($data, ['reason' => 'required|string|min:5|max:3000'])->validate();
                $this->check($action === 'hold' ? ! $order->on_hold : $order->on_hold, 'El pedido ya está en ese estado de espera.');
                $order->update(['on_hold' => $action === 'hold', 'hold_reason' => $action === 'hold' ? $data['reason'] : null]);
                $this->history->record($order->quote, $action, ($action === 'hold' ? 'Pedido en espera: ' : 'Pedido reanudado: ').$data['reason'], $actor);

                return;
            }
            $this->check(! $order->on_hold, 'El pedido está en espera. Registra su reanudación antes de avanzar.');
            $method = $order->version->snapshot['delivery_method'];
            switch ($action) {
                case 'start':
                    $this->check($order->payment_status === 'paid' && $order->manufacturing_status === 'pending', 'Solo se puede iniciar con el pago total verificado y fabricación pendiente.');
                    $order->manufacturing_status = 'in_progress';
                    $message = 'Comenzó la fabricación del pedido completo.';
                    break;
                case 'finish':
                    $this->check($order->manufacturing_status === 'in_progress', 'El pedido debe estar en fabricación.');
                    $order->manufacturing_status = 'completed';
                    $message = 'Finalizó la fabricación de todas las cubiertas del pedido.';
                    break;
                case 'ready_pickup':
                    $this->check($method === 'pickup' && $order->manufacturing_status === 'completed' && $order->delivery_status === 'pending_pickup', 'El pedido debe estar terminado y pendiente de retiro.');
                    $order->delivery_status = 'ready_pickup';
                    $message = 'Pedido completo listo para retiro en taller.';
                    break;
                case 'deliver':
                    $this->check($method === 'pickup' && $order->delivery_status === 'ready_pickup', 'El pedido debe estar listo para retiro.');
                    $order->delivery_status = 'delivered';
                    $message = 'Pedido completo entregado al cliente en taller.';
                    break;
                case 'dispatch':
                    $this->check($method === 'transport' && $order->manufacturing_status === 'completed' && $order->delivery_status === 'pending_dispatch', 'Solo se puede despachar un pedido terminado y pendiente de despacho.');
                    $dispatch = Validator::make($data, ['carrier' => 'required|string|max:255', 'destination' => 'required|string|max:1000', 'dispatched_at' => 'required|date|before_or_equal:today', 'tracking_number' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:2000'])->validate();
                    $order->dispatch_details = $dispatch;
                    $order->delivery_status = 'handed_to_carrier';
                    $message = 'Pedido completo entregado al transportista. Aún no confirma recepción del cliente.';
                    break;
                case 'confirm_delivery':
                    $this->check($method === 'transport' && $order->delivery_status === 'handed_to_carrier', 'El pedido debe estar entregado al transportista.');
                    $evidence = Validator::make($data, ['evidence' => 'required|string|min:10|max:2000'])->validate();
                    $order->dispatch_details = [...($order->dispatch_details ?? []), ...$evidence];
                    $order->delivery_status = 'delivery_confirmed';
                    $message = 'Entrega al cliente confirmada: '.$evidence['evidence'];
                    break;
                default:
                    throw ValidationException::withMessages(['order' => 'La transición solicitada no está permitida.']);
            }
            $order->save();
            $this->history->record($order->quote, $action, $message, $actor);
        });
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['order' => $message]);
        }
    }
}
