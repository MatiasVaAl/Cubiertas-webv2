<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\WorkshopSetting;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TrackingController extends Controller
{
    public function authenticate(Request $request, TrackingService $tracking)
    {
        $data = $request->validate(['number' => 'required|string|max:40', 'code' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        $quote = $tracking->authenticate($data['number'], $data['code']);

        return redirect()->route('tracking.show', $quote);
    }

    public function show(Quote $quote)
    {
        Gate::authorize('view', $quote);
        $quote->load(['lines', 'versions', 'order.version', 'order.payments', 'events' => fn ($q) => $q->where('customer_visible', true)]);

        return response()->view('tracking.show', ['quote' => $quote, 'settings' => WorkshopSetting::current()])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
