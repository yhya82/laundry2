<x-app-layout>
    <x-slot name="header">Payments</x-slot>

    <div
        x-data="{
            init() {
                window.Echo.channel('payments').listen('.payment.status-changed', (e) => {
                    document.querySelectorAll('[data-payment-status=\'' + e.paymentId + '\']').forEach((el) => {
                        window.applyStatusPill(el, e.toStatus);
                    });
                });
            }
        }"
    >
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
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr class="border-t border-line hover:bg-surface-2">
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
                        <td class="px-4 py-3"><x-status-pill :status="$payment->status" data-payment-status="{{ $payment->id }}" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-ink-faint text-sm">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($payments as $payment)
            <div class="bg-surface border border-line rounded-2xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-ink">{{ $payment->order?->customer?->full_name ?? $payment->subscription?->customer?->full_name ?? '—' }}</span>
                    <x-status-pill :status="$payment->status" data-payment-status="{{ $payment->id }}" />
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
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No payments recorded yet.</div>
        @endforelse
    </div>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
</x-app-layout>
