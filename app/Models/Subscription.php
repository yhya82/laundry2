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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
