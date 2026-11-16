<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\Subscription;
use App\Support\CollectionScheduler;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The one place a subscription gets created -- used by
 * SubscriptionController::store() and the Terminal's subscription panel, so
 * the rules can't drift apart between them:
 *
 *  - new sign-ups can be switched off in Settings;
 *  - the customer must be typed 'subscription';
 *  - the customer must owe nothing;
 *  - one active-or-paused subscription per customer (a DB trigger);
 *  - it always starts today -- never a staff-picked date;
 *  - the first cycle and its collections are generated straight away (and,
 *    for a scheduled plan, end_date = its last collection date).
 */
class SubscriptionCreationService
{
    public function __construct(protected CustomerBalanceService $balanceService)
    {
    }

    /**
     * Runs in its own transaction, or as a savepoint inside the caller's
     * (the Terminal creates the customer in the same transaction).
     *
     * @param  array{subscription_package_id: int|string, collection_type: string, collections_per_month: int|string, max_clothes_per_cycle: int|string}  $plan
     *
     * @throws SubscriptionCreationException
     */
    public function create(Customer $customer, array $plan): Subscription
    {
        if (Setting::get('subscription.allow_new_signups', 'true') !== 'true') {
            throw new SubscriptionCreationException(
                'New subscription sign-ups are currently disabled in Settings.',
                SubscriptionCreationException::SIGNUPS_DISABLED
            );
        }

        try {
            return DB::transaction(function () use ($customer, $plan) {
                // Locked so the balance check and the insert can't race a
                // payment or order on the same customer.
                $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

                if ($locked->customer_type !== 'subscription') {
                    throw new SubscriptionCreationException(
                        'This customer is not set to Customer type: Subscription. Edit their profile first.',
                        SubscriptionCreationException::NOT_SUBSCRIPTION_CUSTOMER
                    );
                }

                if ($this->balanceService->hasOutstandingBalance($locked)) {
                    throw new SubscriptionCreationException(
                        'This customer has an outstanding balance. Please settle it before creating a new subscription.',
                        SubscriptionCreationException::OUTSTANDING_BALANCE
                    );
                }

                $subscription = Subscription::create([
                    'customer_id' => $locked->id,
                    'subscription_package_id' => (int) $plan['subscription_package_id'],
                    'status' => 'active',
                    'start_date' => now()->toDateString(),
                    'collection_type' => $plan['collection_type'],
                    'collections_per_month' => (int) $plan['collections_per_month'],
                    'max_clothes_per_cycle' => (int) $plan['max_clothes_per_cycle'],
                ]);

                CollectionScheduler::scheduleFirstCycle($subscription);

                return $subscription->fresh();
            });
        } catch (QueryException $e) {
            // trg_subscriptions_one_active_guard_insert -- one active or
            // paused subscription per customer. Anything else is unexpected.
            if (($e->errorInfo[0] ?? null) === '45000' && str_contains($e->getMessage(), 'Cancel the existing subscription')) {
                throw new SubscriptionCreationException(
                    'Cancel the existing subscription before creating a new one.',
                    SubscriptionCreationException::ALREADY_SUBSCRIBED
                );
            }

            throw $e;
        }
    }
}
