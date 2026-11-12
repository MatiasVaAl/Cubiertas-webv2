<?php

namespace Tests\Concerns;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkshopSetting;
use App\Services\CartService;
use App\Services\QuoteService;
use App\Services\TrackingService;

trait CreatesCommercialFlow
{
    private function worker(bool $verify = false, bool $admin = false): User
    {
        return User::factory()->create(['role' => $admin ? 'admin' : 'worker', 'can_verify_payments' => $verify]);
    }

    private function acceptedOrder(string $method = 'pickup'): Order
    {
        session()->forget('cart');
        app(CartService::class)->add(['kind' => 'special', 'material_name' => 'Material ficticio de prueba', 'length_cm' => '150', 'width_cm' => '60', 'quantity' => 2]);
        $quotes = app(QuoteService::class);
        $result = $quotes->submit(['name' => 'Cliente de prueba', 'phone' => '123456', 'delivery_method' => $method, 'city' => $method === 'transport' ? 'Comuna de prueba' : null]);
        $quote = $result['quote'];
        $actor = $this->worker();
        $quotes->resolveLine($quote, $quote->lines()->first(), 10000, 'Disponibilidad y precio confirmados en prueba', $actor);
        if ($method === 'transport') {
            $quotes->confirmTransport($quote, 5000, 'Transporte ficticio confirmado para prueba', $actor);
        }
        $version = $quotes->issue($quote, 'Cotización de prueba', $actor);
        app(TrackingService::class)->authenticate($quote->number, $result['token']);

        return $quotes->accept($quote, $version->version);
    }

    private function bankConfigured(): void
    {
        WorkshopSetting::updateOrCreate(['id' => 1], ['bank_details' => ['holder' => 'PRUEBA FICTICIA', 'rut' => 'NO REAL', 'bank' => 'BANCO FICTICIO', 'account_type' => 'PRUEBA', 'account_number' => 'NO TRANSFERIR', 'email' => 'test@example.invalid']]);
    }
}
