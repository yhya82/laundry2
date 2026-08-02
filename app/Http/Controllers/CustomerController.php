<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
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

        return redirect()->route('customers.show', $customer)->with('status', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'orders' => fn ($q) => $q->latest()->limit(10),
            'payments' => fn ($q) => $q->latest()->limit(10),
            'creditTransactions' => fn ($q) => $q->latest()->limit(10),
        ]);

        $damageRecords = $customer->damageRecords()->with('damageType')->latest()->limit(10)->get();

        return view('customers.show', compact('customer', 'damageRecords'));
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
}
