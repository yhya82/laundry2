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
        'completed' => 'collection',
    ];

    public const TERMINAL_STATUSES = ['collection', 'cancelled'];

    protected $fillable = [
        'order_number',
        'customer_id',
        'collection_id',
        'washing_machine_id',
        'user_id',
        'assigned_to',
        'order_source',
        'subtotal',
        'discount',
        'discount_reason',
        'extra_charge',
        'extra_charge_reason',
        'cycle_overage_charge',
        'cancellation_reason',
        'collected_by_type',
        'collected_by_name',
        'collected_by_phone',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'extra_charge' => 'decimal:2',
            'cycle_overage_charge' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * withTrashed() -- a soft-deleted customer's past orders still need a
     * real customer to render/settle against (name display, store credit,
     * receiving a payment), not a silently-null relation.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function washingMachine(): BelongsTo
    {
        return $this->belongsTo(WashingMachine::class);
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

    /**
     * Purely informational -- see OrderController::assign() -- never gates
     * who can actually act on the order, only (when
     * order.assignment_enabled is on) who a view-only staff member sees it
     * in their own list at all.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function nextStatus(): ?string
    {
        return self::STAGE_SEQUENCE[$this->status] ?? null;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * An order is "high" priority if any of its package lines used a
     * laundry package flagged high priority in the catalog.
     */
    public function priority(): string
    {
        return $this->packageLines->contains(fn (OrderPackageLine $line) => $line->laundryPackage?->priority === 'high')
            ? 'high'
            : 'normal';
    }

    /**
     * Same '!= refunded' convention used everywhere else money is summed
     * (Dashboard, reports) -- a partially_refunded payment still means that
     * much was paid toward the order; only a fully refunded one doesn't.
     * Memoized per instance -- balanceDue()/paymentStatus()/
     * combinedPaymentStatus() all call this independently, and every caller
     * across the app only ever reads it before creating a new payment on
     * this instance within a request, never after, so a cached value never
     * goes stale in practice.
     */
    protected ?float $amountPaidCache = null;

    public function amountPaid(): float
    {
        return $this->amountPaidCache ??= (float) $this->payments()->where('status', '!=', 'refunded')->sum('amount');
    }

    /**
     * Cancelled orders are never "awaiting payment" regardless of the math
     * -- there's no future collection expected once an order is cancelled.
     */
    public function balanceDue(): float
    {
        if ($this->status === 'cancelled') {
            return 0.0;
        }

        return max(0, round($this->total_amount - $this->amountPaid(), 2));
    }

    /**
     * 'paid'/'partial'/'unpaid' -- for cancelled orders balanceDue() is
     * always 0, so this naturally resolves to 'paid' if something was
     * collected before cancellation, or 'unpaid' if nothing ever was.
     */
    public function paymentStatus(): string
    {
        if ($this->balanceDue() <= 0) {
            return $this->amountPaid() > 0 ? 'paid' : 'unpaid';
        }

        return $this->amountPaid() > 0 ? 'partial' : 'unpaid';
    }

    /**
     * Same as paymentStatus(), but folds in the subscription cycle's
     * balance too. A subscription order's own subtotal is always 0 -- the
     * flat monthly fee lives on the cycle -- so an order with no
     * over-allowance extra_charge would otherwise show "unpaid" here even
     * when the whole cycle is fully settled, contradicting the cycle-status
     * line shown alongside it. Walk-in orders (no cycle) fall back to
     * paymentStatus() unchanged.
     */
    public function combinedPaymentStatus(): string
    {
        $cycle = $this->subscriptionCycle();

        if (! $cycle) {
            return $this->paymentStatus();
        }

        $totalDue = $this->balanceDue() + $cycle->balanceDue();
        $totalPaid = $this->amountPaid() + $cycle->amountPaid();

        if ($totalDue <= 0) {
            return $totalPaid > 0 ? 'paid' : 'unpaid';
        }

        return $totalPaid > 0 ? 'partial' : 'unpaid';
    }

    /**
     * The billing cycle this collection's flat subscription fee actually
     * lives on -- null for walk-in orders, and for a legacy subscription
     * order that predates cycles (its own subtotal carries the price
     * instead, see the Terminal's submitSubscriptionCollection()).
     */
    public function subscriptionCycle(): ?SubscriptionCycle
    {
        return $this->collection?->subscriptionCycle;
    }

    /**
     * Who physically picked this order up -- the customer themself (their
     * own name, not a generic "Customer" label) or the recorded stand-in.
     * Null until the order actually reaches the collection stage.
     */
    public function collectedByDisplayName(): ?string
    {
        if ($this->collected_by_type === 'customer') {
            return $this->customer->full_name;
        }

        return $this->collected_by_type === 'other' ? $this->collected_by_name : null;
    }
}
