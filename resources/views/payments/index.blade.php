<x-app-layout>
    <x-slot name="header">Payments</x-slot>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden hidden md:block">
        <table class="w-full text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Date</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Customer</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Order</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Method</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Amount</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr class="border-t border-line hover:bg-surface-2" x-data="{ refunding: false }">
                        <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-ink">{{ $payment->order?->customer?->full_name ?? $payment->subscription?->customer?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($payment->order)
                                <a href="{{ route('orders.show', $payment->order) }}" class="font-mono text-accent-ink hover:underline">{{ $payment->order->order_number }}</a>
                            @else
                                <span class="text-ink-faint">Subscription</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-ink-muted">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                        <td class="px-4 py-3 font-mono tabular-nums text-ink">
                            GMD {{ number_format($payment->amount, 2) }}
                            @if ($payment->credit_applied > 0)
                                <div class="text-xs text-ink-faint">({{ number_format($payment->credit_applied, 2) }} credit)</div>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-status-pill :status="$payment->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('payments.manage')
                                @if ($payment->remainingRefundable() > 0)
                                    <button type="button" @click="refunding = !refunding" class="text-critical text-xs hover:underline">Refund</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                    @can('payments.manage')
                        @if ($payment->remainingRefundable() > 0)
                            <tr x-show="refunding" x-cloak class="border-t border-line bg-surface-2">
                                <td colspan="7" class="px-4 py-3">
                                    <form method="POST" action="{{ route('payments.refund', $payment) }}" class="flex items-center gap-3">
                                        @csrf
                                        <span class="text-xs text-ink-faint">Refundable: GMD {{ number_format($payment->remainingRefundable(), 2) }}</span>
                                        <input type="number" step="0.01" min="0.01" max="{{ $payment->remainingRefundable() }}" name="amount" placeholder="Amount" class="w-28 bg-surface border-line-strong rounded-lg shadow-sm text-sm font-mono" required>
                                        <input type="text" name="reason" placeholder="Reason (optional)" class="flex-1 bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                                        <button type="submit" class="px-4 py-1.5 bg-critical-soft text-critical rounded-lg text-xs font-semibold">Confirm refund</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endcan
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-ink-faint text-sm">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($payments as $payment)
            <div class="bg-surface border border-line rounded-2xl p-4" x-data="{ refunding: false }">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-ink">{{ $payment->order?->customer?->full_name ?? $payment->subscription?->customer?->full_name ?? '—' }}</span>
                    <x-status-pill :status="$payment->status" />
                </div>
                <div class="flex items-center justify-between text-sm text-ink-muted mb-1">
                    <span>
                        @if ($payment->order)
                            <a href="{{ route('orders.show', $payment->order) }}" class="font-mono text-accent-ink hover:underline">{{ $payment->order->order_number }}</a>
                        @else
                            <span class="text-ink-faint">Subscription</span>
                        @endif
                        · {{ ucfirst(str_replace('_', ' ', $payment->method)) }}
                    </span>
                    <span class="font-mono tabular-nums text-ink">GMD {{ number_format($payment->amount, 2) }}</span>
                </div>
                <div class="text-ink-faint font-mono text-xs">{{ $payment->created_at->format('Y-m-d H:i') }}</div>
                @can('payments.manage')
                    @if ($payment->remainingRefundable() > 0)
                        <button type="button" @click="refunding = !refunding" class="text-critical text-xs hover:underline mt-2">Refund</button>
                        <form x-show="refunding" x-cloak method="POST" action="{{ route('payments.refund', $payment) }}" class="mt-2 space-y-2">
                            @csrf
                            <div class="text-xs text-ink-faint">Refundable: GMD {{ number_format($payment->remainingRefundable(), 2) }}</div>
                            <input type="number" step="0.01" min="0.01" max="{{ $payment->remainingRefundable() }}" name="amount" placeholder="Amount" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm font-mono" required>
                            <input type="text" name="reason" placeholder="Reason (optional)" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                            <button type="submit" class="w-full px-4 py-1.5 bg-critical-soft text-critical rounded-lg text-xs font-semibold">Confirm refund</button>
                        </form>
                    @endif
                @endcan
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No payments recorded yet.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
</x-app-layout>
