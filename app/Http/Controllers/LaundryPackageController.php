<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLaundryPackageRequest;
use App\Models\LaundryPackage;
use App\Models\SubscriptionPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LaundryPackageController extends Controller
{
    public function index(): View
    {
        $laundryPackages = LaundryPackage::orderBy('name')->get();
        $subscriptionPackages = SubscriptionPackage::orderBy('name')->get();

        return view('catalog.packages.index', compact('laundryPackages', 'subscriptionPackages'));
    }

    public function store(StoreLaundryPackageRequest $request): RedirectResponse
    {
        LaundryPackage::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('status', 'Laundry package created.');
    }

    public function update(StoreLaundryPackageRequest $request, LaundryPackage $laundryPackage): RedirectResponse
    {
        $laundryPackage->update($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('status', 'Laundry package updated.');
    }

    public function destroy(LaundryPackage $laundryPackage): RedirectResponse
    {
        $laundryPackage->delete();

        return back()->with('status', 'Laundry package removed.');
    }
}
