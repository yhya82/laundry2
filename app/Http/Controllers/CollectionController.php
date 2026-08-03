<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Support\CollectionScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $collections = Collection::with(['subscription.customer', 'subscription.subscriptionPackage'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->orderBy('scheduled_date')
            ->paginate(20)
            ->withQueryString();

        return view('collections.index', compact('collections'));
    }

    /**
     * Per Master Document §2.2: a skipped collection never creates an order.
     */
    public function skip(Collection $collection): RedirectResponse
    {
        if ($collection->status !== 'scheduled') {
            return back()->withErrors(['collection' => 'Only a scheduled collection can be skipped.']);
        }

        $collection->update(['status' => 'skipped']);

        CollectionScheduler::scheduleNext($collection->subscription, $collection->scheduled_date);

        return back()->with('status', 'Collection marked as skipped -- no order created.');
    }

    /**
     * Entry point into the Terminal's subscription mode -- collecting
     * doesn't happen here, it happens when that order is submitted.
     */
    public function collect(Collection $collection): View|RedirectResponse
    {
        if ($collection->status !== 'scheduled') {
            return redirect()->route('collections.index')->withErrors(['collection' => 'This collection has already been resolved.']);
        }

        $collection->load('subscription.customer', 'subscription.subscriptionPackage');

        return view('orders.collect', compact('collection'));
    }
}
