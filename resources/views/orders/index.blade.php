<x-app-layout>
    <x-slot name="header">Orders</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif

    <div class="flex items-center justify-between mb-5 gap-4">
        <form method="GET" class="flex-1 max-w-sm flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search order number…" class="w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm">
            <select name="status" onchange="this.form.submit()" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                <option value="">All statuses</option>
                @foreach (['received', 'sorting', 'washing', 'drying', 'ironing', 'packaging', 'completed', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </form>

        @can('terminal.use')
            <a href="{{ route('orders.create') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-accent border border-transparent rounded-lg font-semibold text-sm text-white hover:opacity-90 whitespace-nowrap">
                + New Order
            </a>
        @endcan
    </div>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Order</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Customer</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Total</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3">
                                <a href="{{ route('orders.show', $order) }}" class="font-mono font-medium text-ink hover:text-accent-ink">{{ $order->order_number }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink">{{ $order->customer->full_name }}</td>
                            <td class="px-4 py-3"><x-status-pill :status="$order->status" /></td>
                            <td class="px-4 py-3 font-mono tabular-nums text-ink">GMD {{ number_format($order->total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-ink-faint font-mono text-xs">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint text-sm">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
</x-app-layout>
