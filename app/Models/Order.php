<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /**
     * Mirrors trg_orders_status_transition_guard exactly -- kept here so the
     * UI only ever offers the one valid next stage, never a jump. The
     * trigger is still the real enforcement; this just avoids the UI
     * offering something the DB would reject.
     */
    public const STAGE_SEQUENCE = [
        'received' => 'sorting',
        'sorting' => 'washing',
        'washing' => 'drying',
        'drying' => 'ironing',
        'ironing' => 'packaging',
        'packaging' => 'completed',
    ];

    public const TERMINAL_STATUSES = ['completed', 'cancelled'];

    protected $fillable = [
        'order_number',
        'customer_id',
        'collection_id',
        'user_id',
        'order_source',
        'subtotal',
        'discount',
        'discount_reason',
        'extra_charge',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'extra_charge' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function damageRecords(): HasMany
    {
        return $this->hasMany(DamageRecord::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function packageLines(): HasMany
    {
        return $this->hasMany(OrderPackageLine::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function nextStatus(): ?string
    {
        return self::STAGE_SEQUENCE[$this->status] ?? null;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
