<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidthRate extends Model
{
    protected $fillable = ['product_id', 'min_width_cm', 'max_width_cm', 'price_per_m2_clp', 'confirmed', 'active'];

    protected function casts(): array
    {
        return ['min_width_cm' => 'decimal:2', 'max_width_cm' => 'decimal:2', 'price_per_m2_clp' => 'integer', 'confirmed' => 'boolean', 'active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
