<x-app-layout>
    <x-slot name="header">{{ $order->order_number }}</x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-1 space-y-5">
            <div
                class="bg-surface border border-line rounded-2xl p-6"
                x-data="{
                    status: @js($order->status),
                    tones: { received:'neutral', sorting:'active', washing:'active', drying:'active', ironing:'active', packaging:'active', completed:'success', cancelled:'critical' },
                    classes: { neutral:'bg-pill-bg text-pill-ink', active:'bg-accent-soft text-accent-ink', success:'bg-success-soft text-success', critical:'bg-critical-soft text-critical' },
                    label() { return this.status.charAt(0).toUpperCase() + this.status.slice(1).replace('_', ' '); },
                    init() {
                        window.Echo.channel('orders').listen('.order.status-changed', (e) => {
                            if (e.orderId === {{ $order->id }}) {
                                this.status = e.toStatus;
                            }
                        });
                    }
                }"
            >
                <div class="flex items-center justify-between mb-4">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint">Order</div>
                    <span
                        class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full"
                        :class="classes[tones[status]] ?? classes.neutral"
                        x-text="label()"
                    ></span>
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
                    @if (! $order->isTerminal() && ($turnaroundHours = \App\Models\Setting::get('laundry.default_turnaround_hours')))
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Est. ready</dt>
                            <dd class="text-ink font-mono text-xs">{{ $order->created_at->copy()->addHours((int) $turnaroundHours)->format('Y-m-d H:i') }}</dd>
                        </div>
                    @endif
                    @if ($order->receipt)
                        <div class="flex justify-between pt-2 border-t border-line">
                            <dt class="text-ink-muted">Receipt</dt>
                            <dd class="text-ink font-mono text-xs">{{ $order->receipt->receipt_number }}</dd>
                        </div>
                    @endif
                    @if ($order->status === 'cancelled' && $order->cancellation_reason)
                        <div class="pt-2 border-t border-line">
                            <dt class="text-ink-muted mb-1">Cancellation reason</dt>
                            <dd class="text-ink">{{ $order->cancellation_reason }}</dd>
                        </div>
                    @endif
                </dl>

                @can('orders.manage')
                    @unless ($order->isTerminal())
                        <div class="mt-5 pt-5 border-t border-line space-y-3" x-data="{ cancelling: false }">
                            <form method="POST" action="{{ route('orders.advance', $order) }}">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-accent rounded-lg text-white text-sm font-semibold hover:opacity-90">
                                    Mark as {{ ucfirst($order->nextStatus()) }}
                                </button>
                            </form>

                            <button type="button" @click="cancelling = !cancelling" class="w-full text-xs text-critical hover:underline">
                                Cancel order
                            </button>

                            <form x-show="cancelling" x-cloak method="POST" action="{{ route('orders.cancel', $order) }}" class="space-y-2">
                                @csrf
                                <input type="text" name="cancellation_reason" placeholder="Reason for cancellation" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm" required>
                                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-critical-soft text-critical rounded-lg text-sm font-semibold">
                                    Confirm cancellation
                                </button>
                            </form>
                        </div>
                    @endunless
                @endcan
            </div>

            <div
                class="bg-surface border border-line rounded-2xl p-6"
                x-data="{
                    entries: @js($order->statusHistory->sortByDesc('created_at')->map(fn ($e) => ['from' => $e->from_status, 'to' => $e->to_status, 'time' => $e->created_at->format('H:i')])),
                    init() {
                        window.Echo.channel('orders').listen('.order.status-changed', (e) => {
                            if (e.orderId === {{ $order->id }}) {
                                this.entries.unshift({ from: e.fromStatus, to: e.toStatus, time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) });
                            }
                        });
                    }
                }"
            >
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Status Timeline</div>
                <template x-if="entries.length === 0">
                    <p class="text-ink-faint text-sm">No status changes yet.</p>
                </template>
                <template x-for="(entry, i) in entries" :key="i">
                    <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                        <span class="text-ink" x-text="(entry.from ? entry.from.charAt(0).toUpperCase() + entry.from.slice(1) + ' → ' : '') + entry.to.charAt(0).toUpperCase() + entry.to.slice(1)"></span>
                        <span class="text-ink-faint font-mono text-xs" x-text="entry.time"></span>
                    </div>
                </template>
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint">Damage Reports</div>
                    @can('damage.report')
                        @if ($order->status !== 'cancelled')
                            <a href="{{ route('damage.create', $order) }}" class="text-xs text-accent-ink hover:underline">+ Report damage</a>
                        @endif
                    @endcan
                </div>
                @forelse ($order->damageRecords as $damage)
                    <a href="{{ route('damage.show', $damage) }}" class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm hover:bg-surface-2 -mx-2 px-2 rounded">
                        <span class="text-ink">{{ $damage->damageType->name ?? '—' }}</span>
                        <x-status-pill :status="$damage->status" />
                    </a>
                @empty
                    <p class="text-ink-faint text-sm">None reported.</p>
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
