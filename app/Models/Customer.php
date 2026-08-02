<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'customer_type',
        'address',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'store_credit_balance' => 'decimal:2',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * payments has no direct customer_id -- it belongs to either an order or
     * a subscription. This covers the order path (walk-in); a subscription
     * payment history view can be added once Phase 05 exists.
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Order::class);
    }

    public function damageRecords(): HasManyThrough
    {
        return $this->hasManyThrough(DamageRecord::class, Order::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
