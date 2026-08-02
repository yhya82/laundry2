<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collection extends Model
{
    protected $fillable = ['subscription_id', 'scheduled_date', 'status', 'collected_at'];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'collected_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
