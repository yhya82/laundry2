<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;

class CustomerBalanceService
{
    public function hasOutstandingBalance(Customer $customer): bool
    {
        // Check balances belonging directly to orders.
        // Cancelled orders do not contribute their own balance.
        $hasUnpaidOrder = $customer->orders()
            ->where('status', '!=', 'cancelled')
            ->get()
            ->contains(
                fn (Order $order) => $order->balanceDue() > 0
            );

        if ($hasUnpaidOrder) {
            return true;
        }

        // Check each subscription cycle exactly once.
        // A cycle's monthly fee is shared across its collections.
        $hasUnpaidCycle = $customer->subscriptions()
            ->with('cycles')
            ->get()
            ->flatMap(
                fn (Subscription $subscription) => $subscription->cycles
            )
            ->contains(
                fn (SubscriptionCycle $cycle) => $cycle->balanceDue() > 0
            );

        return $hasUnpaidCycle;
    }
}
