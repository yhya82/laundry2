<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionCycle extends Model
{
    protected $fillable = ['subscription_id', 'starts_on', 'ends_on', 'monthly_price_snapshot'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'monthly_price_snapshot' => 'decimal:2',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * Payments belong to the cycle directly (payments.subscription_id +
     * subscription_cycle_id), not to any one collection's order -- every
     * collection in the cycle draws against the same shared balance.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Same '!= refunded' convention used everywhere else money is summed
     * (Order::amountPaid(), Dashboard, reports).
     */
    public function amountPaid(): float
    {
        return (float) $this->payments()->where('status', '!=', 'refunded')->sum('amount');
    }

    public function balanceDue(): float
    {
        return max(0, round((float) $this->monthly_price_snapshot - $this->amountPaid(), 2));
    }

    public function isPaid(): bool
    {
        return $this->balanceDue() <= 0;
    }
}
