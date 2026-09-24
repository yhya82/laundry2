<?php

namespace App\Http\Controllers;

use App\Events\SubscriptionStatusChanged;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Support\CollectionScheduler;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(): View
    {
        $subscriptions = Subscription::with(['customer', 'subscriptionPackage'])->latest()->paginate(15);

        return view('subscriptions.index', compact('subscriptions'));
    }

    public function create(Request $request): View
    {
        $eligibleCustomers = Customer::where('customer_type', 'subscription')->orderBy('full_name')->get();
        $packages = SubscriptionPackage::where('is_active', true)->orderBy('name')->get();
        $customerId = $request->integer('customer') ?: null;

        return view('subscriptions.create', compact('eligibleCustomers', 'packages', 'customerId'));
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        if (Setting::get('subscription.allow_new_signups', 'true') !== 'true') {
            return back()->withErrors(['subscription' => 'New subscription sign-ups are currently disabled in Settings.']);
        }

        try {
            $subscription = DB::transaction(function () use ($request) {
                $subscription = Subscription::create($request->validated());

                CollectionScheduler::scheduleFirstCycle($subscription);

                return $subscription;
            });
        } catch (QueryException $e) {
            return back()->withInput()->withErrors(['customer_id' => 'Cancel the existing subscription before creating a new one.']);
        }

        // Set by the Terminal's New Subscription modal so submitting it lands
        // staff back in the Terminal for this same customer, already on the
        // newly scheduled first collection -- back() can't be trusted for
        // this because a customer picked via the Terminal's own search never
        // put ?customer= in the URL, so there's no referer to fall back to.
        if ($redirectCustomerId = $request->integer('redirect_customer_id')) {
            return redirect()->route('orders.create', ['customer' => $redirectCustomerId])->with('status', 'Subscription created.');
        }

        if ($request->boolean('return_to_profile')) {
            return redirect()->route('customers.show', $subscription->customer_id)->with('status', 'Subscription created.');
        }

        return redirect()->route('subscriptions.show', $subscription)->with('status', 'Subscription created.');
    }

    /**
     * Only allowed while the current cycle has no collected visit yet.
     * Covers package, end date, collections_per_month, collection_type and
     * max_clothes_per_cycle -- this replaces the old standalone
     * updateCollectionType() action entirely, folding collection-type
     * changes into this same single edit instead of two separate places to
     * change a subscription (same guard, same delete+regenerate approach
     * that action used for collections).
     *
     * start_date is deliberately NOT editable here -- subscriptions.start_date
     * is the historical anchor of the subscription's first cycle only; once
     * it's renewed even once, the current cycle's own starts_on has already
     * moved past that date, so there's no single "start date" left that's
     * safe to edit through this form without silently drifting the two out
     * of sync (a change to the current cycle's starts_on if it needed to be
     * moved would belong on the cycle itself, not the subscription).
     *
     * Since nothing's been collected yet, the current cycle is still
     * "fresh" -- rather than patching individual snapshot fields, a change
     * to collections_per_month or collection_type regenerates the cycle's
     * collections from scratch, anchored on the cycle's own existing
     * starts_on (the cycle row itself is only ever UPDATEd, never
     * deleted+recreated: the app's DB user has no DELETE privilege on
     * subscription_cycles, only on collections). A change to just the
     * package or max_clothes_per_cycle alone refreshes the cycle's
     * snapshots in place, since there's no schedule to regenerate for those.
     */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validated();
        $currentCycle = $subscription->cycles()->latest('starts_on')->first();

        if ($currentCycle && $currentCycle->collections()->where('status', 'collected')->exists()) {
            return back()->withErrors(['subscription' => 'This subscription already has a collected visit — it can no longer be edited.']);
        }

        $needsReschedule = $validated['collections_per_month'] !== $subscription->collections_per_month
            || $validated['collection_type'] !== $subscription->collection_type;

        DB::transaction(function () use ($subscription, $currentCycle, $validated, $needsReschedule) {
            $subscription->update($validated);

            if (! $currentCycle) {
                CollectionScheduler::scheduleCycle($subscription, Carbon::parse($subscription->start_date));

                return;
            }

            if ($needsReschedule) {
                $startsOn = Carbon::parse($currentCycle->starts_on);

                $currentCycle->collections()->delete();

                $currentCycle->update([
                    'ends_on' => null,
                    'monthly_price_snapshot' => $subscription->subscriptionPackage->monthly_price,
                    'max_clothes_snapshot' => $validated['max_clothes_per_cycle'],
                ]);

                CollectionScheduler::generateCollections($subscription, $currentCycle, $subscription->collection_type, $startsOn, max(1, $validated['collections_per_month']));

                return;
            }

            $currentCycle->update([
                'monthly_price_snapshot' => $subscription->subscriptionPackage->monthly_price,
                'max_clothes_snapshot' => $validated['max_clothes_per_cycle'],
            ]);
        });

        return back()->with('status', 'Subscription updated.');
    }

    public function show(Subscription $subscription): View
    {
        $subscription->load([
            'customer',
            'subscriptionPackage',
            'collections' => fn ($q) => $q->orderByDesc('scheduled_date'),
            'cycles' => fn ($q) => $q->orderByDesc('starts_on'),
        ]);

        $currentCycle = $subscription->cycles->first();
        $needsRenewal = $subscription->status === 'active' && $currentCycle && $currentCycle->isExhausted();

        $cycleCollections = $currentCycle
            ? $subscription->collections->where('subscription_cycle_id', $currentCycle->id)
            : collect();
        $cycleCollectionsCompleted = $cycleCollections->where('status', 'collected')->count();
        $cycleCollectionsTotal = $cycleCollections->count();
        $packages = SubscriptionPackage::where('is_active', true)->orderBy('name')->get();

        return view('subscriptions.show', compact(
            'subscription',
            'needsRenewal',
            'currentCycle',
            'cycleCollectionsCompleted',
            'cycleCollectionsTotal',
            'packages',
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
     * Re-schedules the next collection if none is currently pending -- e.g.
     * the subscription was paused right after its last collection completed,
     * which left scheduleNext()'s status guard blocking a follow-up.
     */
    public function resume(Subscription $subscription): RedirectResponse
    {
        if ($subscription->status !== 'paused') {
            return back()->withErrors(['subscription' => 'Only a paused subscription can be resumed.']);
        }

        try {
            $subscription->update(['status' => 'active']);
        } catch (QueryException $e) {
            return back()->withErrors(['subscription' => 'This customer has another subscription that\'s active or paused — cancel it before resuming this one.']);
        }

        if (! $subscription->collections()->where('status', 'scheduled')->exists()) {
            CollectionScheduler::scheduleNextCycle($subscription, now());
        }

        SubscriptionStatusChanged::dispatch($subscription);

        return back()->with('status', 'Subscription resumed.');
    }

    /**
     * Terminal -- cancelling also skips any collection still pending since
     * no further service is expected. Mirrors Order::cancel()'s one-way
     * transition; a cancelled subscription cannot be resumed.
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
     * Starts the next cycle -- the only way that happens now, for scheduled
     * and non-scheduled subscriptions alike. Nothing auto-continues anymore
     * once a cycle's collections are all resolved. The Renew modal lets
     * staff change package/collection type/counts and pick the new cycle's
     * start date, rather than just repeating the previous cycle unchanged.
     */
    public function renew(Request $request, Subscription $subscription): RedirectResponse
    {
        if ($subscription->status !== 'active') {
            return back()->withErrors(['subscription' => 'Only an active subscription can be renewed.']);
        }

        $currentCycle = $subscription->cycles()->latest('starts_on')->first();

        if ($currentCycle && ! $currentCycle->isExhausted()) {
            return back()->withErrors(['subscription' => 'This subscription still has open collections in its current cycle.']);
        }

        $validated = $request->validate([
            'subscription_package_id' => ['required', 'exists:subscription_packages,id'],
            'collection_type' => ['required', 'in:scheduled,non_scheduled'],
            'start_date' => [
                'required',
                'date',
                Rule::unique('subscription_cycles', 'starts_on')->where('subscription_id', $subscription->id),
            ],
            'collections_per_month' => ['required', 'integer', 'min:1', 'max:28'],
            'max_clothes_per_cycle' => ['required', 'integer', 'min:1'],
        ], [
            'start_date.unique' => 'This subscription already has a cycle starting on that date — pick a different date.',
        ]);

        DB::transaction(function () use ($subscription, $validated) {
            $subscription->update([
                'subscription_package_id' => $validated['subscription_package_id'],
                'collection_type' => $validated['collection_type'],
                'collections_per_month' => $validated['collections_per_month'],
                'max_clothes_per_cycle' => $validated['max_clothes_per_cycle'],
            ]);
            $subscription->refresh();

            CollectionScheduler::renew($subscription, Carbon::parse($validated['start_date']));
        });

        // Same reasoning as store()'s redirect_customer_id -- see that method.
        if ($redirectCustomerId = $request->integer('redirect_customer_id')) {
            return redirect()->route('orders.create', ['customer' => $redirectCustomerId])->with('status', 'Subscription renewed for a new cycle.');
        }

        return back()->with('status', 'Subscription renewed for a new cycle.');
    }

}
