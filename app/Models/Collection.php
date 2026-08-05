<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $fillable = [
        'subscription_id',
        'subscription_cycle_id',
        'scheduled_date',
        'status',
        'collected_at',
        'cancelled_at',
        'cancellation_reason',
        'combined_into_collection_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'collected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subscriptionCycle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCycle::class);
    }

    /**
     * The other collection this one's slot got folded into after being
     * cancelled -- that visit fulfills both slots at once.
     */
    public function combinedInto(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'combined_into_collection_id');
    }

    /**
     * Cancelled collections whose slot was folded into this one.
     */
    public function absorbedCollections(): HasMany
    {
        return $this->hasMany(Collection::class, 'combined_into_collection_id');
    }
}
