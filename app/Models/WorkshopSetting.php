<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkshopSetting extends Model
{
    protected $fillable = ['id', 'formula_confirmed', 'dimension_limits', 'contact', 'bank_details', 'payment_instructions'];

    protected $hidden = ['bank_details'];

    protected function casts(): array
    {
        return ['formula_confirmed' => 'boolean', 'dimension_limits' => 'array', 'contact' => 'array', 'bank_details' => 'encrypted:array'];
    }

    public static function current(): self
    {
        return static::find(1) ?? new static(['formula_confirmed' => false]);
    }

    public function bankIsReady(): bool
    {
        foreach (['holder', 'rut', 'bank', 'account_type', 'account_number', 'email'] as $field) {
            if (blank($this->bank_details[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
