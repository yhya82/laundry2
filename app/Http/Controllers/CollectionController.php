<?php

namespace App\Http\Controllers;

use App\Events\CollectionStatusChanged;
use App\Models\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $collections = Collection::with(['subscription.customer', 'subscription.subscriptionPackage'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->orderByRaw('scheduled_date IS NULL, scheduled_date')
            ->paginate(20)
            ->withQueryString();

        return view('collections.index', compact('collections'));
    }

    /**
     * Drops this collection's slot. Both fields are optional so the same
     * endpoint serves two different flows: "Skip" always sends both (its
     * form marks them required client-side) -- a reason plus another
     * still-scheduled collection on the subscription to fold this slot's
     * allowance into (see the Terminal's allowance computed). Plain
     * "Cancel" sends neither -- a one-click drop with nothing to absorb it.
     * The cycle's shared price is unaffected either way, since it's flat
     * per cycle regardless of how many physical visits actually happen.
     */
    public function cancel(Request $request, Collection $collection): RedirectResponse
    {
        if ($collection->status !== 'scheduled') {
            return back()->withErrors(['collection' => 'Only a scheduled collection can be cancelled.']);
        }

        $collection->loadMissing('subscription');

        if ($collection->subscription->status !== 'active') {
            return back()->withErrors(['collection' => 'This subscription is paused — resume it before skipping or cancelling a collection.']);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'combined_into_collection_id' => [
                'nullable',
                Rule::exists('collections', 'id')->where(function ($query) use ($collection) {
                    $query->where('subscription_id', $collection->subscription_id)
                        ->where('status', 'scheduled')
                        ->where('id', '!=', $collection->id);

                    if ($collection->subscription_cycle_id) {
                        $query->where('subscription_cycle_id', $collection->subscription_cycle_id);
                    }
                }),
            ],
        ], [
            'combined_into_collection_id.exists' => 'That collection is not a valid, still-scheduled pickup on this subscription.',
        ]);

        $collection->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['reason'] ?? null,
            'combined_into_collection_id' => $validated['combined_into_collection_id'] ?? null,
        ]);

        $collection->subscriptionCycle?->closeIfExhausted();

        CollectionStatusChanged::dispatch($collection);

        return back()->with('status', $validated['combined_into_collection_id'] ?? null
            ? 'Collection cancelled and combined into another pickup.'
            : 'Collection cancelled.');
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

        $collection->loadMissing('subscription');

        if ($collection->subscription->status !== 'active') {
            return redirect()->route('collections.index')->withErrors(['collection' => 'This subscription is paused — resume it before collecting.']);
        }

        $collection->load('subscription.customer', 'subscription.subscriptionPackage');

        return view('orders.collect', compact('collection'));
    }
}
