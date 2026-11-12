<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\TrackingCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    public function issue(Quote $quote): string
    {
        $token = bin2hex(random_bytes(32));
        $quote->credentials()->create(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(config('cubiertas.tracking_days'))]);

        return $token;
    }

    public function authenticate(string $number, string $token): Quote
    {
        $credential = TrackingCredential::where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first();
        $quote = $credential?->quote;
        if (! $quote || ! hash_equals($quote->number, strtoupper(trim($number)))) {
            throw ValidationException::withMessages(['code' => 'El número y el código no son válidos o el acceso ha caducado.']);
        }
        session()->regenerate();
        session()->put('tracking.'.$quote->id, ['credential_id' => $credential->id, 'until' => now()->addHours(2)->timestamp]);

        return $quote;
    }

    public function canView(Quote $quote): bool
    {
        $grant = session('tracking.'.$quote->id);
        if (! is_array($grant) || ($grant['until'] ?? 0) <= now()->timestamp) {
            return false;
        }

        return $quote->credentials()->whereKey($grant['credential_id'])->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }

    public function rotate(Quote $quote, User $actor, string $reason): string
    {
        Gate::forUser($actor)->authorize('update', $quote);

        return DB::transaction(function () use ($quote, $actor, $reason) {
            $quote = Quote::lockForUpdate()->findOrFail($quote->id);
            $quote->credentials()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $token = $this->issue($quote);
            app(HistoryService::class)->record($quote, 'tracking_rotated', 'Acceso privado renovado tras verificación de identidad. '.$reason, $actor, false);

            return $token;
        });
    }
}
