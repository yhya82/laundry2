<x-app-layout>
    <x-slot name="header">{{ $order->order_number }}</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-1 space-y-5">
            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint">Order</div>
                    <x-status-pill :status="$order->status" />
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Customer</dt>
                        <dd><a href="{{ route('customers.show', $order->customer) }}" class="text-accent-ink hover:underline">{{ $order->customer->full_name }}</a></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Source</dt>
                        <dd class="text-ink">{{ $order->order_source === 'walk_in' ? 'Walk-in' : 'Subscription' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Created by</dt>
                        <dd class="text-ink">{{ $order->creator->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Created</dt>
                        <dd class="text-ink font-mono text-xs">{{ $order->created_at->format('Y-m-d H:i') }}</dd>
                    </div>
                    @if ($order->receipt)
                        <div class="flex justify-between pt-2 border-t border-line">
                            <dt class="text-ink-muted">Receipt</dt>
                            <dd class="text-ink font-mono text-xs">{{ $order->receipt->receipt_number }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Status Timeline</div>
                @forelse ($order->statusHistory->sortByDesc('created_at') as $entry)
                    <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                        <span class="text-ink">{{ $entry->from_status ? ucfirst($entry->from_status).' → ' : '' }}{{ ucfirst($entry->to_status) }}</span>
                        <span class="text-ink-faint font-mono text-xs">{{ $entry->created_at->format('H:i') }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No status changes yet — the processing pipeline is built in Phase 06.</p>
                @endforelse
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Damage Reports</div>
                @forelse ($order->damageRecords as $damage)
                    <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                        <span class="text-ink">{{ $damage->damageType->name ?? '—' }}</span>
                        <x-status-pill :status="$damage->status" />
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">None reported — built in Phase 08.</p>
                @endforelse
            </div>
        </div>

        <div class="lg:col-span-2 space-y-5">

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Line Items</div>
                @foreach ($order->packageLines as $line)
                    <div class="border border-line rounded-xl p-4 mb-3 last:mb-0">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-semibold text-ink">{{ $line->package_name_snapshot }} × {{ $line->quantity }}</span>
                            <span class="font-mono tabular-nums text-ink">GMD {{ number_format($line->package_price_snapshot * $line->quantity, 2) }}</span>
                        </div>
                        @if ($line->clothesLines->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($line->clothesLines as $clothes)
                                    <span class="inline-flex items-center bg-pill-bg text-pill-ink font-mono text-xs px-2.5 py-1 rounded-full">
                                        {{ $clothes->item_name_snapshot }} × {{ $clothes->quantity }}
                                        @if ($clothes->is_extra) <span class="ml-1 text-accent-ink">(extra)</span> @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="border-t border-line mt-4 pt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-ink-muted">Subtotal</span><span class="font-mono tabular-nums text-ink">GMD {{ number_format($order->subtotal, 2) }}</span></div>
                    @if ($order->discount > 0)
                        <div class="flex justify-between"><span class="text-ink-muted">Discount ({{ $order->discount_reason }})</span><span class="font-mono tabular-nums text-critical">− GMD {{ number_format($order->discount, 2) }}</span></div>
                    @endif
                    @if ($order->extra_charge > 0)
                        <div class="flex justify-between"><span class="text-ink-muted">Extra charge</span><span class="font-mono tabular-nums text-ink">+ GMD {{ number_format($order->extra_charge, 2) }}</span></div>
                    @endif
                    <div class="flex justify-between pt-2 border-t border-line font-semibold"><span class="text-ink">Total</span><span class="font-mono tabular-nums text-accent-ink text-lg">GMD {{ number_format($order->total_amount, 2) }}</span></div>
                </div>
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Payments</div>
                @forelse ($order->payments as $payment)
                    <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                        <span class="text-ink-muted">{{ ucfirst($payment->method) }}</span>
                        <x-status-pill :status="$payment->status" />
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($payment->amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No payments recorded.</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
