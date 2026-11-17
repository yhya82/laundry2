<x-app-layout>
    <x-slot name="header">{{ $order->order_number }}</x-slot>

    <div class="flex items-center justify-between mb-6">
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
        'received' => 'box',
        'wash' => 'washer',
        'completed' => 'check',
        'collection' => 'truck',
    ];

    $stageLabels = [
        'received' => 'Received',
        'wash' => 'Start Wash',
        'completed' => 'Completed',
        'collection' => 'Collection',
    ];

    // Timeline-only labels.
    $stageCurrentLabels = [
        'received' => 'Received',
        'wash' => 'Wash',
        'completed' => 'Completed',
        'collection' => 'Collected',
    ];

    $stagePastLabels = [
        'received' => 'Received',
        'wash' => 'Washed',
        'completed' => 'Completed',
        'collection' => 'Collected',
    ];

    $stages = [...array_keys(\App\Models\Order::STAGE_SEQUENCE), 'collection'];

    $stageTimestamps = ['received' => $order->created_at];

    // Received has no order_status_history row, so use the order creator.
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

    $nextIsWashing = $order->nextStatus() === 'wash';
    $nextIsCollection = $order->nextStatus() === 'collection';

    $paymentStatus = $order->combinedPaymentStatus();

    /*
    |--------------------------------------------------------------------------
    | Order and Subscription Cycle Balances
    |--------------------------------------------------------------------------
    */

    $cycle = $order->subscriptionCycle();
    $collection = $order->collection;

    // The order's own outstanding balance, such as an overage charge.
    $orderDue = $order->balanceDue();

    // Includes the cycle fee when applicable.
    $combinedDue = $order->combinedBalanceDue();

    $combinedPaid = $order->amountPaid()
        + ($cycle?->amountPaid() ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Record Payment
    |--------------------------------------------------------------------------
    |
    | Keep the existing behavior for the Record Payment panel.
    | This allows the cycle fee to be paid early, even before the final
    | scheduled collection.
    |
    */

    $payableDue = (
        $orderDue > 0
        && $order->status !== 'cancelled'
    )
        ? $orderDue
        : ($cycle?->balanceDue() ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Record Collection
    |--------------------------------------------------------------------------
    |
    | Walk-in orders:
    |   Their own outstanding balance must be paid.
    |
    | Subscription orders:
    |   Any order-specific balance (e.g. an overage charge) must be paid.
    |   The shared cycle fee is optional on non-final collections.
    |   On the LAST collection of the cycle, the order's balance AND the
    |   remaining cycle fee must both be paid (same rule as
    |   OrderController::advanceToCollection()).
    |
    */

    $isSubscription = $order->order_source === 'subscription';

    $isFinalSubscriptionCollection = $isSubscription
        && $cycle !== null
        && $collection !== null
        && $cycle->isLastScheduledCollection($collection);

    $collectionCycleDue = $order->status !== 'cancelled' ? ($cycle?->balanceDue() ?? 0) : 0;
    $collectionOrderDue = $order->status !== 'cancelled' ? $orderDue : 0;

    $collectionPayableDue = round($collectionOrderDue + ($isFinalSubscriptionCollection ? $collectionCycleDue : 0), 2);

    /*
    |--------------------------------------------------------------------------
    | Display Totals
    |--------------------------------------------------------------------------
    */

    $grandTotal = (float) $order->total_amount
        + ($cycle?->monthly_price_snapshot ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Status Styling
    |--------------------------------------------------------------------------
    */

    $statusTones = [
        'received' => 'neutral',
        'wash' => 'active',
        'completed' => 'active',
        'collection' => 'success',
        'cancelled' => 'critical',
    ];

    $statusToneClasses = [
        'neutral' => 'bg-pill-bg text-pill-ink',
        'active' => 'bg-accent-soft text-accent-ink',
        'success' => 'bg-success-soft text-success',
        'critical' => 'bg-critical-soft text-critical',
    ];

    /*
    |--------------------------------------------------------------------------
    | Handling Card
    |--------------------------------------------------------------------------
    */

    $washStartedAt = $stageTimestamps['wash'] ?? null;
    $washEndedAt = $stageTimestamps['completed'] ?? null;

    $machineState = $order->status === 'wash'
        ? 'washing'
        : 'idle';

    $loadCount = $order->packageLines->sum(
        fn ($line) => $line->clothesLines->sum('quantity')
    );

    $washDuration = $washStartedAt && $washEndedAt
        ? $washStartedAt->diffForHumans($washEndedAt, true)
        : null;

    
@endphp



    {{-- Summary hero: who, what stage, and the one number that matters -- answers "what do I need to know" before any card does. --}}
    <div
        class="flex items-center justify-between gap-6 flex-wrap pb-6 mb-6 border-b border-line"
        x-data="{
            status: @js($order->status),
            tones: @js($statusTones),
            classes: @js($statusToneClasses),
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
        <div class="flex items-center gap-4 min-w-0">
            <span class="w-12 h-12 rounded-full bg-accent text-white flex items-center justify-center text-lg font-bold flex-none">
                {{ Str::substr($order->customer->full_name, 0, 1) }}
            </span>
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-ink tracking-tight truncate">{{ $order->order_number }}</h1>
                <div class="flex items-center gap-2.5 text-sm text-ink-muted mt-0.5 flex-wrap">
                    <a href="{{ route('customers.show', $order->customer) }}" class="font-semibold text-accent-ink hover:underline">{{ $order->customer->full_name }}</a>
                    <span class="w-1 h-1 rounded-full bg-ink-faint"></span>
                    <span>{{ $order->order_source === 'walk_in' ? 'Walk-in order' : 'Subscription pickup' }}</span>
                    <span class="w-1 h-1 rounded-full bg-ink-faint"></span>
                    <span
                        class="inline-flex items-center font-mono text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide"
                        :class="classes[tones[status]] ?? classes.neutral"
                        x-text="label()"
                    ></span>
                </div>
            </div>
        </div>
        <div class="text-right flex-none">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-1">{{ $combinedDue > 0 ? 'Balance due' : 'Payment' }}</div>
            @if ($combinedDue > 0)
                <div class="font-mono text-3xl font-bold tabular-nums text-critical">GMD {{ number_format($combinedDue, 2) }}</div>
            @else
                <div class="font-mono text-3xl font-bold tabular-nums text-success">Paid in full</div>
            @endif
        </div>
    </div>

    <div
        class="bg-surface border border-line rounded-2xl px-6 pt-7 pb-6 mb-6 shadow-sm overflow-x-auto"
        x-data="{
            status: @js($order->status),
            stages: @js($stages),
            currentIndex: @js($lastReachedIndex),
            isActive: @js($isActiveOrder),
            timestamps: @js(collect($stageTimestamps)->map(fn ($t) => $t->format('M d, g:i A'))),
            futureLabels: @js($stageLabels),
            currentLabels: @js($stageCurrentLabels),
            pastLabels: @js($stagePastLabels),
            labelFor(i, stage) {
                if (i < this.currentIndex || (i === this.currentIndex && !this.isActive)) return this.pastLabels[stage];
                if (i === this.currentIndex && this.isActive) return this.currentLabels[stage];
                return this.futureLabels[stage];
            },
            init() {
                window.Echo.channel('orders').listen('.order.status-changed', (e) => {
                    if (e.orderId !== {{ $order->id }}) return;
                    this.status = e.toStatus;
                    const idx = this.stages.indexOf(e.toStatus);
                    if (idx !== -1) this.currentIndex = idx;
                    this.isActive = ! ['collection', 'cancelled'].includes(e.toStatus);
                    this.timestamps[e.toStatus] = new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                });
            }
        }"
    >
        <div class="flex items-center min-w-max">
            @foreach ($stages as $i => $stage)
                @php
                    $canAdvance = $i === $lastReachedIndex + 1 && $isActiveOrder && auth()->user()?->can('orders.manage');
                    $initialStageLabel = match (true) {
                        $i < $lastReachedIndex || ($i === $lastReachedIndex && ! $isActiveOrder) => $stagePastLabels[$stage],
                        $i === $lastReachedIndex && $isActiveOrder => $stageCurrentLabels[$stage],
                        default => $stageLabels[$stage],
                    };
                @endphp
                <div class="flex items-center {{ $loop->last ? '' : 'flex-1' }}">
                    <div class="flex flex-col items-center text-center w-36 flex-none">
                        @if ($canAdvance && $stage === 'wash')
                            <button
                                type="button"
                                title="{{ $stageLabels[$stage] }}"
                                @click="$dispatch('open-panel', 'select-washing-machine')"
                                class="w-20 h-20 rounded-full bg-surface-2 text-ink-faint flex items-center justify-center border-2 border-dashed border-line-strong hover:border-accent hover:bg-accent-soft hover:text-accent-ink transition-colors cursor-pointer"
                            >
                                <x-nav-icon name="{{ $stageIcons[$stage] }}" class="!w-8 !h-8" />
                            </button>
                        @elseif ($canAdvance && $stage === 'collection')
                            <button
                                type="button"
                                title="Record Collection"
                                @click="$dispatch('open-panel', 'record-collection')"
                                class="w-20 h-20 rounded-full bg-surface-2 text-ink-faint flex items-center justify-center border-2 border-dashed border-line-strong hover:border-accent hover:bg-accent-soft hover:text-accent-ink transition-colors cursor-pointer"
                            >
                                <x-nav-icon name="{{ $stageIcons[$stage] }}" class="!w-8 !h-8" />
                            </button>
                        @elseif ($canAdvance)
                            <form method="POST" action="{{ route('orders.advance', $order) }}" title="{{ $stageLabels[$stage] }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-20 h-20 rounded-full bg-surface-2 text-ink-faint flex items-center justify-center border-2 border-dashed border-line-strong hover:border-accent hover:bg-accent-soft hover:text-accent-ink transition-colors cursor-pointer"
                                >
                                    <x-nav-icon name="{{ $stageIcons[$stage] }}" class="!w-8 !h-8" />
                                </button>
                            </form>
                        @else
                            <span
                                class="w-20 h-20 rounded-full flex items-center justify-center relative transition-all duration-500 ease-out motion-reduce:transition-none"
                                :class="({{ $i }} < currentIndex || ({{ $i }} === currentIndex && !isActive)) ? 'bg-success-soft text-success' : (({{ $i }} === currentIndex && isActive) ? 'bg-accent-soft text-accent-ink ring-2 ring-accent animate-ring-pulse' : 'bg-surface-2 text-ink-faint')"
                            >
                                <x-nav-icon name="{{ $stageIcons[$stage] }}" class="!w-8 !h-8" />
                            </span>
                        @endif

                        <div
                            class="text-xs font-semibold mt-2.5 transition-colors duration-500 ease-out motion-reduce:transition-none"
                            :class="({{ $i }} < currentIndex || ({{ $i }} === currentIndex && !isActive)) ? 'text-ink' : (({{ $i }} === currentIndex && isActive) ? 'text-accent-ink' : 'text-ink-faint')"
                            x-text="labelFor({{ $i }}, '{{ $stage }}')"
                        >{{ $initialStageLabel }}</div>

                        <div
                            class="text-[11px] text-ink-faint font-mono mt-1"
                            x-show="timestamps['{{ $stage }}']"
                            x-transition:enter="transition ease-out duration-300 delay-150"
                            x-transition:enter-start="opacity-0 -translate-y-0.5"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-text="timestamps['{{ $stage }}']"
                        ></div>
                        @if (! empty($stageChangedBy[$stage]))
                            <div class="text-[11px] text-ink-faint mt-0.5">{{ $stage === 'collection' ? 'Collected from' : 'by' }} <span class="font-semibold text-ink">{{ $stageChangedBy[$stage] }}</span></div>
                        @endif
                        @if ($stage === 'wash' && isset($stageTimestamps['wash']) && $order->washingMachine)
                            <div class="text-[11px] font-semibold text-ink mt-0.5">{{ $order->washingMachine->name }}</div>
                        @endif
                        @if ($stage === 'collection' && $order->status === 'collection')
                            <div class="text-[11px] text-ink-faint mt-0.5">Collected by <span class="font-semibold text-ink">{{ $order->collectedByDisplayName() }}</span></div>
                        @endif
                        @unless ($stage === 'completed')
                            <div
                                class="text-[11px] text-accent-ink font-semibold mt-0.5"
                                x-show="{{ $i }} === currentIndex && isActive"
                                x-transition:enter="transition ease-out duration-300 delay-150"
                                x-transition:enter-start="opacity-0 -translate-y-0.5"
                                x-transition:enter-end="opacity-100 translate-y-0"
                            >In Progress</div>
                        @endunless
                    </div>

                    @unless ($loop->last)
                        <div class="flex-1 h-0.5 -mt-[44px] rounded-full bg-line overflow-hidden">
                            <div
                                class="h-full bg-success transition-all duration-700 ease-in-out motion-reduce:transition-none"
                                :class="({{ $i }} < currentIndex || ({{ $i }} === currentIndex && !isActive)) ? 'w-full' : 'w-0'"
                            ></div>
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 {{ $order->washingMachine ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }} gap-5 mb-5">

            @if ($order->washingMachine)
                <div class="bg-surface border border-line rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Handling</div>
                        <span class="inline-flex items-center font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $machineState === 'washing' ? 'bg-accent-soft text-accent-ink' : 'bg-success-soft text-success' }}">{{ $machineState === 'washing' ? 'Running' : 'Finished' }}</span>
                    </div>

                    <div class="flex justify-center mb-4">
                        <x-machine-illustration :status="$machineState" :name="$order->washingMachine->name" :size="96" :interactive="true" />
                    </div>

                    <div class="text-center mb-4">
                        <div class="font-semibold text-ink text-sm">{{ $order->washingMachine->name }}</div>
                        <div class="text-xs text-ink-muted mt-0.5">
                            @if ($machineState === 'washing' && $washStartedAt)
                                Started {{ $washStartedAt->format('g:i A') }}
                            @elseif ($washDuration)
                                Wash finished {{ $washEndedAt->format('g:i A') }} · {{ $washDuration }} cycle
                            @elseif ($washStartedAt)
                                Started {{ $washStartedAt->format('g:i A') }}
                            @endif
                        </div>
                    </div>

                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Customer</dt>
                            <dd class="text-ink font-medium">{{ $order->customer->full_name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Machine</dt>
                            <dd class="text-ink font-mono text-xs">{{ $order->washingMachine->name }}</dd>
                        </div>
                        @if (! empty($stageChangedBy['wash']))
                            <div class="flex justify-between">
                                <dt class="text-ink-muted">Washed by</dt>
                                <dd class="text-ink font-mono text-xs">{{ $stageChangedBy['wash'] }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Load</dt>
                            <dd class="text-ink font-mono text-xs">{{ $loadCount }} {{ Str::plural('piece', $loadCount) }}</dd>
                        </div>
                        @if ($washDuration)
                            <div class="flex justify-between">
                                <dt class="text-ink-muted">Duration</dt>
                                <dd class="text-ink font-mono text-xs">{{ $washDuration }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold mb-4">Order Details</div>

                <div class="space-y-2 text-sm mb-5">
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Customer</dt>
                        <dd class="text-ink font-medium"><a href="{{ route('customers.show', $order->customer) }}" class="hover:text-accent-ink hover:underline">{{ $order->customer->full_name }}</a></dd>
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
                </div>

                @if ($assignmentEnabled || $order->washingMachine || (! $order->isTerminal() && \App\Models\Setting::get('laundry.default_turnaround_hours')))
                    <div class="space-y-2 text-sm mb-5">
                        <div class="text-[11px] font-bold text-ink-faint uppercase tracking-wide mb-1.5">Handling</div>
                        @if ($assignmentEnabled)
                            <div class="flex justify-between items-center">
                                <dt class="text-ink-muted">Assigned to</dt>
                                <dd>
                                    @if ($order->assignedTo)
                                        <span class="inline-flex items-center gap-2">
                                            <span class="w-6 h-6 rounded-full bg-accent-soft text-accent-ink flex items-center justify-center text-xs font-bold flex-none">
                                                {{ Str::substr($order->assignedTo->name, 0, 1) }}
                                            </span>
                                            <span class="text-ink font-medium">{{ $order->assignedTo->name }}</span>
                                        </span>
                                    @else
                                        <span class="text-ink-faint">Unassigned</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
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
                    </div>
                @endif

                <div class="mb-5">
                    @if ($order->notes && $order->notes !== 'None')
                        <div class="flex items-start gap-2 bg-critical-soft text-critical rounded-xl p-3 text-sm font-medium leading-relaxed">
                            <x-nav-icon name="alert" class="w-4 h-4 flex-none mt-0.5" />
                            <span>{{ $order->notes }}</span>
                        </div>
                    @else
                        <div class="text-ink-faint text-xs">No notes for this order.</div>
                    @endif
                </div>

                @if ($order->status === 'cancelled' && $order->cancellation_reason)
                    <div class="text-sm mb-5">
                        <dt class="text-ink-muted mb-1">Cancellation reason</dt>
                        <dd class="text-ink">{{ $order->cancellation_reason }}</dd>
                    </div>
                @endif

                @if ($order->receipt)
                    <a href="{{ route('orders.receipt', $order) }}" target="_blank" class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-surface-2 hover:bg-accent-soft transition-colors group">
                        <span class="font-mono text-xs text-ink-faint">{{ $order->receipt->receipt_number }}</span>
                        <span class="inline-flex items-center gap-1.5 font-semibold text-accent-ink text-sm">
                            <x-nav-icon name="receipt" class="w-3.5 h-3.5" />
                            Print Receipt
                        </span>
                    </a>
                @endif

                @can('orders.manage')
                    @unless ($order->isTerminal())
                        <div class="mt-5 pt-5 border-t border-line space-y-3" x-data="{ cancelling: false }">
                            @if ($nextIsWashing)
                                <button type="button" @click="$dispatch('open-panel', 'select-washing-machine')" class="w-full inline-flex items-center justify-center px-4 py-3 bg-accent rounded-xl text-white text-sm font-semibold hover:opacity-90 transition-opacity">
                                    {{ $stageLabels['wash'] }}
                                </button>
                            @elseif ($nextIsCollection)
                                <button type="button" @click="$dispatch('open-panel', 'record-collection')" class="w-full inline-flex items-center justify-center px-4 py-3 bg-accent rounded-xl text-white text-sm font-semibold hover:opacity-90 transition-opacity">
                                    Record Collection
                                </button>
                            @else
                                <form method="POST" action="{{ route('orders.advance', $order) }}">
                                    @csrf
                                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-accent rounded-xl text-white text-sm font-semibold hover:opacity-90 transition-opacity">
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

            <div class="flex flex-col gap-5 h-full">
                <div class="bg-surface border border-line rounded-2xl p-6 flex-1 flex flex-col">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold mb-3">Collection</div>
                    @if ($order->collected_by_type)
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-ink-muted">Collected by</dt>
                                <dd class="text-ink font-medium text-right">
                                    {{ $order->collected_by_type === 'customer' ? $order->customer->full_name : $order->collected_by_name }}
                                </dd>
                            </div>
                            @if ($order->collected_by_type === 'other' && $order->collected_by_phone)
                                <div class="flex justify-between">
                                    <dt class="text-ink-muted">Phone</dt>
                                    <dd class="text-ink font-mono text-xs">{{ $order->collected_by_phone }}</dd>
                                </div>
                            @endif
                            @if (isset($stageTimestamps['collection']))
                                <div class="flex justify-between">
                                    <dt class="text-ink-muted">Collected</dt>
                                    <dd class="text-ink font-mono text-xs">{{ $stageTimestamps['collection']->format('Y-m-d g:i A') }}</dd>
                                </div>
                            @endif
                        </dl>
                    @else
                        <p class="text-ink-faint text-sm">Not yet collected.</p>
                    @endif
                </div>

                <div class="bg-surface border border-line rounded-2xl p-6 flex-1 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Damage Reports</div>
                        @can('damage.report')
                            @if ($order->status !== 'cancelled')
                                <button type="button" @click="$dispatch('open-panel', 'report-damage')" title="Report damage" aria-label="Report damage" class="w-8 h-8 rounded-lg bg-critical-soft text-critical flex items-center justify-center hover:bg-critical hover:text-white transition-colors">
                                    <x-nav-icon name="alert" class="w-3.5 h-3.5" />
                                </button>
                            @endif
                        @endcan
                    </div>
                    @forelse ($order->damageRecords as $damage)
                        <a href="{{ route('damage.show', $damage) }}" class="flex items-center justify-between gap-3 py-2 px-1.5 rounded-lg hover:bg-surface-2 transition-colors {{ ! $loop->last ? 'mb-1' : '' }}">
                            <span class="flex items-center gap-2.5 min-w-0">
                                <span class="w-8 h-8 rounded-lg bg-critical-soft text-critical flex items-center justify-center flex-none">
                                    <x-nav-icon name="alert" class="w-4 h-4" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-ink truncate">{{ $damage->damageType->name ?? '—' }}</span>
                                    @if ($damage->item_description)
                                        <span class="block text-xs text-ink-faint truncate">{{ $damage->item_description }}</span>
                                    @endif
                                    @if ($damage->resolution)
                                        <span class="block text-xs text-success truncate">Resolved: {{ ucfirst(str_replace('_', ' ', $damage->resolution->resolution_type)) }}</span>
                                    @endif
                                </span>
                            </span>
                            <x-status-pill :status="$damage->status" />
                        </a>
                    @empty
                        <p class="text-ink-faint text-sm">None reported.</p>
                    @endforelse
                </div>
            </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold mb-4">Line Items</div>
                @foreach ($order->packageLines as $line)
                    <div class="border border-line rounded-xl p-4 mb-3 last:mb-0">
                        <div class="mb-2">
                            <span class="font-semibold text-ink">{{ $line->package_name_snapshot }} × {{ $line->quantity }}</span>
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

                <div class="border-t border-line mt-5 pt-5 space-y-2 text-sm">
                    @if ($cycle)
                        <div class="flex justify-between text-ink-muted"><span>Subscription Plan{{ $order->collection?->subscription?->subscriptionPackage?->name ? ' ('.$order->collection->subscription->subscriptionPackage->name.')' : '' }}</span><span class="font-mono tabular-nums">GMD {{ number_format($cycle->monthly_price_snapshot, 2) }}</span></div>
                    @else
                        <div class="flex justify-between text-ink-muted"><span>Subtotal</span><span class="font-mono tabular-nums">GMD {{ number_format($order->subtotal, 2) }}</span></div>
                    @endif
                    @if ($order->discount > 0)
                        <div class="flex justify-between text-ink-muted"><span>Discount ({{ $order->discount_reason }})</span><span class="font-mono tabular-nums text-critical">− GMD {{ number_format($order->discount, 2) }}</span></div>
                    @endif
                    @if ($order->extra_charge > 0)
                        <div class="flex justify-between text-ink-muted"><span>Extra charge{{ $order->extra_charge_reason ? ' ('.$order->extra_charge_reason.')' : '' }}</span><span class="font-mono tabular-nums text-ink">+ GMD {{ number_format($order->extra_charge, 2) }}</span></div>
                    @endif
                    @if ($order->cycle_overage_charge > 0)
                        <div class="flex justify-between text-ink-muted"><span>Cycle overage charge</span><span class="font-mono tabular-nums text-ink">+ GMD {{ number_format($order->cycle_overage_charge, 2) }}</span></div>
                    @endif
                    <div class="flex justify-between items-baseline pt-3 mt-1 border-t border-line">
                        <span class="font-bold text-ink">{{ $cycle ? 'Cycle Total' : 'Total' }}</span>
                        <span class="font-mono tabular-nums text-accent-ink text-2xl font-bold">GMD {{ number_format($grandTotal, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
                    <div class="flex items-center gap-2.5">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Payments</div>
                        <x-status-pill :status="$paymentStatus" />
                    </div>
                    @can('orders.manage')
                        @if ($payableDue > 0)
                            <x-panel-trigger panel="record-payment">Record Payment</x-panel-trigger>
                        @endif
                    @endcan
                </div>

                @if ($order->order_source === 'subscription')
                    <div class="flex items-center justify-between gap-2 mb-4 p-3 rounded-xl bg-accent-soft text-sm">
                        <span class="inline-flex items-center gap-1.5 text-accent-ink font-semibold">
                            <x-nav-icon name="repeat" class="w-3.5 h-3.5" />
                            Subscription cycle
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
                    <div class="flex items-center justify-between py-2.5 border-b border-line last:border-0 text-sm">
                        <span class="text-ink-muted">{{ $payment->methodLabel() }}</span>
                        <x-status-pill :status="$payment->status" />
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($payment->amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No payments recorded.</p>
                @endforelse

                <div class="flex gap-6 mt-4 pt-4 border-t border-line">
                    <div class="flex-1">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-1">Amount Paid</div>
                        <div class="font-mono tabular-nums text-xl font-bold text-ink">GMD {{ number_format($combinedPaid, 2) }}</div>
                    </div>
                    @if ($combinedDue > 0)
                        <div class="flex-1">
                            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-1">Balance Due</div>
                            <div class="font-mono tabular-nums text-xl font-bold text-critical">GMD {{ number_format($combinedDue, 2) }}</div>
                        </div>
                    @endif
                </div>
            </div>

    </div>

    @can('orders.manage')
        @if ($payableDue > 0)
            <x-slide-panel name="record-payment" title="Record Payment" :error-fields="['amount', 'credit_applied', 'method']">
                <form method="POST" action="{{ route('orders.payments.record', $order) }}" class="space-y-4">
                    @csrf
                    <p class="text-sm text-ink-muted">Balance due: <span class="font-mono text-critical font-bold">GMD {{ number_format($payableDue, 2) }}</span>{{ $orderDue <= 0 && $cycle ? ' (subscription cycle)' : '' }}</p>

                    @include('orders._payment-fields', ['prefix' => 'record', 'amountRequired' => true, 'dueOverride' => $payableDue])

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
                    async refresh() {
                        // Best-effort -- if this fails, the panel still shows
                        // the last-known state and submit is still guarded
                        // server-side, so a network hiccup here never blocks
                        // the flow, only the freshness of what's displayed.
                        try {
                            const res = await fetch(@js(route('washingMachines.status')), { headers: { 'Accept': 'application/json' } });
                            if (res.ok) this.machines = await res.json();
                        } catch (e) {}
                    },
                    init() {
                        window.Echo.channel('orders').listen('.order.status-changed', (e) => {
                            const machine = this.machines.find(m => m.id === e.washingMachineId);
                            if (! machine) return;
                            machine.busy = e.toStatus === 'wash';
                            machine.currentOrderNumber = machine.busy ? e.orderNumber : null;
                        });
                    }
                }"
                x-on:open-panel.window="$event.detail === 'select-washing-machine' && ((open = true), refresh())"
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

    @can('orders.manage')
        @if ($nextIsCollection)
            <x-slide-panel name="record-collection" title="Record Collection" :error-fields="['collected_by_type', 'collected_by_name', 'collected_by_phone', 'payment_timing', 'amount', 'credit_applied', 'method', 'method_note']">
                <div x-data="{ collectedByType: @js(old('collected_by_type', 'customer')) }">
                    <form method="POST" action="{{ route('orders.advance', $order) }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label value="Collected by" />
                            <div class="flex gap-3 mt-1">
                                <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                                    <input type="radio" name="collected_by_type" value="customer" x-model="collectedByType" class="text-accent focus:ring-accent">
                                    Customer
                                </label>
                                <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                                    <input type="radio" name="collected_by_type" value="other" x-model="collectedByType" class="text-accent focus:ring-accent">
                                    Someone else
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('collected_by_type')" class="mt-1.5" />
                        </div>

                        <div x-show="collectedByType === 'other'" x-cloak class="space-y-4">
                            <div>
                                <x-input-label for="collected_by_name" value="Name" />
                                <x-text-input id="collected_by_name" name="collected_by_name" type="text" class="block w-full" value="{{ old('collected_by_name') }}" x-bind:required="collectedByType === 'other'" />
                                <x-input-error :messages="$errors->get('collected_by_name')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="collected_by_phone" value="Phone number" />
                                <x-text-input id="collected_by_phone" name="collected_by_phone" type="text" class="block w-full" value="{{ old('collected_by_phone') }}" placeholder="835190199" x-bind:required="collectedByType === 'other'" />
                                <x-input-error :messages="$errors->get('collected_by_phone')" class="mt-1.5" />
                            </div>
                        </div>


                        {{--
                            Payment at collection: Pay now / Pay later.
                            - Something owed and payment REQUIRED ($collectionPayableDue > 0:
                              a walk-in balance, an order's own extra charge, or the last
                              visit of a cycle) -> Pay now selected, Pay later greyed out.
                            - Something owed but optional (a non-final subscription visit
                              with the cycle fee open) -> both; Pay later hides the fields.
                            - Nothing owed -> "Paid in full", no choice.
                            The fields sit in a <fieldset> that's disabled for Pay later,
                            so nothing half-typed is sent. OrderController::advanceToCollection()
                            enforces the same rules server-side.
                        --}}
                        @php
                            // Everything that CAN be paid here (order + cycle); what MUST
                            // be paid is $collectionPayableDue (order, plus the cycle on the last visit).
                            $collectDue = round($collectionOrderDue + $collectionCycleDue, 2);
                            $mustPayAtCollection = $collectionPayableDue > 0;
                            $mustPayReason = match (true) {
                                $isFinalSubscriptionCollection => 'Last collection of this cycle, so everything still owed must be paid in full.',
                                $isSubscription => 'This order has its own extra charge, so it must be paid in full to collect.',
                                default => 'Must be paid in full to collect.',
                            };
                            $initialTiming = $mustPayAtCollection ? 'now' : old('payment_timing', 'now');
                        @endphp

                        <div class="border-t border-line pt-4 space-y-4" x-data="{ timing: @js($initialTiming) }">
                            @if ($collectDue > 0)
                                {{-- What's owed, broken down when both the order and the cycle owe something. --}}
                                <div class="rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm space-y-1">
                                    @if ($collectionOrderDue > 0)
                                        <div class="flex justify-between gap-3">
                                            <span class="text-ink-muted">{{ $isSubscription ? 'Order extra charge' : 'Order balance' }}</span>
                                            <span class="font-mono tabular-nums text-ink">GMD {{ number_format($collectionOrderDue, 2) }}</span>
                                        </div>
                                    @endif
                                    @if ($collectionCycleDue > 0)
                                        <div class="flex justify-between gap-3">
                                            <span class="text-ink-muted">Subscription cycle balance{{ $isFinalSubscriptionCollection ? '' : ' (optional now)' }}</span>
                                            <span class="font-mono tabular-nums text-ink">GMD {{ number_format($collectionCycleDue, 2) }}</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between gap-3 border-t border-line pt-1.5 font-semibold">
                                        <span class="text-ink">{{ $mustPayAtCollection ? 'Due to collect' : 'Owed' }}</span>
                                        <span class="font-mono tabular-nums text-critical">GMD {{ number_format($mustPayAtCollection ? $collectionPayableDue : $collectDue, 2) }}</span>
                                    </div>
                                    @if ($mustPayAtCollection && $collectDue > $collectionPayableDue)
                                        <p class="text-xs text-ink-faint">Up to GMD {{ number_format($collectDue, 2) }} can be paid now, including the rest of the cycle.</p>
                                    @endif
                                    @if ($collectionOrderDue > 0 && $collectionCycleDue > 0)
                                        <p class="text-xs text-ink-faint">One payment is split automatically: the order first, then the cycle.</p>
                                    @endif
                                </div>

                                <div>
                                    <x-input-label value="Payment" />
                                    {{-- Option cards: real radios (visually hidden, still keyboard-focusable). --}}
                                    <div class="grid grid-cols-2 gap-3 mt-1.5" role="radiogroup">
                                        <label class="group relative flex items-center gap-3 rounded-xl border border-line-strong bg-surface p-3 cursor-pointer shadow-sm transition hover:border-accent has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:ring-1 has-[:checked]:ring-accent has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent">
                                            <input type="radio" name="payment_timing" value="now" x-model="timing" class="sr-only">
                                            {{-- Green for paying now; solid once picked. --}}
                                            <span class="flex-none w-9 h-9 rounded-lg bg-emerald-500/15 text-emerald-600 flex items-center justify-center transition-colors group-has-[:checked]:bg-emerald-500 group-has-[:checked]:text-white" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                                            </span>
                                            <span class="min-w-0 pr-5">
                                                <span class="block text-sm font-semibold text-ink group-has-[:checked]:text-accent-ink">Pay now</span>
                                            </span>
                                            <span class="absolute top-2.5 right-2.5 w-4 h-4 rounded-full border-2 border-line-strong flex items-center justify-center transition-colors group-has-[:checked]:border-accent group-has-[:checked]:bg-accent" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-white opacity-0 group-has-[:checked]:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                            </span>
                                        </label>

                                        @if ($mustPayAtCollection)
                                            {{-- Locked: payment is required for this collection. --}}
                                            <label class="relative flex items-center gap-3 rounded-xl border border-dashed border-line-strong bg-surface-2 p-3 cursor-not-allowed" title="{{ $mustPayReason }}">
                                                <input type="radio" name="payment_timing" value="later" class="sr-only" disabled>
                                                <span class="flex-none w-9 h-9 rounded-lg bg-surface text-ink-faint flex items-center justify-center" aria-hidden="true">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-semibold text-ink-faint">Pay later</span>
                                                </span>
                                            </label>
                                        @else
                                            <label class="group relative flex items-center gap-3 rounded-xl border border-line-strong bg-surface p-3 cursor-pointer shadow-sm transition hover:border-accent has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:ring-1 has-[:checked]:ring-accent has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent">
                                                <input type="radio" name="payment_timing" value="later" x-model="timing" class="sr-only">
                                                {{-- Amber for deferring; solid once picked. --}}
                                                <span class="flex-none w-9 h-9 rounded-lg bg-amber-500/15 text-amber-600 flex items-center justify-center transition-colors group-has-[:checked]:bg-amber-500 group-has-[:checked]:text-white" aria-hidden="true">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                                </span>
                                                <span class="min-w-0 pr-5">
                                                    <span class="block text-sm font-semibold text-ink group-has-[:checked]:text-accent-ink">Pay later</span>
                                                </span>
                                                <span class="absolute top-2.5 right-2.5 w-4 h-4 rounded-full border-2 border-line-strong flex items-center justify-center transition-colors group-has-[:checked]:border-accent group-has-[:checked]:bg-accent" aria-hidden="true">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-white opacity-0 group-has-[:checked]:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                </span>
                                            </label>
                                        @endif
                                    </div>
                                    @if ($mustPayAtCollection)
                                        <p class="flex items-center gap-1.5 text-xs text-ink-muted mt-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 flex-none text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                                            {{ $mustPayReason }}
                                        </p>
                                    @endif
                                    <x-input-error :messages="$errors->get('payment_timing')" class="mt-1.5" />
                                </div>

                                <fieldset x-show="timing === 'now'" :disabled="timing !== 'now'" class="space-y-4">
                                    @include('orders._payment-fields', [
                                        'prefix' => 'collect',
                                        'amountRequired' => true,
                                        'dueOverride' => $collectDue,
                                    ])
                                </fieldset>

                                <p x-show="timing === 'later'" x-cloak class="rounded-lg bg-surface-2 px-3 py-2.5 text-sm text-ink-muted">
                                    No payment is recorded now. The cycle balance stays open and must be paid in full by the last collection.
                                </p>
                            @else
                                <p class="text-sm text-ink-muted">
                                    Payment: <span class="font-mono text-success font-bold">Paid in full</span>
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>Confirm Collection</x-primary-button>
                            <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                        </div>
                    </form>
                </div>
            </x-slide-panel>
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
