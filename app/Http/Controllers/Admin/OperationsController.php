<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\OrderService;
use App\Services\QuoteService;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperationsController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', ['metrics' => [
            'Cotizaciones por revisar' => Quote::whereIn('status', ['pending_review', 'in_review', 'awaiting_information'])->count(),
            'Esperando aceptación' => Quote::where('status', 'awaiting_acceptance')->count(),
            'Pagos por verificar' => Payment::where('status', 'under_review')->count(),
            'Pendientes de fabricación' => Order::whereNull('superseded_at')->where('manufacturing_status', 'pending')->count(),
            'En fabricación' => Order::whereNull('superseded_at')->where('manufacturing_status', 'in_progress')->count(),
            'Listos para entrega' => Order::whereNull('superseded_at')->where('manufacturing_status', 'completed')->whereIn('delivery_status', ['pending_pickup', 'ready_pickup', 'pending_dispatch'])->count(),
        ]]);
    }

    public function index(Request $request, string $area = 'quotes')
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|string|max:40', 'sort' => 'nullable|in:newest,oldest']);
        $query = Quote::with(['customer', 'order']);
        if ($area !== 'quotes') {
            $query->whereHas('order', function ($q) use ($area, $data) {
                if ($area === 'payments') {
                    $q->whereHas('payments');
                    if (! empty($data['status'])) {
                        $q->where('payment_status', $data['status']);
                    }
                } elseif ($area === 'deliveries') {
                    $q->where('manufacturing_status', 'completed');
                    if (! empty($data['status'])) {
                        $q->where('delivery_status', $data['status']);
                    }
                } elseif (! empty($data['status'])) {
                    $q->where('manufacturing_status', $data['status']);
                }
            });
        } elseif (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['q'])) {
            $term = $data['q'];
            $query->where(function ($q) use ($term) {
                if (preg_match('/^(?:CT-)?0*(\d+)$/i', $term, $match)) {
                    $q->orWhere('id', $match[1]);
                }
                $q->orWhereHas('customer', fn ($q) => $q->where('name', 'ilike', '%'.$term.'%')->orWhere('phone', 'ilike', '%'.$term.'%'));
            });
        }

        return view('admin.operations.index', ['quotes' => $query->orderBy('id', ($data['sort'] ?? '') === 'oldest' ? 'asc' : 'desc')->paginate(15)->withQueryString(), 'area' => $area]);
    }

    public function show(Quote $quote)
    {
        Gate::authorize('update', $quote);

        return response()->view('admin.operations.show', ['quote' => $quote->load(['customer', 'lines', 'versions', 'events.actor', 'order.payments'])])->header('Cache-Control', 'private, no-store');
    }

    public function resolveLine(Request $request, Quote $quote, QuoteLine $line, QuoteService $quotes)
    {
        $data = $request->validate(['unit_price_clp' => 'required|integer|min:0|max:1000000000000', 'reason' => 'required|string|min:5|max:3000', 'feasible' => 'accepted']);
        $quotes->resolveLine($quote, $line, (int) $data['unit_price_clp'], $data['reason'], $request->user());

        return back()->with('status', 'Configuración resuelta. Emite una nueva versión cuando esté todo confirmado.');
    }

    public function transport(Request $request, Quote $quote, QuoteService $quotes)
    {
        $data = $request->validate(['amount' => 'required|integer|min:0|max:1000000000000', 'conditions' => 'required|string|min:5|max:3000']);
        $quotes->confirmTransport($quote, (int) $data['amount'], $data['conditions'], $request->user());

        return back()->with('status', 'Transporte confirmado.');
    }

    public function issue(Request $request, Quote $quote, QuoteService $quotes)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:3000']);
        $quotes->issue($quote, $data['reason'], $request->user());

        return back()->with('status', 'Nueva versión definitiva emitida.');
    }

    public function status(Request $request, Quote $quote, QuoteService $quotes)
    {
        $data = $request->validate(['status' => 'required|in:in_review,awaiting_information,rejected', 'reason' => 'required|string|min:5|max:3000']);
        $quotes->changeStatus($quote, $data['status'], $data['reason'], $request->user());

        return back()->with('status', 'Revisión registrada.');
    }

    public function transition(Request $request, Order $order, OrderService $orders)
    {
        $data = $request->validate(['action' => 'required|string|max:40']);
        $orders->transition($order, $data['action'], $request->only(['reason', 'carrier', 'destination', 'dispatched_at', 'tracking_number', 'notes', 'evidence']), $request->user());

        return back()->with('status', 'Estado del pedido actualizado.');
    }

    public function rotate(Request $request, Quote $quote, TrackingService $tracking)
    {
        $data = $request->validate(['identity_verified' => 'accepted', 'reason' => 'required|string|min:10|max:1000']);
        $token = $tracking->rotate($quote, $request->user(), $data['reason']);

        return back()->with('new_tracking', ['number' => $quote->number, 'code' => $token]);
    }
}
