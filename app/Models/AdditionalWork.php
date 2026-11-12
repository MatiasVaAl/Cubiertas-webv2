<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalWork extends Model
{
    protected $fillable = ['name', 'description', 'pricing_mode', 'price_clp', 'confirmed', 'active'];

    protected function casts(): array
    {
        return ['price_clp' => 'integer', 'confirmed' => 'boolean', 'active' => 'boolean'];
    }
}
