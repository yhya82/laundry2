<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

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
     * a subscription. This only covers the order (walk-in) path; use
     * paymentsQuery() for a combined view across both.
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Order::class);
    }

    /**
     * Combined walk-in + subscription payments, since Eloquent has no single
     * relation that follows "belongs to order OR subscription, both of which
     * belong to this customer."
     */
    public function paymentsQuery(): Builder
    {
        return Payment::query()->where(
            fn ($q) => $q->whereIn('order_id', $this->orders()->pluck('id'))
                ->orWhereIn('subscription_id', $this->subscriptions()->pluck('id'))
        );
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
