<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['payment_status' => 'pending', 'manufacturing_status' => 'pending', 'on_hold' => false];

    protected function casts(): array
    {
        return ['total_clp' => 'integer', 'on_hold' => 'boolean', 'dispatch_details' => 'array', 'superseded_at' => 'datetime'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class, 'quote_version_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(StateEvent::class)->orderByDesc('id');
    }
}
