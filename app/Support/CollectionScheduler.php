<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\Subscription;
use Carbon\Carbon;

/**
 * Each subscription_package now carries a collections_per_month count, so a
 * "cycle" is a month's worth of that many collections, spaced evenly across
 * it, all sharing one flat monthly_price (see SubscriptionCycle::balanceDue()
 * and the Terminal's subscription-mode pricing). Cycles are generated one at
 * a time -- the next cycle's whole batch is only created once every
 * collection in the current one has been resolved, via
 * maybeScheduleNextCycleAfter().
 */
class CollectionScheduler
{
    public static function scheduleFirstCycle(Subscription $subscription): void
    {
        self::scheduleCycle($subscription, Carbon::parse($subscription->start_date));
    }

    /**
     * Starts a fresh cycle beginning on $startsOn, provided the subscription
     * is still active and hasn't run past its end date. Used both to resume
     * a paused subscription and internally once a cycle is fully resolved.
     */
    public static function scheduleNextCycle(Subscription $subscription, \DateTimeInterface $startsOn): void
    {
        if ($subscription->status !== 'active') {
            return;
        }

        $startsOn = Carbon::instance($startsOn);

        if ($subscription->end_date && $startsOn->greaterThanOrEqualTo($subscription->end_date)) {
            return;
        }

        self::scheduleCycle($subscription, $startsOn);
    }

    /**
     * Call after a single collection is resolved (collected/cancelled). Only
     * actually schedules the next cycle once nothing 'scheduled' remains in
     * the one $resolvedCollection belonged to -- a cycle's collections all
     * resolve independently, in any order, so this is safe to call after
     * each one and only fires on the last.
     */
    public static function maybeScheduleNextCycleAfter(Collection $resolvedCollection): void
    {
        $cycle = $resolvedCollection->subscriptionCycle;

        // Legacy collections created before cycles existed have no cycle to
        // check against -- nothing left to do, they were already
        // single-collection "cycles" of their own.
        if (! $cycle) {
            return;
        }

        if ($cycle->collections()->where('status', 'scheduled')->exists()) {
            return;
        }

        self::scheduleNextCycle($resolvedCollection->subscription, $cycle->ends_on);
    }

    /**
     * Creates the cycle record and its whole batch of collections up front,
     * spaced by dividing the cycle's length evenly across the package's
     * collections_per_month. A high count relative to the month's length is
     * floored to a 1-day minimum spacing so dates never collide.
     */
    protected static function scheduleCycle(Subscription $subscription, Carbon $startsOn): void
    {
        $package = $subscription->subscriptionPackage;
        $count = max(1, $package->collections_per_month);
        $endsOn = $startsOn->copy()->addMonthNoOverflow();
        $intervalDays = max(1, intdiv($startsOn->diffInDays($endsOn), $count));

        $cycle = $subscription->cycles()->create([
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'monthly_price_snapshot' => $package->monthly_price,
        ]);

        for ($i = 0; $i < $count; $i++) {
            $subscription->collections()->create([
                'subscription_cycle_id' => $cycle->id,
                'scheduled_date' => $startsOn->copy()->addDays($i * $intervalDays)->toDateString(),
                'status' => 'scheduled',
            ]);
        }
    }
}
