<x-app-layout>
    <x-slot name="header">Customers</x-slot>

    <div class="flex items-center justify-between mb-5 gap-4">
        <form method="GET" class="flex-1 max-w-sm">
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search by name or phone…"
                class="w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm"
            >
        </form>

        @can('customers.manage')
            <a href="{{ route('customers.create') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-accent border border-transparent rounded-lg font-semibold text-sm text-white hover:opacity-90">
                + New Customer
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif

    <div class="bg-surface border border-line rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        @foreach (['full_name' => 'Name', 'created_at' => 'Added'] as $key => $label)
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc']) }}" class="hover:text-ink">
                                    {{ $label }}
                                    @if ($sort === $key) {{ $direction === 'asc' ? '↑' : '↓' }} @endif
                                </a>
                            </th>
                        @endforeach
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Phone</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Type</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Store Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3">
                                <a href="{{ route('customers.show', $customer) }}" class="font-medium text-ink hover:text-accent-ink">{{ $customer->full_name }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink-muted font-mono text-xs">{{ $customer->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-ink-muted font-mono">{{ $customer->phone }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $customer->customer_type === 'subscription' ? 'bg-accent-soft text-accent-ink' : 'bg-pill-bg text-pill-ink' }}">
                                    {{ $customer->customer_type === 'subscription' ? 'Subscription' : 'Walk-in' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-ink tabular-nums">GMD {{ number_format($customer->store_credit_balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-ink-faint text-sm">
                                {{ request('q') ? 'No customers match "'.request('q').'".' : 'No customers yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</x-app-layout>
