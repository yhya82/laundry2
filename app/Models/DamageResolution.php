<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageResolution extends Model
{
    protected $fillable = ['damage_record_id', 'resolution_type', 'amount', 'notes', 'resolved_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function damageRecord(): BelongsTo
    {
        return $this->belongsTo(DamageRecord::class);
    }
}
