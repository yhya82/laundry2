<x-app-layout>
    <x-slot name="header">{{ $customer->full_name }}</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-1 space-y-5">
            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-1">Customer</div>
                        <div class="text-lg font-bold text-ink">{{ $customer->full_name }}</div>
                    </div>
                    @can('customers.manage')
                        <a href="{{ route('customers.edit', $customer) }}" class="text-sm text-ink-muted hover:text-accent-ink">Edit</a>
                    @endcan
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Phone</dt>
                        <dd class="font-mono text-ink">{{ $customer->phone }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Email</dt>
                        <dd class="text-ink">{{ $customer->email ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Type</dt>
                        <dd>
                            <span class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $customer->customer_type === 'subscription' ? 'bg-accent-soft text-accent-ink' : 'bg-pill-bg text-pill-ink' }}">
                                {{ $customer->customer_type === 'subscription' ? 'Subscription' : 'Walk-in' }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Address</dt>
                        <dd class="text-ink text-right">{{ $customer->address ?: '—' }}</dd>
                    </div>
                    @if ($customer->notes)
                        <div class="pt-2 border-t border-line">
                            <dt class="text-ink-muted mb-1">Notes</dt>
                            <dd class="text-ink">{{ $customer->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-1">Store Credit Balance</div>
                <div class="text-2xl font-bold text-accent-ink tabular-nums">GMD {{ number_format($customer->store_credit_balance, 2) }}</div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-5">

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Order History</div>
                @forelse ($customer->orders as $order)
                    <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                        <span class="font-mono text-ink">{{ $order->order_number }}</span>
                        <span class="text-ink-muted">{{ ucfirst($order->status) }}</span>
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($order->total_amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No orders yet — the Laundry Terminal is built in Phase 04.</p>
                @endforelse
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Payment History</div>
                @forelse ($customer->payments as $payment)
                    <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                        <span class="text-ink-muted">{{ ucfirst($payment->method) }}</span>
                        <span class="text-ink-muted">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span>
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($payment->amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No payments yet — built alongside Orders in Phase 04/07.</p>
                @endforelse
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Damage History</div>
                @forelse ($damageRecords as $damage)
                    <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                        <span class="text-ink">{{ $damage->damageType->name ?? '—' }}</span>
                        <span class="text-ink-muted">{{ ucfirst(str_replace('_', ' ', $damage->status)) }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No damage reports — built in Phase 08.</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
