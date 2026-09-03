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
                    @foreach (['received' => 'Received', 'sorting' => 'Sorting', 'washing' => 'Washing', 'drying' => 'Drying', 'ironing' => 'Ironing', 'packaging' => 'Packaging', 'completed' => 'Completed', 'collection' => 'Collected', 'cancelled' => 'Cancelled'] as $status => $label)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <div
        x-data="{
            init() {
                window.Echo.channel('orders').listen('.order.status-changed', (e) => {
                    document.querySelectorAll('[data-order-status=\'' + e.orderId + '\']').forEach((el) => {
                        window.applyStatusPill(el, e.toStatus);
                    });
                });
            }
        }"
    >
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
                        @if ($assignmentEnabled)
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Assigned</th>
                        @endif
                        <th class="text-right font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            // Computed once per row instead of at each call
                            // site -- both are live aggregate queries under
                            // the hood, not free attribute reads.
                            $paymentStatus = $order->combinedPaymentStatus();
                            $due = $order->balanceDue();
                        @endphp
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3">
                                <a href="{{ route('orders.show', $order) }}" class="font-mono font-medium text-ink hover:text-accent-ink">{{ $order->order_number }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink">{{ $order->customer->full_name }}</td>
                            <td class="px-4 py-3"><x-status-pill :status="$order->priority()" /></td>
                            <td class="px-4 py-3"><x-status-pill :status="$order->status" data-order-status="{{ $order->id }}" /></td>
                            <td class="px-4 py-3">
                                <x-status-pill :status="$paymentStatus" />
                                @if ($paymentStatus !== 'paid' && $due > 0)
                                    <span class="block text-ink-faint text-xs font-mono mt-0.5">GMD {{ number_format($due, 2) }} due</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono tabular-nums text-ink">GMD {{ number_format($order->total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-ink-faint font-mono text-xs">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                            @if ($assignmentEnabled)
                                <td class="px-4 py-3">
                                    @can('orders.assign')
                                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                            <button type="button" @click="open = !open" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap {{ $order->assignedTo ? 'bg-accent-soft text-accent-ink' : 'bg-pill-bg text-pill-ink' }}">
                                                {{ $order->assignedTo?->name ?? 'Unassigned' }}
                                                <x-nav-icon name="arrow-right" class="w-2.5 h-2.5 rotate-90" />
                                            </button>
                                            <div x-show="open" x-cloak x-transition class="absolute z-20 mt-1 w-44 bg-surface border border-line rounded-xl shadow-lg py-1">
                                                @if ($order->assigned_to)
                                                    <form method="POST" action="{{ route('orders.assign', $order) }}">
                                                        @csrf @method('PUT')
                                                        <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-ink-faint hover:bg-surface-2">Unassign</button>
                                                    </form>
                                                @endif
                                                @foreach ($assignableStaff as $staff)
                                                    <form method="POST" action="{{ route('orders.assign', $order) }}">
                                                        @csrf @method('PUT')
                                                        <input type="hidden" name="assigned_to" value="{{ $staff->id }}">
                                                        <button type="submit" class="w-full text-left px-3 py-1.5 text-xs {{ $order->assigned_to === $staff->id ? 'text-accent-ink font-semibold' : 'text-ink' }} hover:bg-surface-2">{{ $staff->name }}</button>
                                                    </form>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-ink-faint text-xs">{{ $order->assignedTo?->name ?? '—' }}</span>
                                    @endcan
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end">
                                    @if ($order->receipt)
                                        <a href="{{ route('orders.receipt', $order) }}" target="_blank" title="Print Receipt" class="w-8 h-8 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center hover:bg-accent hover:text-white transition-colors">
                                            <x-nav-icon name="receipt" class="w-4 h-4" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-10 text-center text-ink-faint text-sm">{{ $scopedToSelf ? 'No orders have been assigned to you yet.' : 'No orders yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($orders as $order)
            @php
                $paymentStatus = $order->combinedPaymentStatus();
                $due = $order->balanceDue();
            @endphp
            <div class="bg-surface border border-line rounded-2xl p-4">
                <a href="{{ route('orders.show', $order) }}" class="block">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-mono font-medium text-ink">{{ $order->order_number }}</span>
                        <div class="flex items-center gap-1.5">
                            @if ($order->priority() === 'high')
                                <x-status-pill :status="$order->priority()" />
                            @endif
                            <x-status-pill :status="$order->status" data-order-status="{{ $order->id }}" />
                            <x-status-pill :status="$paymentStatus" />
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm text-ink-muted">
                        <span>{{ $order->customer->full_name }}</span>
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($order->total_amount, 2) }}</span>
                    </div>
                    @if ($paymentStatus !== 'paid' && $due > 0)
                        <div class="text-critical text-xs font-mono mt-1">GMD {{ number_format($due, 2) }} due</div>
                    @endif
                    <div class="text-ink-faint font-mono text-xs mt-1">{{ $order->created_at->format('Y-m-d H:i') }}</div>
                </a>
                @if ($assignmentEnabled)
                    <div class="border-t border-line mt-3 pt-3">
                        @can('orders.assign')
                            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                <button type="button" @click="open = !open" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap {{ $order->assignedTo ? 'bg-accent-soft text-accent-ink' : 'bg-pill-bg text-pill-ink' }}">
                                    {{ $order->assignedTo?->name ?? 'Unassigned' }}
                                    <x-nav-icon name="arrow-right" class="w-2.5 h-2.5 rotate-90" />
                                </button>
                                <div x-show="open" x-cloak x-transition class="absolute z-20 mt-1 w-44 bg-surface border border-line rounded-xl shadow-lg py-1">
                                    @if ($order->assigned_to)
                                        <form method="POST" action="{{ route('orders.assign', $order) }}">
                                            @csrf @method('PUT')
                                            <button type="submit" class="w-full text-left px-3 py-1.5 text-xs text-ink-faint hover:bg-surface-2">Unassign</button>
                                        </form>
                                    @endif
                                    @foreach ($assignableStaff as $staff)
                                        <form method="POST" action="{{ route('orders.assign', $order) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="assigned_to" value="{{ $staff->id }}">
                                            <button type="submit" class="w-full text-left px-3 py-1.5 text-xs {{ $order->assigned_to === $staff->id ? 'text-accent-ink font-semibold' : 'text-ink' }} hover:bg-surface-2">{{ $staff->name }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <span class="text-ink-faint text-xs">Assigned: {{ $order->assignedTo?->name ?? '—' }}</span>
                        @endcan
                    </div>
                @endif
                @if ($order->receipt)
                    <a href="{{ route('orders.receipt', $order) }}" target="_blank" class="flex items-center justify-center gap-1.5 border-t border-line mt-3 pt-3 text-xs font-semibold text-accent-ink hover:underline">
                        <x-nav-icon name="receipt" class="w-3.5 h-3.5" />
                        Print Receipt
                    </a>
                @endif
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">{{ $scopedToSelf ? 'No orders have been assigned to you yet.' : 'No orders yet.' }}</div>
        @endforelse
    </div>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
</x-app-layout>
