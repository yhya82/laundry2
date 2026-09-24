<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPackage;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $sort = in_array($request->get('sort'), ['full_name', 'created_at'], true) ? $request->get('sort') : 'full_name';
        $direction = $request->get('direction') === 'desc' ? 'desc' : 'asc';

        $customers = Customer::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->get('q').'%';
                $query->where(fn ($q) => $q->where('full_name', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers', 'sort', 'direction'));
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        if ($request->boolean('start_order')) {
            // A subscription customer has no Subscription record yet at this
            // point -- send them to set that up first, never straight into a
            // walk-in cart, matching how subscription orders always originate
            // from an actual collection, never ad hoc from the Terminal.
            return $customer->customer_type === 'subscription'
                ? redirect()->route('subscriptions.create', ['customer' => $customer->id])
                : redirect()->route('orders.create', ['customer' => $customer->id]);
        }

        return redirect()->route('customers.show', $customer)->with('status', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        $subscriptionIds = $customer->subscriptions()->pluck('id');

        $stats = [
            'totalOrders' => $customer->orders()->count(),
            'lifetimeSpend' => $customer->paymentsQuery()->where('status', '!=', 'refunded')->sum('amount'),
            'activeSubscriptions' => $customer->subscriptions()->where('status', 'active')->count(),
        ];

        $subscriptions = $customer->subscriptions()
            ->with(['subscriptionPackage', 'cycles' => fn ($q) => $q->latest('starts_on')->limit(1), 'collections'])
            ->latest('start_date')
            ->get();

        $orders = $customer->orders()->with('payments', 'collection.subscriptionCycle')->latest()->get();
        $recentOrders = $orders->take(5);

        // A subscription's flat cycle price is never on any order -- it's
        // its own payable thing (see SubscriptionCycle::balanceDue()) -- so
        // it needs its own pass here, across every cycle the customer has
        // ever had (not just the current one), since an older cycle can
        // still be sitting unpaid after the next one has already started.
        $unpaidCycles = SubscriptionCycle::with('subscription.subscriptionPackage')
            ->whereIn('subscription_id', $subscriptionIds)
            ->get()
            ->filter(fn ($cycle) => $cycle->balanceDue() > 0)
            ->sortBy('starts_on')
            ->values();

        // A cancelled order's own balanceDue() can be genuinely nonzero now
        // (see the model) -- it's excluded from this total specifically,
        // since that service was never rendered and isn't real, actionable
        // debt the way an ordinary unpaid order or unpaid cycle is.
        $stats['balanceDue'] = $orders->filter(fn ($order) => $order->status !== 'cancelled')->sum(fn ($order) => $order->balanceDue())
            + $unpaidCycles->sum(fn ($cycle) => $cycle->balanceDue());

        // Every order still owing something, oldest first -- each gets its
        // own quick-pay action on the profile rather than steering staff
        // toward settling only the single oldest one. Cancelled orders are
        // excluded here specifically -- they can have a true, nonzero
        // balanceDue() now (see the model), but that service was never
        // rendered, so no quick-pay action is offered for it (matches
        // PaymentController::record()'s own guard); the top-of-page
        // Balance Due stat still adds it in, so the amount itself isn't hidden.
        $unpaidOrders = $orders->filter(fn ($order) => $order->status !== 'cancelled' && $order->balanceDue() > 0)->sortBy('created_at')->values();

        $lastPayment = $customer->paymentsQuery()->latest()->first();
        $payments = $customer->paymentsQuery()->with(['order', 'subscription'])->latest()->get();

        // Nulls (non-scheduled, "anytime" slots) sort last -- a dated pickup
        // is more informative to call "next" than an open-ended one.
        $nextCollection = Collection::whereIn('subscription_id', $subscriptionIds)
            ->where('status', 'scheduled')
            ->orderByRaw('scheduled_date IS NULL, scheduled_date')
            ->first();

        // The Collection Schedule card shows a subscription's *current cycle*
        // in full, regardless of status -- not just a capped handful of
        // still-scheduled ones -- so a 4-collection/month plan shows all 4,
        // and a just-collected one stays visible with its status pill
        // instead of dropping out of the list. Any subscription somehow
        // still without a cycle (legacy, predating this feature) falls back
        // to the old "just show the next scheduled one" behavior.
        //
        // Scoped to non-cancelled subscriptions only (not $subscriptionIds,
        // which is every subscription the customer has ever had) -- a
        // customer can have more than one subscription in their history now
        // (cancel one, start another), and a cancelled subscription's own
        // last cycle has nothing left to collect, so it shouldn't keep
        // showing "regardless of status" here the way a live one does.
        $liveSubscriptionIds = $customer->subscriptions()->where('status', '!=', 'cancelled')->pluck('id');

        $latestCycleIds = SubscriptionCycle::whereIn('subscription_id', $liveSubscriptionIds)
            ->orderByDesc('starts_on')
            ->get()
            ->unique('subscription_id')
            ->pluck('id');

        $subscriptionsWithoutCycles = $liveSubscriptionIds->diff(
            SubscriptionCycle::whereIn('subscription_id', $liveSubscriptionIds)->pluck('subscription_id')
        );

        $upcomingCollections = Collection::with('subscription.subscriptionPackage')
            ->where(function ($query) use ($latestCycleIds, $subscriptionsWithoutCycles) {
                $query->whereIn('subscription_cycle_id', $latestCycleIds)
                    ->orWhere(function ($q) use ($subscriptionsWithoutCycles) {
                        $q->whereIn('subscription_id', $subscriptionsWithoutCycles)->where('status', 'scheduled');
                    });
            })
            ->orderByRaw('scheduled_date IS NULL, scheduled_date')
            ->get();

        // One row per subscription's *current* cycle (paid or not) for the
        // Payment Summary teaser -- "this month" status at a glance, not the
        // full history (that lives on the subscription's own page).
        $currentCycles = SubscriptionCycle::with('subscription.subscriptionPackage')
            ->whereIn('id', $latestCycleIds)
            ->orderByDesc('starts_on')
            ->get();

        $damageRecords = $customer->damageRecords()->with(['damageType', 'order'])->latest()->get();

        $creditTransactions = $customer->creditTransactions()->latest()->get();

        $subscriptionPackages = SubscriptionPackage::where('is_active', true)->orderBy('name')->get();

        // What "Create Order" should offer: a customer with exactly one
        // active subscription gets asked Use Subscription vs Walk-in (open
        // cycle) or Renew vs Walk-in (exhausted cycle); zero or multiple
        // active subscriptions has nothing single/obvious to pre-offer, so
        // it falls back to the plain link straight into the Terminal.
        $activeSubscriptions = $subscriptions->where('status', 'active');
        $subscriptionOrderState = match (true) {
            $activeSubscriptions->count() > 1 => 'multiple',
            $activeSubscriptions->count() === 1 && $activeSubscriptions->first()->cycles->isNotEmpty() && $activeSubscriptions->first()->cycles->first()->isExhausted() => 'exhausted',
            $activeSubscriptions->count() === 1 => 'open',
            default => 'none',
        };

        return view('customers.show', compact(
            'customer',
            'stats',
            'subscriptions',
            'recentOrders',
            'orders',
            'unpaidOrders',
            'unpaidCycles',
            'lastPayment',
            'payments',
            'nextCollection',
            'upcomingCollections',
            'currentCycles',
            'damageRecords',
            'creditTransactions',
            'subscriptionPackages',
            'subscriptionOrderState',
        ));
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $customer->update($request->validated());
        } catch (QueryException $e) {
            return back()->withInput()->withErrors(['customer_type' => 'Cancel the existing subscription before changing the customer type.']);
        }

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    /**
     * Soft delete -- orders, subscriptions, and payment history all still
     * reference this customer, so the row stays, just hidden from listings.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('status', 'Customer deleted.');
    }
}
