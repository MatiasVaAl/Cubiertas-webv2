<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'description', 'color', 'finish', 'characteristics', 'available', 'demonstration'];

    protected function casts(): array
    {
        return ['available' => 'boolean', 'demonstration' => 'boolean'];
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(WidthRate::class);
    }
}
