<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\StateEvent;
use App\Models\User;

class HistoryService
{
    public function record(Quote $quote, string $type, string $message, ?User $actor = null, bool $visible = true): void
    {
        StateEvent::create(['quote_id' => $quote->id, 'order_id' => $quote->order()->value('id'), 'actor_id' => $actor?->id, 'type' => $type, 'message' => $message, 'customer_visible' => $visible]);
    }
}
