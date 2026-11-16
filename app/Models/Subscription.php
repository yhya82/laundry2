<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'customer_id',
        'subscription_package_id',
        'status',
        'start_date',
        'end_date',
        'collections_per_month',
        'collection_type',
        'max_clothes_per_cycle',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'collections_per_month' => 'integer',
            'max_clothes_per_cycle' => 'integer',
        ];
    }

    /**
     * withTrashed() -- a soft-deleted customer's subscriptions/collections
     * still need a real customer to render/settle against (name display,
     * store credit, recording a cycle payment), not a silently-null relation.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(SubscriptionCycle::class);
    }

    /**
     * The most recently created cycle. Picked by id, not starts_on: a
     * renewal may start on the same day as the previous cycle (or on an
     * earlier date than a cycle staff dated ahead), so starts_on can tie
     * or go backwards, while creation order can't.
     */
    public function currentCycle(): ?SubscriptionCycle
    {
        return $this->cycles()->latest('id')->first();
    }

    /**
     * True once any collection in any cycle has been collected. Uses the
     * loaded collections relation when there is one (customer page), so a
     * list of subscriptions doesn't cost a query each.
     */
    public function hasCollectedVisit(): bool
    {
        if ($this->relationLoaded('collections')) {
            return $this->collections->contains('status', 'collected');
        }

        return $this->collections()->where('status', 'collected')->exists();
    }

    /**
     * Why this subscription can't be edited, or null if it can. Editing is
     * only for an active subscription that has never had a visit collected,
     * in any cycle -- after that, the plan only changes through Renew. A
     * paused one has to be resumed first.
     */
    public function editBlockedReason(): ?string
    {
        return match (true) {
            $this->status === 'cancelled' => 'A cancelled subscription can no longer be edited.',
            $this->hasCollectedVisit() => 'This subscription already has a collected visit, so it can no longer be edited. Use Renew to change the plan.',
            $this->status !== 'active' => 'Resume this subscription before editing it.',
            default => null,
        };
    }

    public function isEditable(): bool
    {
        return $this->editBlockedReason() === null;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
