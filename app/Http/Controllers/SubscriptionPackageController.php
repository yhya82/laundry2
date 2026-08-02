<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionPackageRequest;
use App\Models\SubscriptionPackage;
use Illuminate\Http\RedirectResponse;

class SubscriptionPackageController extends Controller
{
    public function store(StoreSubscriptionPackageRequest $request): RedirectResponse
    {
        SubscriptionPackage::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('status', 'Subscription package created.');
    }

    public function update(StoreSubscriptionPackageRequest $request, SubscriptionPackage $subscriptionPackage): RedirectResponse
    {
        $subscriptionPackage->update($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('status', 'Subscription package updated.');
    }

    public function destroy(SubscriptionPackage $subscriptionPackage): RedirectResponse
    {
        $subscriptionPackage->delete();

        return back()->with('status', 'Subscription package removed.');
    }
}
