<?php

namespace App\Services;

use App\Services\Pricing\PricingService;
use App\Support\Clp;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private ConfigurationService $configurations, private PricingService $pricing) {}

    public function add(array $input, ?string $replaceKey = null): void
    {
        $configuration = $this->configurations->normalize($input);
        $cart = session('cart', []);
        if ($replaceKey !== null) {
            abort_unless(isset($cart[$replaceKey]), 404);
            unset($cart[$replaceKey]);
        }
        $fingerprint = $this->configurations->fingerprint($configuration);
        foreach ($cart as $key => $line) {
            if ($this->configurations->fingerprint($line) === $fingerprint) {
                $cart[$key]['quantity'] += $configuration['quantity'];
                $this->configurations->normalize($cart[$key]);
                session(['cart' => $cart]);

                return;
            }
        }
        if (count($cart) >= 100) {
            throw ValidationException::withMessages(['cart' => 'El carrito admite hasta 100 configuraciones por solicitud.']);
        }
        $cart[(string) Str::uuid()] = $configuration;
        session(['cart' => $cart]);
    }

    public function updateQuantity(string $key, int $quantity): void
    {
        $cart = session('cart', []);
        abort_unless(isset($cart[$key]), 404);
        $cart[$key] = $this->configurations->normalize([...$cart[$key], 'quantity' => $quantity]);
        session(['cart' => $cart]);
    }

    public function remove(string $key): void
    {
        $cart = session('cart', []);
        abort_unless(isset($cart[$key]), 404);
        unset($cart[$key]);
        session(['cart' => $cart]);
    }

    public function split(string $key): string
    {
        $cart = session('cart', []);
        abort_unless(isset($cart[$key]), 404);
        if ($cart[$key]['quantity'] < 2 || count($cart) >= 100) {
            throw ValidationException::withMessages(['cart' => 'Para separar una unidad debe haber al menos dos y espacio en el carrito.']);
        }
        $cart[$key]['quantity']--;
        $newKey = (string) Str::uuid();
        $cart[$newKey] = [...$cart[$key], 'quantity' => 1];
        session(['cart' => $cart]);

        return $newKey;
    }

    public function summary(): array
    {
        $lines = [];
        foreach (session('cart', []) as $key => $configuration) {
            $lines[$key] = $this->pricing->calculate($configuration);
        }

        return ['lines' => $lines, 'known_subtotal_clp' => Clp::sum(array_column($lines, 'known_subtotal_clp')), 'pending' => collect($lines)->contains(fn ($line) => count($line['pending_reasons']) > 0), 'quantity' => collect($lines)->sum('configuration.quantity')];
    }
}
