<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPackage;
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

        $subscriptions = $customer->subscriptions()->with('subscriptionPackage')->latest('start_date')->get();

        $orders = $customer->orders()->with('payments')->latest()->get();
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

        $stats['balanceDue'] = $orders->sum(fn ($order) => $order->balanceDue())
            + $unpaidCycles->sum(fn ($cycle) => $cycle->balanceDue());

        // Every order still owing something, oldest first -- each gets its
        // own quick-pay action on the profile rather than steering staff
        // toward settling only the single oldest one.
        $unpaidOrders = $orders->filter(fn ($order) => $order->balanceDue() > 0)->sortBy('created_at')->values();

        $lastPayment = $customer->paymentsQuery()->latest()->first();
        $payments = $customer->paymentsQuery()->with(['order', 'subscription'])->latest()->get();

        $nextCollection = Collection::whereIn('subscription_id', $subscriptionIds)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_date')
            ->first();

        // The Collection Schedule card shows a subscription's *current cycle*
        // in full, regardless of status -- not just a capped handful of
        // still-scheduled ones -- so a 4-collection/month plan shows all 4,
        // and a just-collected one stays visible with its status pill
        // instead of dropping out of the list. Any subscription somehow
        // still without a cycle (legacy, predating this feature) falls back
        // to the old "just show the next scheduled one" behavior.
        $latestCycleIds = SubscriptionCycle::whereIn('subscription_id', $subscriptionIds)
            ->orderByDesc('starts_on')
            ->get()
            ->unique('subscription_id')
            ->pluck('id');

        $subscriptionsWithoutCycles = $subscriptionIds->diff(
            SubscriptionCycle::whereIn('subscription_id', $subscriptionIds)->pluck('subscription_id')
        );

        $upcomingCollections = Collection::with('subscription.subscriptionPackage')
            ->where(function ($query) use ($latestCycleIds, $subscriptionsWithoutCycles) {
                $query->whereIn('subscription_cycle_id', $latestCycleIds)
                    ->orWhere(function ($q) use ($subscriptionsWithoutCycles) {
                        $q->whereIn('subscription_id', $subscriptionsWithoutCycles)->where('status', 'scheduled');
                    });
            })
            ->orderBy('scheduled_date')
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
        ));
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

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
