<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationAudit extends Model
{
    protected $fillable = ['actor_id', 'action', 'entity_type', 'entity_id', 'changed_fields'];

    protected function casts(): array
    {
        return ['changed_fields' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
