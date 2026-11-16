<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use Carbon\Carbon;
use DateTimeInterface;
use DomainException;

/**
 * Each subscription carries its own collections_per_month, collection_type
 * and max_clothes_per_cycle, so a "cycle" is that many collections sharing
 * one flat monthly_price and one clothes cap (see SubscriptionCycle).
 * Cycles are never created automatically -- once a cycle is exhausted,
 * nothing happens until staff explicitly renew.
 *
 * Non-scheduled cycles are a bundle of N visits: no dates and no expiry,
 * finished only once every visit is used. subscriptions.end_date belongs to
 * scheduled subscriptions only, and always equals the last scheduled
 * collection date of the current cycle (see generateCollections()). It is
 * never padded out by a month, and never touched for a non-scheduled one.
 */
class CollectionScheduler
{
    /**
     * Create the first cycle for a subscription.
     */
    public static function scheduleFirstCycle(Subscription $subscription): SubscriptionCycle
    {
        return self::scheduleCycle($subscription, Carbon::parse($subscription->start_date));
    }

    /**
     * Start the next cycle once the current one is exhausted -- the only way
     * a subscription gets a new cycle. The new cycle may start on the same
     * day as the previous one; the exhausted check is what stops a renewal
     * while visits are still open.
     *
     * The caller should run this inside a database transaction.
     *
     * @throws DomainException when the renewal isn't allowed
     */
    public static function renew(Subscription $subscription, ?DateTimeInterface $startsOn = null): SubscriptionCycle
    {
        if ($subscription->status !== 'active') {
            throw new DomainException('Only an active subscription can be renewed.');
        }

        $currentCycle = $subscription->cycles()
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($currentCycle && ! $currentCycle->isExhausted()) {
            throw new DomainException('This subscription still has open collections in its current cycle.');
        }

        $startsOn = $startsOn
            ? Carbon::parse($startsOn->format('Y-m-d'))->startOfDay()
            : Carbon::today();

        return self::scheduleCycle($subscription, $startsOn);
    }

    /**
     * Create a cycle and generate its collections.
     */
    public static function scheduleCycle(Subscription $subscription, Carbon $startsOn): SubscriptionCycle
    {
        $startsOn = $startsOn->copy()->startOfDay();

        $cycle = $subscription->cycles()->create([
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => null,
            'monthly_price_snapshot' => $subscription->subscriptionPackage->monthly_price,
            'max_clothes_snapshot' => $subscription->max_clothes_per_cycle,
        ]);

        self::generateCollections(
            $subscription,
            $cycle,
            $subscription->collection_type,
            $startsOn,
            max(1, $subscription->collections_per_month)
        );

        return $cycle->fresh();
    }

    /**
     * Create $count collections for $cycle. Used for a brand-new cycle, and
     * by SubscriptionController::update() to rebuild a cycle nothing has
     * been collected from yet.
     *
     * Scheduled: collections are spread evenly over one calendar month from
     * $startsOn. The cycle's ends_on and the subscription's end_date both
     * become the last collection date.
     *
     * Non-scheduled: no dates and ends_on stays null until the bundle is
     * used up (SubscriptionCycle::closeIfExhausted()). end_date is left
     * exactly as it was -- kept, but not used or shown.
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

        // A calendar month sets the spacing (so 4 collections land roughly
        // weekly) -- it is not the cycle's end date.
        $monthSpan = $startsOn->copy()->addMonthNoOverflow();

        $intervalDays = max(1, intdiv((int) $startsOn->diffInDays($monthSpan), $count));

        // collections has a unique (subscription_id, scheduled_date) index
        // covering every collection this subscription has ever had, so a
        // date already taken (e.g. by the previous cycle's last collection,
        // when renewing on the same day) is nudged forward until it's free.
        $takenDates = $subscription->collections()
            ->whereNotNull('scheduled_date')
            ->pluck('scheduled_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $assignedDates = [];

        for ($i = 0; $i < $count; $i++) {
            $date = $startsOn->copy()->addDays($i * $intervalDays);

            while (in_array($date->toDateString(), $takenDates, true)) {
                $date->addDay();
            }

            $dateString = $date->toDateString();

            $takenDates[] = $dateString;
            $assignedDates[] = $dateString;

            $subscription->collections()->create([
                'subscription_cycle_id' => $cycle->id,
                'scheduled_date' => $dateString,
                'status' => 'scheduled',
            ]);
        }

        $lastDate = max($assignedDates);

        $cycle->update(['ends_on' => $lastDate]);
        $subscription->update(['end_date' => $lastDate]);
    }
}
