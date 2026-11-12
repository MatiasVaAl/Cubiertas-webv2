<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->validate(['q' => 'nullable|string|max:100'])['q'] ?? '';
        $products = Product::with('images')->when(app()->isProduction(), fn ($q) => $q->where('demonstration', false))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%')->orWhere('code', 'ilike', '%'.$search.'%')))
            ->orderBy('name')->paginate(12)->withQueryString();

        return view('catalog.index', compact('products', 'search'));
    }

    public function show(Product $product)
    {
        abort_if(app()->isProduction() && $product->demonstration, 404);

        return view('catalog.show', ['product' => $product->load('images')]);
    }
}
