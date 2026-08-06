<?php

namespace App\Http\Controllers;

use App\Models\WashingMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WashingMachineController extends Controller
{
    public function index(): View
    {
        $washingMachines = WashingMachine::orderBy('name')->get();

        return view('catalog.machines.index', compact('washingMachines'));
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
