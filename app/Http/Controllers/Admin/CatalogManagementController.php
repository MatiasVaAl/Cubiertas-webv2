<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdditionalWork;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\WidthRate;
use App\Services\AdministrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class CatalogManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->validate(['q' => 'nullable|string|max:100'])['q'] ?? '';

        return view('admin.catalog', ['products' => Product::with('images')->when($search, fn ($q) => $q->where('name', 'ilike', '%'.$search.'%'))->orderBy('name')->paginate(10)->withQueryString(), 'works' => AdditionalWork::orderBy('name')->get()]);
    }

    public function product(Request $request, AdministrationService $admin, ?Product $product = null)
    {
        $product ??= new Product;
        $data = $request->validate(['name' => 'required|string|max:255', 'code' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($product->id)], 'description' => 'nullable|string|max:5000', 'color' => 'nullable|string|max:255', 'finish' => 'nullable|string|max:255', 'characteristics' => 'nullable|string|max:5000', 'available' => 'nullable|boolean', 'demonstration' => 'nullable|boolean']);
        $data['available'] = $request->boolean('available');
        $data['demonstration'] = $request->boolean('demonstration');
        $admin->save($product, $data, $request->user());

        return back()->with('status', 'Acabado guardado.');
    }

    public function image(Request $request, Product $product, AdministrationService $admin)
    {
        $data = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'], 'alt' => 'required|string|max:255', 'position' => 'required|integer|min:0|max:1000']);
        $path = $request->file('image')->store('catalog', 'public');
        try {
            $admin->save(new ProductImage, ['product_id' => $product->id, 'path' => $path, 'alt' => $data['alt'], 'position' => $data['position']], $request->user());
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return back()->with('status', 'Fotografía agregada.');
    }

    public function removeImage(Request $request, ProductImage $image, AdministrationService $admin)
    {
        DB::transaction(function () use ($request, $image, $admin) {
            $admin->audit($image, 'removed', ['path'], $request->user());
            $image->delete();
        });
        Storage::disk('public')->delete($image->path);

        return back()->with('status', 'Fotografía retirada del catálogo.');
    }

    public function additional(Request $request, AdministrationService $admin, ?AdditionalWork $work = null)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:3000', 'pricing_mode' => 'required|in:manual,per_unit', 'price_clp' => 'nullable|required_if:pricing_mode,per_unit|integer|min:0|max:1000000000000', 'active' => 'nullable|boolean', 'confirmed' => 'nullable|boolean']);
        $data['active'] = $request->boolean('active');
        $data['confirmed'] = $request->boolean('confirmed');
        $admin->save($work ?? new AdditionalWork, $data, $request->user());

        return back()->with('status', 'Trabajo adicional guardado.');
    }

    public function rates()
    {
        return view('admin.rates', ['rates' => WidthRate::with('product')->orderBy('product_id')->orderBy('min_width_cm')->paginate(20), 'products' => Product::orderBy('name')->get()]);
    }

    public function rate(Request $request, AdministrationService $admin, ?WidthRate $rate = null)
    {
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id', 'min_width_cm' => 'required|numeric|decimal:0,2|min:0|max:9999999999', 'max_width_cm' => 'required|numeric|decimal:0,2|gt:min_width_cm|max:9999999999', 'price_per_m2_clp' => 'required|integer|min:1|max:1000000000000', 'active' => 'nullable|boolean', 'confirmed' => 'nullable|boolean']);
        $data['active'] = $request->boolean('active');
        $data['confirmed'] = $request->boolean('confirmed');
        $admin->saveRate($rate ?? new WidthRate, $data, $request->user());

        return back()->with('status', 'Tarifa guardada. El historial emitido conserva sus importes anteriores.');
    }
}
