<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use Carbon\Carbon;

/**
 * Each subscription now carries its own collections_per_month, collection_type
 * and max_clothes_per_cycle (overridable from the package's defaults at
 * creation time -- see subscriptions.create), so a "cycle" is a month's worth
 * of that many collections, all sharing one flat monthly_price and one clothes
 * cap (see SubscriptionCycle). Cycles are generated one at a time, but never
 * automatically -- once a cycle is exhausted (every collection in it
 * resolved), nothing happens until staff explicitly call renew().
 */
class CollectionScheduler
{
    public static function scheduleFirstCycle(Subscription $subscription): void
    {
        self::scheduleCycle($subscription, Carbon::parse($subscription->start_date));
    }

    /**
     * Starts a fresh cycle beginning on $startsOn, provided the subscription
     * is still active and hasn't run past its end date. Used to resume a
     * paused subscription and by renew().
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
     * The Renew action -- the only way a subscription gets its next cycle
     * now, for scheduled and non-scheduled alike. Refuses to double-renew a
     * cycle that still has open collections in it. $startsOn lets the Renew
     * modal override the default of "right after the previous cycle ended"
     * with a staff-chosen date.
     */
    public static function renew(Subscription $subscription, ?\DateTimeInterface $startsOn = null): void
    {
        $currentCycle = $subscription->cycles()->latest('starts_on')->first();

        if ($currentCycle && ! $currentCycle->isExhausted()) {
            return;
        }

        self::scheduleNextCycle($subscription, $startsOn ?? $currentCycle?->ends_on ?? now());
    }

    /**
     * Creates the cycle record and hands off to generateCollections() for
     * its whole batch of collections.
     */
    protected static function scheduleCycle(Subscription $subscription, Carbon $startsOn): void
    {
        $cycle = $subscription->cycles()->create([
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => null,
            'monthly_price_snapshot' => $subscription->subscriptionPackage->monthly_price,
            'max_clothes_snapshot' => $subscription->max_clothes_per_cycle,
        ]);

        self::generateCollections($subscription, $cycle, $subscription->collection_type, $startsOn, max(1, $subscription->collections_per_month));
    }

    /**
     * Creates $count fresh collections against an existing cycle and sets
     * the cycle's ends_on to match -- shared by scheduleCycle() (a brand
     * new cycle) and SubscriptionController::updateCollectionType() (an
     * untouched cycle whose type changed mid-cycle, regenerating just its
     * still-open slots). Non-scheduled has no fixed dates at all -- the
     * customer can use any of them whenever, so ends_on is left null and
     * only gets backfilled once the cycle actually finishes (see
     * SubscriptionCycle::closeIfExhausted()). Scheduled spaces the cards
     * evenly across one calendar month from $startsOn, and ends_on is set
     * to wherever the LAST card actually lands -- not an artificial fixed
     * +1 month cap the cards get squeezed into regardless of whether they
     * fill it.
     */
    public static function generateCollections(Subscription $subscription, SubscriptionCycle $cycle, string $collectionType, Carbon $startsOn, int $count): void
    {
        if ($collectionType === 'non_scheduled') {
            for ($i = 0; $i < $count; $i++) {
                $subscription->collections()->create([
                    'subscription_cycle_id' => $cycle->id,
                    'scheduled_date' => null,
                    'status' => 'scheduled',
                ]);
            }

            $cycle->update(['ends_on' => null]);

            return;
        }

        // A flat calendar month is still used as the spacing cadence (so a
        // 4-collection/month plan lands roughly weekly), but only to derive
        // the interval -- not as the cycle's actual end date.
        $monthSpan = $startsOn->copy()->addMonthNoOverflow();
        $intervalDays = max(1, intdiv($startsOn->diffInDays($monthSpan), $count));

        // collections has a unique (subscription_id, scheduled_date) index
        // covering every collection this subscription has ever had,
        // cancelled/collected ones included -- not just this cycle's. A
        // naive i*intervalDays date can land on one already taken (e.g.
        // re-generating a cycle mid-flight after an earlier date got used
        // by a since-cancelled collection), so each date is nudged forward
        // day by day until it's actually free.
        $takenDates = $subscription->collections()
            ->whereNotNull('scheduled_date')
            ->pluck('scheduled_date')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $assignedDates = [];

        for ($i = 0; $i < $count; $i++) {
            $date = $startsOn->copy()->addDays($i * $intervalDays);

            while (in_array($date->toDateString(), $takenDates, true)) {
                $date->addDay();
            }

            $takenDates[] = $date->toDateString();
            $assignedDates[] = $date->toDateString();

            $subscription->collections()->create([
                'subscription_cycle_id' => $cycle->id,
                'scheduled_date' => $date->toDateString(),
                'status' => 'scheduled',
            ]);
        }

        $cycle->update(['ends_on' => max($assignedDates)]);
    }
}
