<?php

namespace App\Http\Controllers;

use App\Models\WashingMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WashingMachineController extends Controller
{
    /**
     * Eager-loads each machine's recent orders (with their customers and
     * status history) in one pass, so the card grid's detail panels don't
     * each re-query -- the current order (if any), wash history, and who
     * moved an order into "washing" are all derived from this same loaded
     * collection in the view, rather than calling currentOrder()/isBusy()
     * or the order's own status history again per machine.
     */
    public function index(): View
    {
        $washingMachines = WashingMachine::with(['orders' => fn ($q) => $q->latest()->limit(9)->with('customer', 'statusHistory.changedBy')])
            ->orderBy('name')
            ->get();

        return view('catalog.machines.index', compact('washingMachines'));
    }

    /**
     * Live busy/idle snapshot for the order page's "Select Washing Machine"
     * panel -- fetched fresh each time the panel opens rather than trusted
     * from the order page's own initial render, which can otherwise sit
     * stale for as long as that page has been open.
     */
    public function status(): JsonResponse
    {
        $washingMachines = WashingMachine::where('is_active', true)->orderBy('name')->get();

        return response()->json($washingMachines->map(fn (WashingMachine $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'busy' => $m->isBusy(),
            'currentOrderNumber' => $m->currentOrder()?->order_number,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255', 'unique:washing_machines,name']]);

        WashingMachine::create($request->only('name'));

        return back()->with('status', 'Washing machine added.');
    }

    public function update(Request $request, WashingMachine $washingMachine): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255', 'unique:washing_machines,name,'.$washingMachine->id]]);

        $washingMachine->update($request->only('name'));

        return back()->with('status', 'Washing machine updated.');
    }

    /**
     * Retiring a busy machine doesn't touch its in-progress order -- that
     * order still shows the machine on its own page, it just won't be
     * offered for new washing assignments (or count toward capacity) going
     * forward until reactivated.
     */
    public function toggleActive(WashingMachine $washingMachine): RedirectResponse
    {
        $washingMachine->update(['is_active' => ! $washingMachine->is_active]);

        return back()->with('status', $washingMachine->is_active ? 'Washing machine activated.' : 'Washing machine retired.');
    }
}
