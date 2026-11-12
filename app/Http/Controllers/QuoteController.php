<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitQuoteRequest;
use App\Models\Product;
use App\Models\Quote;
use App\Services\CartService;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuoteController extends Controller
{
    public function create(CartService $cart)
    {
        abort_if(! session('cart'), 404);

        $summary = $cart->summary();
        $products = Product::with('images')->whereIn('id', collect($summary['lines'])->pluck('configuration.product_id')->filter())->get()->keyBy('id');

        return view('quotes.create', compact('summary', 'products'));
    }

    public function store(SubmitQuoteRequest $request, QuoteService $quotes)
    {
        $result = $quotes->submit($request->validated());
        $request->session()->forget('cart');

        return redirect()->route('quotes.received')->with('issued_tracking', ['number' => $result['quote']->number, 'code' => $result['token']]);
    }

    public function received()
    {
        abort_unless(session('issued_tracking'), 404);

        return response()->view('quotes.received', ['access' => session('issued_tracking')])->header('Cache-Control', 'private, no-store');
    }

    public function accept(Request $request, Quote $quote, QuoteService $quotes)
    {
        Gate::authorize('view', $quote);
        $data = $request->validate(['version' => 'required|integer|min:1', 'acceptance' => 'accepted']);
        $quotes->accept($quote, (int) $data['version']);

        return back()->with('status', 'Cotización aceptada. Revisa las instrucciones de transferencia del total.');
    }

    public function revision(Request $request, Quote $quote, QuoteService $quotes)
    {
        Gate::authorize('view', $quote);
        $data = $request->validate(['reason' => 'required|string|min:5|max:3000']);
        $quotes->changeStatus($quote, 'in_review', 'El cliente solicita revisión: '.$data['reason']);

        return back()->with('status', 'Solicitud de revisión registrada.');
    }
}
