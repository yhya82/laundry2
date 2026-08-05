<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Support\CollectionScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('subscriptions.show', compact('subscription'));
    }

    public function pause(Subscription $subscription): RedirectResponse
    {
        if ($subscription->status !== 'active') {
            return back()->withErrors(['subscription' => 'Only an active subscription can be paused.']);
        }

        $subscription->update(['status' => 'paused']);

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

        return back()->with('status', 'Subscription cancelled.');
    }
}
