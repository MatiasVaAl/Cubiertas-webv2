<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;
use App\Services\TrackingService;

class QuotePolicy
{
    public function view(?User $user, Quote $quote): bool
    {
        return ($user?->active && in_array($user->role, ['admin', 'worker'], true)) || app(TrackingService::class)->canView($quote);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->active && in_array($user->role, ['admin', 'worker'], true);
    }
}
