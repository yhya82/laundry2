<x-app-layout>
    <x-slot name="header">Expenses</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 text-sm text-critical bg-critical-soft border border-critical/30 rounded-lg px-4 py-2.5">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-4">
            <form method="GET" class="flex flex-wrap items-end gap-3">
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

            <div class="bg-surface border border-line rounded-2xl overflow-hidden">
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

            <div>{{ $expenses->links() }}</div>
        </div>

        <div class="space-y-5 h-fit">
            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">New expense</div>
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
                    </div>
                    <div>
                        <x-input-label for="description" value="Description" />
                        <x-text-input id="description" name="description" type="text" class="block w-full" required />
                    </div>
                    <div>
                        <x-input-label for="amount" value="Amount (GMD)" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="block w-full" required />
                    </div>
                    <div>
                        <x-input-label for="expense_date" value="Date" />
                        <x-text-input id="expense_date" name="expense_date" type="date" class="block w-full" value="{{ now()->format('Y-m-d') }}" required />
                    </div>
                    <x-primary-button class="w-full">Add expense</x-primary-button>
                </form>
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">New category</div>
                <form method="POST" action="{{ route('expenses.categories.store') }}" class="flex gap-2">
                    @csrf
                    <x-text-input name="name" type="text" class="flex-1" placeholder="e.g. Utilities" required />
                    <button type="submit" class="px-4 py-2 bg-accent-soft text-accent-ink rounded-lg text-sm font-semibold">Add</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
