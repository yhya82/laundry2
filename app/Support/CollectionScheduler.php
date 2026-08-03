<?php

namespace App\Support;

use App\Models\Subscription;

/**
 * The schema has no explicit frequency column on subscriptions or
 * subscription_packages -- only a monthly_price. Interpreted as: one
 * collection per month. Rather than batch-generating a year of future
 * collections up front for an open-ended subscription, this schedules one
 * collection at a time -- the next one is created only once the current one
 * is resolved (collected or skipped), via scheduleNext().
 */
class CollectionScheduler
{
    public static function scheduleFirst(Subscription $subscription): void
    {
        $subscription->collections()->create([
            'scheduled_date' => $subscription->start_date,
            'status' => 'scheduled',
        ]);
    }

    public static function scheduleNext(Subscription $subscription, \DateTimeInterface $afterDate): void
    {
        if ($subscription->status !== 'active') {
            return;
        }

        $next = \Carbon\Carbon::instance($afterDate)->addMonthNoOverflow();

        if ($subscription->end_date && $next->greaterThan($subscription->end_date)) {
            return;
        }

        $subscription->collections()->create([
            'scheduled_date' => $next,
            'status' => 'scheduled',
        ]);
    }
}
