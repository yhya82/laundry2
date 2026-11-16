<?php

namespace App\Http\Controllers;

use App\Events\SubscriptionStatusChanged;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPackage;
use App\Services\CustomerBalanceService;
use App\Support\CollectionScheduler;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $subscriptions = Subscription::with(['customer', 'subscriptionPackage'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->get('q').'%';
                $q->whereHas('customer', fn ($c) => $c->where('full_name', 'like', $term));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('subscriptions.index', compact('subscriptions'));
    }

    public function create(Request $request): View
    {
        $eligibleCustomers = Customer::where('customer_type', 'subscription')->orderBy('full_name')->get();
        $packages = SubscriptionPackage::where('is_active', true)->orderBy('name')->get();
        $customerId = $request->integer('customer') ?: null;

        return view('subscriptions.create', compact('eligibleCustomers', 'packages', 'customerId'));
    }

    public function store(StoreSubscriptionRequest $request, CustomerBalanceService $balanceService): RedirectResponse
    {
        if (Setting::get('subscription.allow_new_signups', 'true') !== 'true') {
            return back()->withErrors([
                'subscription' => 'New subscription sign-ups are currently disabled in Settings.',
            ]);
        }

        try {
            $subscription = DB::transaction(function () use ($request, $balanceService) {
                // Lock the customer row before checking their balance.
                $customer = Customer::query()
                    ->whereKey($request->validated('customer_id'))
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($balanceService->hasOutstandingBalance($customer)) {
                    return null;
                }

                $subscription = Subscription::create($request->validated());

                // Also sets end_date for a scheduled subscription (its last
                // collection date) -- see CollectionScheduler.
                CollectionScheduler::scheduleFirstCycle($subscription);

                return $subscription;
            });
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->withErrors([
                    'customer_id' => 'Cancel the existing subscription before creating a new one.',
                ]);
        }

        // A null result means the customer has an outstanding balance.
        if (! $subscription) {
            return redirect()
                ->route('customers.show', $request->validated('customer_id'))
                ->withErrors([
                    'subscription' => 'This customer has an outstanding balance. Please settle it before creating a new subscription.',
                ]);
        }

        // Return to the Terminal when the request originated there.
        if ($redirectCustomerId = $request->integer('redirect_customer_id')) {
            return redirect()
                ->route('orders.create', ['customer' => $redirectCustomerId])
                ->with('status', 'Subscription created.');
        }

        if ($request->boolean('return_to_profile')) {
            return redirect()
                ->route('customers.show', $subscription->customer_id)
                ->with('status', 'Subscription created.');
        }

        return redirect()
            ->route('subscriptions.show', $subscription)
            ->with('status', 'Subscription created.');
    }

    /**
     * Only for an active subscription that has never had a visit collected,
     * in any cycle (see Subscription::editBlockedReason()). After that, the
     * plan only changes through Renew.
     *
     * Covers package, collections_per_month, collection_type and
     * max_clothes_per_cycle. start_date and end_date are not editable:
     * end_date follows the schedule for a scheduled subscription and is
     * left untouched for a non-scheduled one.
     *
     * Only a change to collections_per_month or collection_type rebuilds the
     * current cycle's collections (anchored on its own starts_on). A package
     * or clothes-cap change alone refreshes the cycle's snapshots in place
     * and leaves its collections -- including any cancelled ones -- alone.
     * The cycle row itself is only ever UPDATEd: the app's DB user has no
     * DELETE privilege on subscription_cycles.
     *
     * A package change on a cycle that's already been paid: what was paid
     * counts toward the new price, and anything paid beyond it is refunded
     * to the customer's store credit (moveCycleOverpaymentToCredit()).
     */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validated();

        // Request input arrives as strings; cast before comparing against
        // the model's integer casts, or every save looks like a change.
        $collectionsPerMonth = (int) $validated['collections_per_month'];
        $maxClothesPerCycle = (int) $validated['max_clothes_per_cycle'];
        $collectionType = $validated['collection_type'];

        $result = DB::transaction(function () use ($subscription, $validated, $collectionsPerMonth, $maxClothesPerCycle, $collectionType) {
            // Locked so a collection recorded at the Terminal can't slip in
            // between the edit check and the rebuild below.
            $locked = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reason = $locked->editBlockedReason()) {
                return ['error' => $reason];
            }

            $currentCycle = $locked->cycles()->latest('id')->lockForUpdate()->first();

            $needsReschedule = $collectionsPerMonth !== (int) $locked->collections_per_month
                || $collectionType !== $locked->collection_type;

            $locked->update([
                'subscription_package_id' => $validated['subscription_package_id'],
                'collections_per_month' => $collectionsPerMonth,
                'collection_type' => $collectionType,
                'max_clothes_per_cycle' => $maxClothesPerCycle,
            ]);

            $locked->refresh();

            if (! $currentCycle) {
                CollectionScheduler::scheduleCycle($locked, Carbon::parse($locked->start_date));

                return ['credited' => 0.0];
            }

            $currentCycle->update([
                'monthly_price_snapshot' => $locked->subscriptionPackage->monthly_price,
                'max_clothes_snapshot' => $maxClothesPerCycle,
            ]);

            if ($needsReschedule) {
                // Safe: editBlockedReason() guarantees nothing in this
                // subscription has been collected, so no collection here has
                // an order attached.
                $currentCycle->collections()->delete();

                CollectionScheduler::generateCollections(
                    $locked,
                    $currentCycle,
                    $collectionType,
                    Carbon::parse($currentCycle->starts_on),
                    max(1, $collectionsPerMonth)
                );
            }

            return ['credited' => $this->moveCycleOverpaymentToCredit($currentCycle->fresh(), $locked->customer)];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['subscription' => $result['error']]);
        }

        $message = 'Subscription updated.';

        if ($result['credited'] > 0) {
            $message .= ' GMD '.number_format($result['credited'], 2).' paid above the new package price was moved to store credit.';
        }

        return back()->with('status', $message);
    }

    /**
     * After a package change, a cycle can hold more money than its new
     * price. The excess goes back to the customer as store credit: refunds
     * are recorded against the cycle's payments (newest first) so the
     * cycle's net paid amount drops to its new price, and one matching
     * credit is added to the store-credit ledger. The refund and credit
     * triggers keep payment status and the customer's balance in step.
     */
    protected function moveCycleOverpaymentToCredit(SubscriptionCycle $cycle, Customer $customer): float
    {
        $excess = round($cycle->amountPaid() - (float) $cycle->monthly_price_snapshot, 2);

        if ($excess <= 0) {
            return 0.0;
        }

        $remaining = $excess;

        $payments = $cycle->payments()
            ->where('status', '!=', 'refunded')
            ->latest('id')
            ->get();

        foreach ($payments as $payment) {
            if ($remaining <= 0) {
                break;
            }

            $refundAmount = round(min($remaining, $payment->remainingRefundable()), 2);

            if ($refundAmount <= 0) {
                continue;
            }

            $payment->refunds()->create([
                'amount' => $refundAmount,
                'reason' => 'Package changed: amount above the new price moved to store credit',
                'refunded_by' => auth()->id(),
            ]);

            $remaining = round($remaining - $refundAmount, 2);
        }

        $credited = round($excess - $remaining, 2);

        if ($credited > 0) {
            $customer->creditTransactions()->create([
                'type' => 'credit',
                'amount' => $credited,
                'reference_type' => 'subscription_cycle',
                'reference_id' => $cycle->id,
                'notes' => 'Package changed: overpayment on the cycle moved to store credit',
                'created_by' => auth()->id(),
            ]);
        }

        return $credited;
    }

    public function show(Subscription $subscription, CustomerBalanceService $balanceService): View
    {
        $subscription->load([
            'customer',
            'subscriptionPackage',
            'collections' => fn ($q) => $q->orderByDesc('scheduled_date'),
            'cycles' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $currentCycle = $subscription->cycles->first();
        $needsRenewal = $subscription->status === 'active' && $currentCycle && $currentCycle->isExhausted();

        $cycleCollections = $currentCycle
            ? $subscription->collections->where('subscription_cycle_id', $currentCycle->id)
            : collect();
        $cycleCollectionsCompleted = $cycleCollections->where('status', 'collected')->count();
        $cycleCollectionsTotal = $cycleCollections->count();
        $packages = SubscriptionPackage::where('is_active', true)->orderBy('name')->get();
        $canEdit = $subscription->isEditable();

        // Renew is refused server-side while the customer owes anything
        // (see renew()); checked here too so the page shows why up front
        // instead of offering a Renew that will bounce.
        $hasOutstandingBalance = $needsRenewal
            && $subscription->customer
            && $balanceService->hasOutstandingBalance($subscription->customer);

        return view('subscriptions.show', compact(
            'subscription',
            'needsRenewal',
            'currentCycle',
            'cycleCollectionsCompleted',
            'cycleCollectionsTotal',
            'packages',
            'canEdit',
            'hasOutstandingBalance',
        ));
    }

    public function pause(Subscription $subscription): RedirectResponse
    {
        if ($subscription->status !== 'active') {
            return back()->withErrors(['subscription' => 'Only an active subscription can be paused.']);
        }

        $subscription->update(['status' => 'paused']);

        SubscriptionStatusChanged::dispatch($subscription);

        return back()->with('status', 'Subscription paused.');
    }

    /**
     * Only reactivates the subscription. It never creates a cycle -- a new
     * cycle only ever comes from Renew.
     */
    public function resume(Subscription $subscription): RedirectResponse
    {
        try {
            DB::transaction(function () use ($subscription) {
                // Lock the subscription to prevent concurrent resume operations.
                $locked = Subscription::query()
                    ->whereKey($subscription->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status !== 'paused') {
                    throw ValidationException::withMessages([
                        'subscription' => 'Only a paused subscription can be resumed.',
                    ]);
                }

                $locked->update(['status' => 'active']);
            }, 3);
        } catch (QueryException $e) {
            // One active-or-paused subscription per customer is enforced by
            // trg_subscriptions_one_active_guard_update, which raises
            // SQLSTATE 45000 -- there's no unique index behind it.
            if (($e->errorInfo[0] ?? null) === '45000' && str_contains($e->getMessage(), 'Cancel the existing subscription')) {
                return back()->withErrors([
                    'subscription' => 'This customer already has another active or paused subscription. Cancel it before resuming this one.',
                ]);
            }

            throw $e;
        }

        // Notify listeners after the transaction has committed.
        SubscriptionStatusChanged::dispatch($subscription->fresh());

        return back()->with('status', 'Subscription resumed.');
    }

    /**
     * Terminal -- cancelling also marks every collection still pending as
     * subscription_cancelled, since no further service is expected. A
     * cancelled subscription cannot be resumed.
     */
    public function cancel(Subscription $subscription): RedirectResponse
    {
        if ($subscription->status === 'cancelled') {
            return back()->withErrors(['subscription' => 'This subscription is already cancelled.']);
        }

        $subscription->update(['status' => 'cancelled']);

        $subscription->collections()->where('status', 'scheduled')->update(['status' => 'subscription_cancelled']);

        $subscription->cycles()->whereNull('ends_on')->get()->each->closeIfExhausted();

        SubscriptionStatusChanged::dispatch($subscription);

        return back()->with('status', 'Subscription cancelled.');
    }

    /**
     * Starts the next cycle -- the only way that happens, for scheduled and
     * non-scheduled subscriptions alike. Allowed only once the current cycle
     * is exhausted. The new cycle always starts today, which may be the same
     * day the previous one started. The Renew modal lets staff change
     * package, collection type and counts.
     */
    public function renew(Request $request, Subscription $subscription, CustomerBalanceService $balanceService): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_package_id' => ['required', 'exists:subscription_packages,id'],
            'collection_type' => ['required', 'in:scheduled,non_scheduled'],
            'collections_per_month' => ['required', 'integer', 'min:1', 'max:4'],
            'max_clothes_per_cycle' => ['required', 'integer', 'min:1'],
        ], [
            'collections_per_month.min' => 'A subscription must have at least 1 collection per month.',
            'collections_per_month.max' => 'A subscription cannot have more than 4 collections per month.',
        ]);

        try {
            $result = DB::transaction(function () use ($subscription, $validated, $balanceService) {
                // Concurrent renewals of the same subscription serialize on
                // this lock; the second one then finds the brand-new cycle
                // not exhausted and is refused.
                $locked = Subscription::query()
                    ->whereKey($subscription->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status !== 'active') {
                    throw new DomainException('Only an active subscription can be renewed.');
                }

                $currentCycle = $locked->cycles()->latest('id')->lockForUpdate()->first();

                if ($currentCycle && ! $currentCycle->isExhausted()) {
                    throw new DomainException('This subscription still has open collections in its current cycle.');
                }

                $customer = $locked->customer;

                if (! $customer) {
                    throw new DomainException('The customer for this subscription could not be found.');
                }

                if ($balanceService->hasOutstandingBalance($customer)) {
                    return ['type' => 'balance', 'customer_id' => $customer->id];
                }

                $locked->update([
                    'subscription_package_id' => $validated['subscription_package_id'],
                    'collection_type' => $validated['collection_type'],
                    'collections_per_month' => (int) $validated['collections_per_month'],
                    'max_clothes_per_cycle' => (int) $validated['max_clothes_per_cycle'],
                ]);

                $locked->refresh();

                // A renewal always starts today -- never a staff-picked
                // date, so a cycle can't be dated ahead (or back). Any
                // start_date sent with the form is ignored. renew() also
                // re-checks status and exhaustion under the same lock, and
                // sets end_date for a scheduled subscription.
                CollectionScheduler::renew($locked, Carbon::today());

                return ['type' => 'success'];
            });
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors(['subscription' => $exception->getMessage()]);
        }

        if ($result['type'] === 'balance') {
            return redirect()
                ->route('customers.show', $result['customer_id'])
                ->withErrors([
                    'subscription' => 'This customer has an outstanding balance. Please settle it before renewing a subscription.',
                ]);
        }

        if ($redirectCustomerId = $request->integer('redirect_customer_id')) {
            return redirect()
                ->route('orders.create', ['customer' => $redirectCustomerId])
                ->with('status', 'Subscription renewed for a new cycle.');
        }

        return back()->with('status', 'Subscription renewed for a new cycle.');
    }
}
