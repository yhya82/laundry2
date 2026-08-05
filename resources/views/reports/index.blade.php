<x-app-layout>
    <x-slot name="header">Reports</x-slot>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach (['all' => 'All', 'day' => 'Day', 'month' => 'Month', 'year' => 'Year'] as $period => $label)
            <a href="{{ route('reports.index', ['period' => $period]) }}" class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors {{ $activePeriod === $period ? 'bg-accent text-white' : 'bg-surface-2 text-ink-muted hover:text-ink' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
        <div>
            <label class="block text-xs text-ink-muted mb-1">From</label>
            <input type="date" name="from" value="{{ $from }}" class="bg-surface border-line-strong rounded-lg shadow-sm text-sm">
        </div>
        <div>
            <label class="block text-xs text-ink-muted mb-1">To</label>
            <input type="date" name="to" value="{{ $to }}" class="bg-surface border-line-strong rounded-lg shadow-sm text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-surface-2 text-ink rounded-lg text-sm font-semibold">Apply range</button>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint">Revenue</div>
                <a href="{{ route('reports.export.revenue', ['from' => $from, 'to' => $to]) }}" class="text-xs text-accent-ink hover:underline">Export CSV</a>
            </div>
            <div class="text-2xl font-bold text-accent-ink tabular-nums mb-4">GMD {{ number_format($revenueTotal, 2) }}</div>
            <div class="space-y-1.5 max-h-56 overflow-y-auto">
                @forelse ($revenue as $day)
                    <div class="flex justify-between text-sm">
                        <span class="font-mono text-xs text-ink-faint">{{ $day->day }}</span>
                        <span class="text-ink-muted">{{ $day->count }} payment{{ $day->count == 1 ? '' : 's' }}</span>
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($day->total, 2) }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No payments in this range.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint">Damage</div>
                <a href="{{ route('reports.export.damage', ['from' => $from, 'to' => $to]) }}" class="text-xs text-accent-ink hover:underline">Export CSV</a>
            </div>
            <div class="space-y-2">
                @foreach (['pending_review', 'under_investigation', 'approved', 'rejected', 'resolved', 'closed'] as $status)
                    <div class="flex justify-between text-sm">
                        <x-status-pill :status="$status" />
                        <span class="font-mono tabular-nums text-ink">{{ $damageByStatus[$status] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint">Expenses</div>
                <a href="{{ route('reports.export.expenses', ['from' => $from, 'to' => $to]) }}" class="text-xs text-accent-ink hover:underline">Export CSV</a>
            </div>
            <div class="text-2xl font-bold text-ink tabular-nums mb-4">GMD {{ number_format($expensesTotal, 2) }}</div>
            <div class="space-y-1.5 max-h-56 overflow-y-auto">
                @forelse ($expensesByCategory as $categoryName => $total)
                    <div class="flex justify-between text-sm">
                        <span class="text-ink-muted">{{ $categoryName }}</span>
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($total, 2) }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No expenses in this range.</p>
                @endforelse
            </div>
        </div>

    </div>
</x-app-layout>
