<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quote extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pending_review', 'current_version' => 0];

    protected function casts(): array
    {
        return ['delivery_details' => 'array', 'transport_clp' => 'integer', 'current_version' => 'integer', 'accepted_at' => 'datetime'];
    }

    public function getNumberAttribute(): string
    {
        return 'CT-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class)->orderByDesc('version');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class)->whereNull('superseded_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(StateEvent::class)->orderByDesc('id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(TrackingCredential::class);
    }
}
