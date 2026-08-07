<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionCycle extends Model
{
    protected $fillable = ['subscription_id', 'starts_on', 'ends_on', 'monthly_price_snapshot', 'max_clothes_snapshot'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'monthly_price_snapshot' => 'decimal:2',
            'max_clothes_snapshot' => 'integer',
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
     * (Order::amountPaid(), Dashboard, reports). Memoized per instance --
     * see Order::amountPaid()'s doc comment for why that's safe here too.
     */
    protected ?float $amountPaidCache = null;

    public function amountPaid(): float
    {
        return $this->amountPaidCache ??= (float) $this->payments()->where('status', '!=', 'refunded')->sum('amount');
    }

    public function balanceDue(): float
    {
        return max(0, round((float) $this->monthly_price_snapshot - $this->amountPaid(), 2));
    }

    public function isPaid(): bool
    {
        return $this->balanceDue() <= 0;
    }

    /**
     * Total clothes across every collection in this cycle so far -- the cap
     * (max_clothes_snapshot) is cycle-wide, however many visits it takes to
     * reach it, not a per-collection thing like the package's own
     * clothes_allowance. Memoized per instance, same reasoning as
     * amountPaid() -- always read, never re-checked after a change within
     * the same request.
     */
    protected ?int $clothesCollectedCache = null;

    public function clothesCollected(): int
    {
        return $this->clothesCollectedCache ??= (int) OrderClothesLine::whereHas(
            'packageLine.order.collection',
            fn ($q) => $q->where('subscription_cycle_id', $this->id)
        )->sum('quantity');
    }

    public function clothesRemaining(): int
    {
        return max(0, $this->max_clothes_snapshot - $this->clothesCollected());
    }

    /**
     * True once every collection belonging to this cycle has been resolved
     * (collected or cancelled) -- the point at which Renew becomes available
     * instead of the old auto-continue behavior.
     */
    public function isExhausted(): bool
    {
        return ! $this->collections()->where('status', 'scheduled')->exists();
    }

    /**
     * Non-scheduled cycles are created with ends_on left null (see
     * CollectionScheduler::scheduleCycle()) since there's no fixed schedule
     * to derive one from upfront. Called after any collection in the cycle
     * resolves, this backfills ends_on to today once the cycle is actually
     * exhausted, so it reflects when the cycle really finished.
     */
    public function closeIfExhausted(): void
    {
        if ($this->ends_on !== null) {
            return;
        }

        if ($this->isExhausted()) {
            $this->update(['ends_on' => now()->toDateString()]);
        }
    }
}
