<x-app-layout>
    <x-slot name="header">Orders</x-slot>

    <div class="flex items-center justify-between mb-5 gap-4">
        @can('terminal.use')
            <a href="{{ route('orders.create') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-accent border border-transparent rounded-lg font-semibold text-sm text-white hover:opacity-90 whitespace-nowrap">
                + New Order
            </a>
        @endcan

        <div class="flex items-center gap-2">
            <form method="GET" class="flex gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search order number or customer…" class="w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm">
                <select name="status" onchange="this.form.submit()" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                    <option value="">All statuses</option>
                    @foreach (['received', 'sorting', 'washing', 'drying', 'ironing', 'packaging', 'completed', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden hidden md:block">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Order</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Customer</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Priority</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Payment</th>
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
                            <td class="px-4 py-3"><x-status-pill :status="$order->priority()" /></td>
                            <td class="px-4 py-3"><x-status-pill :status="$order->status" /></td>
                            <td class="px-4 py-3">
                                <x-status-pill :status="$order->combinedPaymentStatus()" />
                                @if ($order->combinedPaymentStatus() !== 'paid' && $order->balanceDue() > 0)
                                    <span class="block text-ink-faint text-xs font-mono mt-0.5">GMD {{ number_format($order->balanceDue(), 2) }} due</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono tabular-nums text-ink">GMD {{ number_format($order->total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-ink-faint font-mono text-xs">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-ink-faint text-sm">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($orders as $order)
            <a href="{{ route('orders.show', $order) }}" class="block bg-surface border border-line rounded-2xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-mono font-medium text-ink">{{ $order->order_number }}</span>
                    <div class="flex items-center gap-1.5">
                        @if ($order->priority() === 'high')
                            <x-status-pill :status="$order->priority()" />
                        @endif
                        <x-status-pill :status="$order->status" />
                        <x-status-pill :status="$order->combinedPaymentStatus()" />
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm text-ink-muted">
                    <span>{{ $order->customer->full_name }}</span>
                    <span class="font-mono tabular-nums text-ink">GMD {{ number_format($order->total_amount, 2) }}</span>
                </div>
                @if ($order->combinedPaymentStatus() !== 'paid' && $order->balanceDue() > 0)
                    <div class="text-critical text-xs font-mono mt-1">GMD {{ number_format($order->balanceDue(), 2) }} due</div>
                @endif
                <div class="text-ink-faint font-mono text-xs mt-1">{{ $order->created_at->format('Y-m-d H:i') }}</div>
            </a>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No orders yet.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
</x-app-layout>
