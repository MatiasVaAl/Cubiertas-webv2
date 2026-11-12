<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Services\TrackingService;

class PaymentPolicy
{
    public function view(?User $user, Payment $payment): bool
    {
        return ($user?->active && in_array($user->role, ['admin', 'worker'], true)) || app(TrackingService::class)->canView($payment->order->quote);
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $user->active && in_array($user->role, ['admin', 'worker'], true) && $user->can_verify_payments;
    }
}
