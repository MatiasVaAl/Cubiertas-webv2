<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Services\TrackingService;

class OrderPolicy
{
    public function view(?User $user, Order $order): bool
    {
        return ($user?->active && in_array($user->role, ['admin', 'worker'], true)) || app(TrackingService::class)->canView($order->quote);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->active && in_array($user->role, ['admin', 'worker'], true);
    }
}
