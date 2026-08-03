<x-app-layout>
    <x-slot name="header">Expenses</x-slot>

    <div class="flex items-center justify-end gap-3 mb-5">
        <x-panel-trigger panel="expense-category-create">+ New Category</x-panel-trigger>
        <x-panel-trigger panel="expense-create">+ New Expense</x-panel-trigger>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
        <div>
            <label class="block text-xs text-ink-muted mb-1">Category</label>
            <select name="category" onchange="this.form.submit()" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-ink-muted mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="bg-surface border-line-strong rounded-lg shadow-sm text-sm">
        </div>
        <div>
            <label class="block text-xs text-ink-muted mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="bg-surface border-line-strong rounded-lg shadow-sm text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-surface-2 text-ink rounded-lg text-sm font-semibold">Filter</button>
        @if (request()->hasAny(['category', 'from', 'to']))
            <a href="{{ route('expenses.index') }}" class="text-xs text-ink-muted hover:text-ink">Clear</a>
        @endif
    </form>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden hidden md:block">
        <table class="w-full text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Date</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Category</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Description</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Amount</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr class="border-t border-line hover:bg-surface-2">
                        <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $expense->expense_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-ink-muted">{{ $expense->category->name }}</td>
                        <td class="px-4 py-3 text-ink">{{ $expense->description }}</td>
                        <td class="px-4 py-3 font-mono tabular-nums text-ink">GMD {{ number_format($expense->amount, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Remove this expense?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-critical text-xs hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint text-sm">No expenses match these filters.</td></tr>
                @endforelse
            </tbody>
            @if ($expenses->isNotEmpty())
                <tfoot>
                    <tr class="border-t border-line bg-surface-2 font-semibold">
                        <td colspan="3" class="px-4 py-3 text-ink-muted text-xs uppercase tracking-wide">Page total</td>
                        <td class="px-4 py-3 font-mono tabular-nums text-ink">GMD {{ number_format($expenses->sum('amount'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($expenses as $expense)
            <div class="bg-surface border border-line rounded-2xl p-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-ink">{{ $expense->description }}</span>
                    <span class="font-mono tabular-nums text-ink">GMD {{ number_format($expense->amount, 2) }}</span>
                </div>
                <div class="flex items-center justify-between text-xs text-ink-faint">
                    <span>{{ $expense->category->name }} · {{ $expense->expense_date->format('Y-m-d') }}</span>
                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Remove this expense?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-critical hover:underline">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No expenses match these filters.</div>
        @endforelse
        @if ($expenses->isNotEmpty())
            <div class="bg-surface-2 border border-line rounded-2xl p-4 flex items-center justify-between text-sm font-semibold">
                <span class="text-ink-muted text-xs uppercase tracking-wide">Page total</span>
                <span class="font-mono tabular-nums text-ink">GMD {{ number_format($expenses->sum('amount'), 2) }}</span>
            </div>
        @endif
    </div>

    <div class="mt-4">{{ $expenses->links() }}</div>

    <x-slide-panel name="expense-create" title="New Expense" :error-fields="['expense_category_id', 'description', 'amount', 'expense_date']">
        <form method="POST" action="{{ route('expenses.store') }}" class="space-y-4">
            @csrf
            <div>
                <x-input-label for="expense_category_id" value="Category" />
                <select id="expense_category_id" name="expense_category_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                    <option value="">Select…</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('expense_category_id')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <x-text-input id="description" name="description" type="text" class="block w-full" value="{{ old('description') }}" required />
                <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="amount" value="Amount (GMD)" />
                <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="block w-full" value="{{ old('amount') }}" required />
                <x-input-error :messages="$errors->get('amount')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label for="expense_date" value="Date" />
                <x-text-input id="expense_date" name="expense_date" type="date" class="block w-full" value="{{ old('expense_date', now()->format('Y-m-d')) }}" required />
                <x-input-error :messages="$errors->get('expense_date')" class="mt-1.5" />
            </div>
            <div class="flex items-center gap-3">
                <x-primary-button>Add expense</x-primary-button>
                <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
            </div>
        </form>
    </x-slide-panel>

    <x-slide-panel name="expense-category-create" title="New Expense Category" :error-fields="['name']">
        <form method="POST" action="{{ route('expenses.categories.store') }}" class="space-y-4">
            @csrf
            <div>
                <x-input-label for="category_name" value="Name" />
                <x-text-input id="category_name" name="name" type="text" class="block w-full" placeholder="e.g. Utilities" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
            </div>
            <div class="flex items-center gap-3">
                <x-primary-button>Add category</x-primary-button>
                <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
            </div>
        </form>
    </x-slide-panel>
</x-app-layout>
