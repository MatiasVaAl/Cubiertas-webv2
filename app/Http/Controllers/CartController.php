<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(CartService $cart)
    {
        $summary = $cart->summary();
        $products = Product::with('images')->whereIn('id', collect($summary['lines'])->pluck('configuration.product_id')->filter())->get()->keyBy('id');

        return view('cart', compact('summary', 'products'));
    }

    public function update(Request $request, string $line, CartService $cart)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:10000']);
        $cart->updateQuantity($line, (int) $data['quantity']);

        return back()->with('status', 'Cantidad actualizada.');
    }

    public function destroy(string $line, CartService $cart)
    {
        $cart->remove($line);

        return back()->with('status', 'Configuración eliminada.');
    }

    public function split(string $line, CartService $cart)
    {
        return redirect()->route('cart.edit', $cart->split($line));
    }
}
