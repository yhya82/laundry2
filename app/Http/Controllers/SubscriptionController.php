<?php

namespace App\Http\Controllers;

use App\Events\SubscriptionStatusChanged;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Support\CollectionScheduler;
use Carbon\Carbon;
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

        $subscription = Subscription::create($request->validated());

        CollectionScheduler::scheduleFirstCycle($subscription);

        if ($request->boolean('return_to_profile')) {
            return redirect()->route('customers.show', $subscription->customer_id)->with('status', 'Subscription created.');
        }

        return redirect()->route('subscriptions.show', $subscription)->with('status', 'Subscription created.');
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

        $subscription->update(['status' => 'active']);

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

        $subscription->collections()->where('status', 'scheduled')->update(['status' => 'skipped']);

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

        $subscription->update([
            'subscription_package_id' => $validated['subscription_package_id'],
            'collection_type' => $validated['collection_type'],
            'collections_per_month' => $validated['collections_per_month'],
            'max_clothes_per_cycle' => $validated['max_clothes_per_cycle'],
        ]);
        $subscription->refresh();

        CollectionScheduler::renew($subscription, Carbon::parse($validated['start_date']));

        return back()->with('status', 'Subscription renewed for a new cycle.');
    }

    /**
     * Lets staff flip Scheduled <-> Non-scheduled on the current cycle
     * directly, rather than waiting for a full Renew. Only ever touches the
     * cycle's still-open ('scheduled') collections -- anything already
     * cancelled stays untouched as history, and the moment even one has
     * actually been collected, this locks entirely (see the guard below).
     */
    public function updateCollectionType(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'collection_type' => ['required', 'in:scheduled,non_scheduled'],
        ]);

        $currentCycle = $subscription->cycles()->latest('starts_on')->first();

        if (! $currentCycle) {
            return back()->withErrors(['collection_type' => 'This subscription has no current cycle.']);
        }

        if ($currentCycle->collections()->where('status', 'collected')->exists()) {
            return back()->withErrors(['collection_type' => 'This cycle already has a collected visit — collection type can no longer be changed for it.']);
        }

        if ($validated['collection_type'] === $subscription->collection_type) {
            return back()->with('status', 'Collection type unchanged.');
        }

        DB::transaction(function () use ($subscription, $currentCycle, $validated) {
            $subscription->update(['collection_type' => $validated['collection_type']]);

            $openCount = $currentCycle->collections()->where('status', 'scheduled')->count();

            if ($openCount === 0) {
                return;
            }

            $currentCycle->collections()->where('status', 'scheduled')->delete();

            CollectionScheduler::generateCollections($subscription, $currentCycle, $validated['collection_type'], now(), $openCount);
        });

        return back()->with('status', 'Collection type updated.');
    }
}
