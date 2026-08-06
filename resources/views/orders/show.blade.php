<x-app-layout>
    <x-slot name="header">{{ $order->order_number }}</x-slot>

    <div class="flex items-center justify-between">
        <x-breadcrumbs :items="[
            ['label' => 'Customers', 'url' => route('customers.index')],
            ['label' => $order->customer->full_name, 'url' => route('customers.show', $order->customer)],
            ['label' => $order->order_number, 'url' => null],
        ]" />

        <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink-muted hover:text-accent-ink transition-colors">
            <x-nav-icon name="clipboard" class="w-4 h-4" />
            All Orders
        </a>
    </div>

    @php
        $stageIcons = [
            'received' => 'box', 'sorting' => 'shirt', 'washing' => 'washer',
            'drying' => 'dryer', 'ironing' => 'iron', 'packaging' => 'gift', 'completed' => 'check',
        ];
        $stageLabels = [
            'received' => 'Received', 'sorting' => 'Start Sorting', 'washing' => 'Start Washing',
            'drying' => 'Start Drying', 'ironing' => 'Start Ironing', 'packaging' => 'Start Packaging', 'completed' => 'Completed',
        ];
        $stages = [...array_keys(\App\Models\Order::STAGE_SEQUENCE), 'completed'];

        $stageTimestamps = ['received' => $order->created_at];
        // Received has no order_status_history row -- the trigger only fires
        // on UPDATE, not the initial INSERT -- so its "who" comes from the
        // order's own creator instead.
        $stageChangedBy = ['received' => $order->creator?->name];
        foreach ($order->statusHistory->sortBy('created_at') as $entry) {
            if (in_array($entry->to_status, $stages, true)) {
                $stageTimestamps[$entry->to_status] = $entry->created_at;
                $stageChangedBy[$entry->to_status] = $entry->changedBy?->name;
            }
        }

        $lastReachedIndex = 0;
        foreach ($stages as $i => $stage) {
            if (isset($stageTimestamps[$stage])) {
                $lastReachedIndex = $i;
            }
        }

        $isActiveOrder = ! $order->isTerminal();
        $nextIsWashing = $order->nextStatus() === 'washing';
    @endphp

    <div
        class="bg-surface border border-line rounded-2xl p-6 mb-5 shadow-sm overflow-x-auto"
        x-data="{
            status: @js($order->status),
            stages: @js($stages),
            currentIndex: @js($lastReachedIndex),
            isActive: @js($isActiveOrder),
            timestamps: @js(collect($stageTimestamps)->map(fn ($t) => $t->format('M d, g:i A'))),
            init() {
                window.Echo.channel('orders').listen('.order.status-changed', (e) => {
                    if (e.orderId !== {{ $order->id }}) return;
                    this.status = e.toStatus;
                    const idx = this.stages.indexOf(e.toStatus);
                    if (idx !== -1) this.currentIndex = idx;
                    this.isActive = ! ['completed', 'cancelled'].includes(e.toStatus);
                    this.timestamps[e.toStatus] = new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                });
            }
        }"
    >
        <div class="flex items-center min-w-max">
            @foreach ($stages as $i => $stage)
                @php
                    $canAdvance = $i === $lastReachedIndex + 1 && $isActiveOrder && auth()->user()?->can('orders.manage');
                @endphp
                <div class="flex items-center {{ $loop->last ? '' : 'flex-1' }}">
                    <div class="flex flex-col items-center text-center w-24 flex-none">
                        @if ($canAdvance && $stage === 'washing')
                            <button
                                type="button"
                                title="{{ $stageLabels[$stage] }}"
                                @click="$dispatch('open-panel', 'select-washing-machine')"
                                class="w-11 h-11 rounded-full bg-surface-2 text-ink-faint flex items-center justify-center border-2 border-dashed border-line-strong hover:border-accent hover:bg-accent-soft hover:text-accent-ink transition-colors cursor-pointer"
                            >
                                <x-nav-icon name="{{ $stageIcons[$stage] }}" class="w-5 h-5" />
                            </button>
                        @elseif ($canAdvance)
                            <form method="POST" action="{{ route('orders.advance', $order) }}" title="{{ $stageLabels[$stage] }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-11 h-11 rounded-full bg-surface-2 text-ink-faint flex items-center justify-center border-2 border-dashed border-line-strong hover:border-accent hover:bg-accent-soft hover:text-accent-ink transition-colors cursor-pointer"
                                >
                                    <x-nav-icon name="{{ $stageIcons[$stage] }}" class="w-5 h-5" />
                                </button>
                            </form>
                        @else
                            <span
                                class="w-11 h-11 rounded-full flex items-center justify-center relative"
                                :class="({{ $i }} < currentIndex || ({{ $i }} === currentIndex && !isActive)) ? 'bg-success-soft text-success' : (({{ $i }} === currentIndex && isActive) ? 'bg-accent-soft text-accent-ink ring-2 ring-accent' : 'bg-surface-2 text-ink-faint')"
                            >
                                <x-nav-icon name="{{ $stageIcons[$stage] }}" class="w-5 h-5" />
                                <span
                                    x-show="{{ $i }} < currentIndex || ({{ $i }} === currentIndex && !isActive)"
                                    class="absolute -bottom-1 -right-1 w-4.5 h-4.5 rounded-full bg-success text-white flex items-center justify-center border-2 border-surface"
                                >
                                    <x-nav-icon name="check" class="w-2.5 h-2.5" />
                                </span>
                            </span>
                        @endif

                        <div
                            class="text-xs font-semibold mt-2"
                            :class="({{ $i }} < currentIndex || ({{ $i }} === currentIndex && !isActive)) ? 'text-ink' : (({{ $i }} === currentIndex && isActive) ? 'text-accent-ink' : 'text-ink-faint')"
                        >
                            {{ $stageLabels[$stage] }}
                        </div>

                        <div class="text-[11px] text-ink-faint font-mono mt-0.5" x-show="timestamps['{{ $stage }}']" x-text="timestamps['{{ $stage }}']"></div>
                        @if (! empty($stageChangedBy[$stage]))
                            <div class="text-[10px] text-ink-faint mt-0.5">by {{ $stageChangedBy[$stage] }}</div>
                        @endif
                        @if ($stage === 'washing' && isset($stageTimestamps['washing']) && $order->washingMachine)
                            <div class="text-[10px] text-ink-faint mt-0.5">{{ $order->washingMachine->name }}</div>
                        @endif
                        <div class="text-[11px] text-accent-ink font-semibold mt-0.5" x-show="{{ $i }} === currentIndex && isActive">In Progress</div>
                    </div>

                    @unless ($loop->last)
                        <div class="flex-1 h-0.5 -mt-6" :class="({{ $i }} < currentIndex || ({{ $i }} === currentIndex && !isActive)) ? 'bg-success' : 'bg-line'"></div>
                    @endunless
                </div>
            @endforeach
        </div>
    </div>

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
                    <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Order</div>
                    <span
                        class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full"
                        :class="classes[tones[status]] ?? classes.neutral"
                        x-text="label()"
                    ></span>
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between items-center">
                        <dt class="text-ink-muted">Customer</dt>
                        <dd>
                            <a href="{{ route('customers.show', $order->customer) }}" class="inline-flex items-center gap-2 hover:underline">
                                <span class="w-6 h-6 rounded-full bg-accent text-white flex items-center justify-center text-xs font-bold flex-none">
                                    {{ Str::substr($order->customer->full_name, 0, 1) }}
                                </span>
                                <span class="text-accent-ink font-medium">{{ $order->customer->full_name }}</span>
                            </a>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Source</dt>
                        <dd class="text-ink">{{ $order->order_source === 'walk_in' ? 'Walk-in' : 'Subscription' }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-ink-muted">Created by</dt>
                        <dd>
                            @if ($order->creator)
                                <span class="inline-flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-pill-bg text-pill-ink flex items-center justify-center text-xs font-bold flex-none">
                                        {{ Str::substr($order->creator->name, 0, 1) }}
                                    </span>
                                    <span class="text-ink font-medium">{{ $order->creator->name }}</span>
                                </span>
                            @else
                                <span class="text-ink">—</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Created</dt>
                        <dd class="text-ink font-mono text-xs">{{ $order->created_at->format('Y-m-d H:i') }}</dd>
                    </div>
                    @if ($order->washingMachine)
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Washing machine</dt>
                            <dd class="text-ink font-mono text-xs">{{ $order->washingMachine->name }}</dd>
                        </div>
                    @endif
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
                    <div class="pt-2 border-t border-line">
                        <dt class="text-ink-muted mb-1">Notes</dt>
                        @if ($order->notes && $order->notes !== 'None')
                            <dd class="flex items-start gap-2 bg-critical-soft text-critical rounded-lg p-2.5">
                                <x-nav-icon name="alert" class="w-4 h-4 flex-none mt-0.5" />
                                <span class="font-medium">{{ $order->notes }}</span>
                            </dd>
                        @else
                            <dd class="text-ink-faint">None</dd>
                        @endif
                    </div>
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
                            @if ($nextIsWashing)
                                <button type="button" @click="$dispatch('open-panel', 'select-washing-machine')" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-accent rounded-lg text-white text-sm font-semibold hover:opacity-90">
                                    {{ $stageLabels['washing'] }}
                                </button>
                            @else
                                <form method="POST" action="{{ route('orders.advance', $order) }}">
                                    @csrf
                                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-accent rounded-lg text-white text-sm font-semibold hover:opacity-90">
                                        {{ $order->nextStatus() === 'completed' ? 'Mark as Completed' : $stageLabels[$order->nextStatus()] }}
                                    </button>
                                </form>
                            @endif

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

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Damage Reports</div>
                    @can('damage.report')
                        @if ($order->status !== 'cancelled')
                            <button type="button" @click="$dispatch('open-panel', 'report-damage')" class="inline-flex items-center gap-1.5 bg-critical text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:opacity-90 transition-opacity">
                                <x-nav-icon name="alert" class="w-3.5 h-3.5" />
                                Report damage
                            </button>
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
                <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold mb-3">Line Items</div>
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
                        <div class="flex justify-between"><span class="text-ink-muted">Extra charge{{ $order->extra_charge_reason ? ' ('.$order->extra_charge_reason.')' : '' }}</span><span class="font-mono tabular-nums text-ink">+ GMD {{ number_format($order->extra_charge, 2) }}</span></div>
                    @endif
                    @if ($order->cycle_overage_charge > 0)
                        <div class="flex justify-between"><span class="text-ink-muted">Cycle overage charge</span><span class="font-mono tabular-nums text-ink">+ GMD {{ number_format($order->cycle_overage_charge, 2) }}</span></div>
                    @endif
                    <div class="flex justify-between pt-2 border-t border-line font-semibold"><span class="text-ink">Total</span><span class="font-mono tabular-nums text-accent-ink text-lg">GMD {{ number_format($order->total_amount, 2) }}</span></div>
                </div>
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Payments</div>
                        <x-status-pill :status="$order->combinedPaymentStatus()" />
                    </div>
                    @can('orders.manage')
                        @if ($order->balanceDue() > 0)
                            <x-panel-trigger panel="record-payment">Record Payment</x-panel-trigger>
                        @endif
                    @endcan
                </div>

                @if ($order->order_source === 'subscription')
                    @php $cycle = $order->subscriptionCycle(); @endphp
                    <div class="flex items-center justify-between gap-2 mb-3 p-2.5 rounded-lg bg-accent-soft text-sm">
                        <span class="inline-flex items-center gap-1.5 text-accent-ink font-semibold">
                            <x-nav-icon name="repeat" class="w-3.5 h-3.5" />
                            Subscription pickup
                        </span>
                        @if ($cycle)
                            @if ($cycle->isPaid())
                                <span class="font-mono text-xs text-success font-semibold">Cycle payment completed</span>
                            @else
                                <a href="{{ route('subscriptions.show', $order->collection->subscription) }}" class="font-mono text-xs text-critical font-semibold hover:underline">
                                    Cycle balance due: GMD {{ number_format($cycle->balanceDue(), 2) }}
                                </a>
                            @endif
                        @endif
                    </div>
                @endif

                @forelse ($order->payments as $payment)
                    <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                        <span class="text-ink-muted">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</span>
                        <x-status-pill :status="$payment->status" />
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($payment->amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No payments recorded.</p>
                @endforelse

                <div class="border-t border-line mt-3 pt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-ink-muted">Amount Paid</span><span class="font-mono tabular-nums text-ink">GMD {{ number_format($order->amountPaid(), 2) }}</span></div>
                    @if ($order->balanceDue() > 0)
                        <div class="flex justify-between font-semibold"><span class="text-critical">Balance Due</span><span class="font-mono tabular-nums text-critical">GMD {{ number_format($order->balanceDue(), 2) }}</span></div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    @can('orders.manage')
        @if ($order->balanceDue() > 0)
            <x-slide-panel name="record-payment" title="Record Payment" :error-fields="['amount', 'credit_applied', 'method']">
                <form method="POST" action="{{ route('orders.payments.record', $order) }}" class="space-y-4">
                    @csrf
                    <p class="text-sm text-ink-muted">Balance due: <span class="font-mono text-ink font-semibold">GMD {{ number_format($order->balanceDue(), 2) }}</span></p>

                    @if ($order->customer->store_credit_balance > 0)
                        <div>
                            <x-input-label for="credit_applied" value="Apply store credit" />
                            <x-text-input id="credit_applied" name="credit_applied" type="number" step="0.01" min="0" max="{{ min($order->customer->store_credit_balance, $order->balanceDue()) }}" class="block w-full" />
                            <p class="text-xs text-ink-faint mt-1">Of GMD {{ number_format($order->customer->store_credit_balance, 2) }} available.</p>
                            <x-input-error :messages="$errors->get('credit_applied')" class="mt-1.5" />
                        </div>
                    @endif

                    <div>
                        <x-input-label for="record_amount" value="Amount collected (GMD)" />
                        <x-text-input id="record_amount" name="amount" type="number" step="0.01" min="0" class="block w-full" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="record_method" value="Method" />
                        <select id="record_method" name="method" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="mixed">Mixed</option>
                        </select>
                        <x-input-error :messages="$errors->get('method')" class="mt-1.5" />
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>Record Payment</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                    </div>
                </form>
            </x-slide-panel>
        @endif
    @endcan

    @can('orders.manage')
        @if ($nextIsWashing)
            <div
                x-data="{
                    open: @js($errors->has('washing_machine_id')),
                    machines: @js($washingMachines->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'busy' => $m->isBusy(),
                        'currentOrderNumber' => $m->currentOrder()?->order_number,
                    ])),
                    get allBusy() { return this.machines.length > 0 && this.machines.every(m => m.busy); },
                    init() {
                        window.Echo.channel('orders').listen('.order.status-changed', (e) => {
                            const machine = this.machines.find(m => m.id === e.washingMachineId);
                            if (! machine) return;
                            machine.busy = e.toStatus === 'washing';
                            machine.currentOrderNumber = machine.busy ? e.orderNumber : null;
                        });
                    }
                }"
                x-on:open-panel.window="$event.detail === 'select-washing-machine' && (open = true)"
                x-on:close-panel.window="$event.detail === 'select-washing-machine' && (open = false)"
                x-on:keydown.escape.window="open = false"
            >
                <div
                    x-show="open" x-cloak
                    class="fixed inset-0 bg-ink/40 backdrop-blur-sm z-40 flex items-center justify-center p-4"
                    x-transition.opacity
                    @click.self="open = false"
                >
                    <div
                        class="bg-surface border border-line rounded-2xl shadow-xl w-full max-w-md p-6"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-ink">Select Washing Machine</h3>
                            <button type="button" @click="open = false" class="text-ink-faint hover:text-ink" aria-label="Close">✕</button>
                        </div>

                        @if ($washingMachines->isEmpty())
                            <p class="text-sm text-ink-muted">
                                No active washing machines yet.
                                @can('catalog.manage')
                                    <a href="{{ route('catalog.machines') }}" class="text-accent-ink hover:underline">Add one in Catalog &rarr; Machines</a>.
                                @endcan
                            </p>
                            <div class="flex justify-end mt-4">
                                <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Close</button>
                            </div>
                        @else
                            <template x-if="allBusy">
                                <div>
                                    <p class="text-sm text-ink-muted">All washing machines are currently busy. Wait for one to finish before starting another.</p>
                                    <div class="flex justify-end mt-4">
                                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Close</button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="! allBusy">
                                <form method="POST" action="{{ route('orders.advance', $order) }}" class="space-y-4">
                                    @csrf
                                    <p class="text-sm text-ink-muted">Pick a machine to start washing this order.</p>

                                    <div class="space-y-2">
                                        <template x-for="machine in machines" :key="machine.id">
                                            <label
                                                class="flex items-center justify-between gap-2 border rounded-xl px-3 py-2.5 text-sm transition-colors"
                                                :class="machine.busy ? 'opacity-50 cursor-not-allowed border-line' : 'cursor-pointer border-line-strong has-[:checked]:border-accent has-[:checked]:bg-accent-soft'"
                                            >
                                                <span class="flex items-center gap-2">
                                                    <input type="radio" name="washing_machine_id" :value="machine.id" class="text-accent focus:ring-accent" :disabled="machine.busy" required>
                                                    <span class="text-ink font-medium" x-text="machine.name"></span>
                                                </span>
                                                <span x-show="machine.busy" class="font-mono text-xs text-critical" x-text="'Washing — ' + machine.currentOrderNumber"></span>
                                                <span x-show="! machine.busy" class="font-mono text-xs text-success">Idle</span>
                                            </label>
                                        </template>
                                    </div>
                                    <x-input-error :messages="$errors->get('washing_machine_id')" class="mt-1.5" />

                                    <div class="flex items-center gap-3">
                                        <x-primary-button>Start Washing</x-primary-button>
                                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                                    </div>
                                </form>
                            </template>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endcan

    @can('damage.report')
        @if ($order->status !== 'cancelled')
            <x-slide-panel name="report-damage" title="Report Damage" :error-fields="['damage_type_id', 'item_description', 'description', 'photo']">
                <form method="POST" action="{{ route('damage.store', $order) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="rd_damage_type_id" value="Damage type" />
                        <select id="rd_damage_type_id" name="damage_type_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                            <option value="">Select a type…</option>
                            @foreach ($damageTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('damage_type_id') == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('damage_type_id')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="rd_item_description" value="Item" />
                        <x-text-input id="rd_item_description" name="item_description" type="text" class="block w-full" value="{{ old('item_description') }}" placeholder="e.g. White Shirt" required />
                        <x-input-error :messages="$errors->get('item_description')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="rd_description" value="Description (optional)" />
                        <textarea id="rd_description" name="description" rows="3" class="block w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="rd_photo" value="Photo evidence (optional)" />
                        <input id="rd_photo" name="photo" type="file" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-accent-soft file:text-accent-ink file:text-xs file:font-semibold">
                        <x-input-error :messages="$errors->get('photo')" class="mt-1.5" />
                    </div>

                    <p class="text-xs text-ink-faint">Reported at stage: <span class="font-mono">{{ ucfirst($order->status) }}</span></p>

                    <div class="flex items-center gap-3">
                        <x-primary-button>Submit report</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                    </div>
                </form>
            </x-slide-panel>
        @endif
    @endcan
</x-app-layout>
