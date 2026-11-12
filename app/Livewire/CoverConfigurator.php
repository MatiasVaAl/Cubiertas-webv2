<?php

namespace App\Livewire;

use App\Models\AdditionalWork;
use App\Models\Product;
use App\Models\WorkshopSetting;
use App\Services\CartService;
use App\Services\Pricing\PricingService;
use App\Support\CoverMeasurements;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class CoverConfigurator extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?string $editKey = null;

    #[Locked]
    public ?string $referencePath = null;

    #[Locked]
    public ?array $preview = null;

    public $reference;

    public string $kind = 'standard';

    public string $product_id = '';

    public string $material_name = '';

    public string $material_description = '';

    public string $length_cm = '';

    public string $lengthInput = '';

    public string $lengthUnit = 'm';

    public string $width_cm = '';

    public string $quantity = '1';

    public array $additional_ids = [];

    public string $notes = '';

    public bool $added = false;

    public bool $showAllFinishes = false;

    public function mount(?string $line = null): void
    {
        $this->product_id = (string) request('product', '');
        $this->kind = request()->boolean('special') ? 'special' : 'standard';
        $line ??= request()->route('line');
        if ($line) {
            $this->editKey = $line;
            $line = session('cart.'.$this->editKey);
            abort_unless(is_array($line), 404);
            foreach ($this->input() as $key => $value) {
                $this->{$key} = $key === 'additional_ids' ? $line[$key] : (string) ($line[$key] ?? '');
            }
            $this->referencePath = $line['reference_path'] ?? null;
            $this->syncLengthDisplay();
        }
        $this->refreshPreview();
    }

    public function updated(string $property): void
    {
        $this->added = false;
        $this->resetValidation();
        if ($property === 'lengthInput') {
            $raw = str_replace(',', '.', $this->lengthInput);
            $pattern = $this->lengthUnit === 'm' ? '/^\d{1,8}(?:\.\d{1,4})?$/D' : '/^\d{1,10}(?:\.\d{1,2})?$/D';
            $this->length_cm = preg_match($pattern, $raw) ? bcmul($raw, $this->lengthUnit === 'm' ? '100' : '1', 2) : '';
        } elseif ($property === 'lengthUnit') {
            if (! in_array($this->lengthUnit, ['m', 'cm'], true)) {
                throw ValidationException::withMessages(['length_cm' => 'Selecciona metros o centímetros.']);
            }
            $this->syncLengthDisplay();
        } elseif ($property === 'length_cm') {
            $this->syncLengthDisplay();
        }
        if ($property === 'reference') {
            $this->referencePath = null;
        }
        $this->refreshPreview();
    }

    private function syncLengthDisplay(): void
    {
        $raw = str_replace(',', '.', $this->length_cm);
        $this->lengthInput = preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)
            ? rtrim(rtrim(bcdiv($raw, $this->lengthUnit === 'm' ? '100' : '1', $this->lengthUnit === 'm' ? 4 : 2), '0'), '.')
            : '';
    }

    private function refreshPreview(): void
    {
        $this->preview = null;
        try {
            $this->preview = app(PricingService::class)->calculate($this->input());
        } catch (ValidationException) {
            // No mostrar errores prematuros mientras se completa el formulario.
        }
    }

    public function calculate(PricingService $pricing): void
    {
        $this->preview = null;
        $this->preview = $pricing->calculate($this->input());
    }

    public function adjustQuantity(int $direction): void
    {
        abort_unless(in_array($direction, [-1, 1], true), 422);
        if (! preg_match('/^\d{1,5}$/D', $this->quantity)) {
            throw ValidationException::withMessages(['quantity' => 'Indica una cantidad entera.']);
        }
        $this->quantity = (string) max(1, min(10000, (int) $this->quantity + $direction));
        $this->refreshPreview();
    }

    public function changeLengthUnit(string $unit): void
    {
        if (! in_array($unit, ['m', 'cm'], true)) {
            throw ValidationException::withMessages(['length_cm' => 'Selecciona metros o centímetros.']);
        }
        // La acción recibe primero las ediciones pendientes en la unidad anterior.
        $this->lengthUnit = $unit;
        $this->syncLengthDisplay();
        $this->refreshPreview();
    }

    public function adjustMeasure(string $dimension, int $direction): void
    {
        abort_unless(in_array($dimension, ['length_cm', 'width_cm'], true) && in_array($direction, [-1, 1], true), 422);
        $raw = str_replace(',', '.', $this->{$dimension});
        if ($raw !== '' && ! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw ValidationException::withMessages([$dimension => 'Indica una medida numérica válida.']);
        }
        $next = bcadd($raw === '' ? '0' : $raw, (string) (($dimension === 'length_cm' ? 10 : 1) * $direction), 2);
        if (bccomp($next, '0', 2) > 0) {
            $this->{$dimension} = $next;
        }
        $this->syncLengthDisplay();
        $this->refreshPreview();
    }

    public function input(): array
    {
        return $this->only(['kind', 'product_id', 'material_name', 'material_description', 'length_cm', 'width_cm', 'quantity', 'additional_ids', 'notes']);
    }

    public function addToCart(CartService $cart, PricingService $pricing)
    {
        $this->calculate($pricing);
        $input = $this->input();
        $newPath = null;
        if ($this->kind === 'special' && $this->reference) {
            $this->validate(['reference' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120']]);
            $newPath = $this->reference->store('references', 'local');
        }
        try {
            $cart->add([...$input, 'reference_path' => $this->kind === 'special' ? ($newPath ?? $this->referencePath) : null], $this->editKey);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($newPath) {
            $this->referencePath = $newPath;
            $this->reference = null;
        }
        $this->dispatch('cart-updated');
        if ($this->editKey) {
            return redirect()->route('cart.index')->with('status', 'Cambios guardados en el carrito.');
        }
        $this->added = true;
        $this->dispatch('cover-added');
    }

    public function render()
    {
        $products = Product::with('images')->when(app()->isProduction(), fn ($q) => $q->where('demonstration', false))->orderBy('name')->get();
        $selected = $this->kind === 'standard' ? $products->firstWhere('id', $this->product_id) : null;
        $visible = $products;
        if (! $this->showAllFinishes && $products->count() > 6) {
            $visible = $selected ? $products->reject(fn ($product) => $product->id === $selected->id)->prepend($selected)->take(6) : $products->take(6);
        }

        return view('livewire.cover-configurator', [
            'products' => $products,
            'visibleProducts' => $visible,
            'selectedProduct' => $selected,
            'works' => AdditionalWork::where('active', true)->orderBy('name')->get(),
            'contact' => WorkshopSetting::current()->contact ?? [],
            'surface' => CoverMeasurements::area($this->length_cm, $this->width_cm),
        ]);
    }
}
