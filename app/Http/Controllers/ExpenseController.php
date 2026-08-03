<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $expenses = Expense::with('category', 'recorder')
            ->when($request->filled('category'), fn ($q) => $q->where('expense_category_id', $request->get('category')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->get('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->get('to')))
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        $categories = ExpenseCategory::orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'categories'));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        Expense::create($request->validated() + ['recorded_by' => auth()->id()]);

        return back()->with('status', 'Expense recorded.');
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->update($request->validated());

        return back()->with('status', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('status', 'Expense removed.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255', 'unique:expense_categories,name']]);

        ExpenseCategory::create($request->only('name'));

        return back()->with('status', 'Category added.');
    }
}
