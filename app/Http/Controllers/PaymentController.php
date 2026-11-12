<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order, PaymentService $payments)
    {
        Gate::authorize('view', $order);
        $request->validate(['receipt' => 'required|file|max:5120']);
        $payments->upload($order, $request->file('receipt'));

        return back()->with('status', 'Comprobante recibido. El equipo debe verificar el ingreso del dinero.');
    }

    public function receipt(Payment $payment)
    {
        Gate::authorize('view', $payment);
        abort_unless(Storage::disk('local')->exists($payment->path), 404);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$payment->mime];

        return Storage::disk('local')->download($payment->path, 'comprobante-'.$payment->id.'.'.$extension, ['Content-Type' => $payment->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function verify(Request $request, Payment $payment, PaymentService $payments)
    {
        $data = $request->validate(['decision' => 'required|in:approve,observe', 'observations' => 'nullable|string|max:3000', 'bank_received' => 'nullable|boolean']);
        $payments->verify($payment, $request->user(), $data['decision'] === 'approve', $data['observations'] ?? '', $request->boolean('bank_received'));

        return back()->with('status', 'Verificación registrada.');
    }
}
