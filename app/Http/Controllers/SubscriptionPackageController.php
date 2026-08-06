<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionPackageRequest;
use App\Models\SubscriptionPackage;
use Illuminate\Http\RedirectResponse;

class SubscriptionPackageController extends Controller
{
    public function store(StoreSubscriptionPackageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['collections_per_month'] ??= 1;
        $data['max_clothes_per_cycle'] ??= $data['clothes_allowance'] * 4;

        SubscriptionPackage::create($data + ['is_active' => $request->boolean('is_active', true)]);

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
