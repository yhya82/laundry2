<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Support\CollectionScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(): View
    {
        $subscriptions = Subscription::with(['customer', 'subscriptionPackage'])->latest()->paginate(15);

        return view('subscriptions.index', compact('subscriptions'));
    }

    public function create(): View
    {
        $eligibleCustomers = Customer::where('customer_type', 'subscription')->orderBy('full_name')->get();
        $packages = SubscriptionPackage::where('is_active', true)->orderBy('name')->get();

        return view('subscriptions.create', compact('eligibleCustomers', 'packages'));
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        $subscription = Subscription::create($request->validated());

        CollectionScheduler::scheduleFirst($subscription);

        return redirect()->route('subscriptions.show', $subscription)->with('status', 'Subscription created.');
    }

    public function show(Subscription $subscription): View
    {
        $subscription->load(['customer', 'subscriptionPackage', 'collections' => fn ($q) => $q->orderByDesc('scheduled_date')]);

        return view('subscriptions.show', compact('subscription'));
    }
}
