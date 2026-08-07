<x-app-layout>
    <x-slot name="header">{{ $customer->full_name }}</x-slot>

    <x-breadcrumbs :items="[
        ['label' => 'Customers', 'url' => route('customers.index')],
        ['label' => $customer->full_name, 'url' => null],
    ]" />

    <div class="relative bg-surface border border-line rounded-2xl p-6 mb-5 shadow-sm">
        @can('customers.manage')
            <button type="button" @click="$dispatch('open-panel', 'customer-edit')" class="absolute top-4 right-4 inline-flex items-center gap-1.5 bg-accent text-white hover:opacity-90 text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition-opacity">
                <x-nav-icon name="edit" class="w-3.5 h-3.5" />
                Edit
            </button>
        @endcan

        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-full bg-accent text-white flex items-center justify-center text-xl font-bold flex-none shadow-sm">
                    {{ Str::substr($customer->full_name, 0, 1) }}
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-lg font-bold text-ink">{{ $customer->full_name }}</h2>
                        <span class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $customer->customer_type === 'subscription' ? 'bg-accent-soft text-accent-ink' : 'bg-pill-bg text-pill-ink' }}">
                            {{ $customer->customer_type === 'subscription' ? 'Subscription' : 'Walk-in' }}
                        </span>
                    </div>
                    <div class="text-sm text-ink-muted mt-1 flex flex-wrap gap-x-4 gap-y-1">
                        <span class="font-mono">{{ $customer->phone }}</span>
                        @if ($customer->address)<span>{{ $customer->address }}</span>@endif
                    </div>
                    <div class="text-xs text-ink-faint mt-1">Customer since {{ $customer->created_at->format('M d, Y') }}</div>
                </div>
            </div>

            <div class="grid grid-cols-2 {{ $stats['balanceDue'] > 0 ? 'sm:grid-cols-5' : 'sm:grid-cols-4' }} gap-x-6 gap-y-4">
                <div class="flex items-start gap-2">
                    <span class="w-7 h-7 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center flex-none mt-0.5"><x-nav-icon name="wallet" class="w-3.5 h-3.5" /></span>
                    <div>
                        <div class="text-xs font-mono uppercase tracking-wide text-ink-faint">Store Credit</div>
                        <div class="text-lg font-bold text-ink tabular-nums">GMD {{ number_format($customer->store_credit_balance, 2) }}</div>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <span class="w-7 h-7 rounded-lg bg-pill-bg text-pill-ink flex items-center justify-center flex-none mt-0.5"><x-nav-icon name="clipboard" class="w-3.5 h-3.5" /></span>
                    <div>
                        <div class="text-xs font-mono uppercase tracking-wide text-ink-faint">Total Orders</div>
                        <div class="text-lg font-bold text-ink tabular-nums">{{ $stats['totalOrders'] }}</div>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <span class="w-7 h-7 rounded-lg bg-success-soft text-success flex items-center justify-center flex-none mt-0.5"><x-nav-icon name="analytics" class="w-3.5 h-3.5" /></span>
                    <div>
                        <div class="text-xs font-mono uppercase tracking-wide text-ink-faint">Lifetime Spend</div>
                        <div class="text-lg font-bold text-ink tabular-nums">GMD {{ number_format($stats['lifetimeSpend'], 2) }}</div>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <span class="w-7 h-7 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center flex-none mt-0.5"><x-nav-icon name="repeat" class="w-3.5 h-3.5" /></span>
                    <div>
                        <div class="text-xs font-mono uppercase tracking-wide text-ink-faint">Active Subs</div>
                        <div class="text-lg font-bold text-ink tabular-nums">{{ $stats['activeSubscriptions'] }}</div>
                    </div>
                </div>
                @if ($stats['balanceDue'] > 0)
                    <div class="flex items-start gap-2">
                        <span class="w-7 h-7 rounded-lg bg-critical-soft text-critical flex items-center justify-center flex-none mt-0.5"><x-nav-icon name="alert" class="w-3.5 h-3.5" /></span>
                        <div>
                            <div class="text-xs font-mono uppercase tracking-wide text-ink-faint">Balance Due</div>
                            <div class="text-lg font-bold text-critical tabular-nums">GMD {{ number_format($stats['balanceDue'], 2) }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div x-data="{ tab: 'overview' }">
        <div class="flex gap-1 bg-surface-2 rounded-full p-1 overflow-x-auto mb-5 w-fit max-w-full">
            @foreach (['overview' => 'Overview', 'packages' => 'Packages', 'orders' => 'Orders', 'payments' => 'Payments', 'damage' => 'Damage', 'credit' => 'Credit History'] as $key => $label)
                <button
                    type="button"
                    @click="tab = '{{ $key }}'"
                    class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition-colors"
                    :class="tab === '{{ $key }}' ? 'bg-accent text-white shadow-sm' : 'text-ink hover:text-accent-ink'"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div x-show="tab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            @php
                $cycleSubscriptions = $subscriptions->where('status', 'active')->filter(fn ($s) => $s->cycles->isNotEmpty());
            @endphp
            @if ($cycleSubscriptions->isNotEmpty())
            <div class="bg-surface border border-line rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center flex-none"><x-nav-icon name="repeat" class="w-3.5 h-3.5" /></span>
                    <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Current Cycle</div>
                </div>
                @foreach ($cycleSubscriptions as $subscription)
                    @php
                        $cycle = $subscription->cycles->first();
                        $cycleCollections = $subscription->collections->where('subscription_cycle_id', $cycle->id);
                        $completed = $cycleCollections->where('status', 'collected')->count();
                        $total = $cycleCollections->count();
                        $clothesUsed = $cycle->clothesCollected();
                        $clothesOver = $clothesUsed > $cycle->max_clothes_snapshot;
                        $clothesPct = min(100, round($clothesUsed / max(1, $cycle->max_clothes_snapshot) * 100));
                        $collectionsPct = $total > 0 ? min(100, round($completed / $total * 100)) : 0;
                        $collectionTypeEditable = $cycleCollections->where('status', 'collected')->isEmpty();
                    @endphp
                    <div class="py-3 first:pt-0 border-t border-line first:border-0 last:pb-0">
                        <div class="flex items-center justify-between mb-1">
                            <a href="{{ route('subscriptions.show', $subscription) }}" class="text-sm font-medium text-ink hover:text-accent-ink">{{ $subscription->subscriptionPackage->name }}</a>
                            @if ($cycle->isExhausted())
                                <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-full bg-accent-soft text-accent-ink">Renewal due</span>
                            @endif
                        </div>

                        @can('subscriptions.manage')
                            @if ($collectionTypeEditable)
                                <form method="POST" action="{{ route('subscriptions.collection-type.update', $subscription) }}" class="flex items-center gap-2 mb-2.5">
                                    @csrf
                                    @method('PUT')
                                    <select name="collection_type" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-xs focus:border-accent focus:ring-accent py-1">
                                        <option value="scheduled" @selected($subscription->collection_type === 'scheduled')>Scheduled</option>
                                        <option value="non_scheduled" @selected($subscription->collection_type === 'non_scheduled')>Non-scheduled</option>
                                    </select>
                                    <button type="submit" class="text-xs font-semibold text-accent-ink hover:underline">Save</button>
                                </form>
                                @error('collection_type') <p class="text-critical text-xs mb-2.5">{{ $message }}</p> @enderror
                            @else
                                <div class="text-xs text-ink-faint mb-2.5">{{ $subscription->collection_type === 'scheduled' ? 'Scheduled' : 'Non-scheduled' }}</div>
                            @endif
                        @else
                            <div class="text-xs text-ink-faint mb-2.5">{{ $subscription->collection_type === 'scheduled' ? 'Scheduled' : 'Non-scheduled' }}</div>
                        @endcan

                        @can('subscriptions.manage')
                            @if ($cycle->isExhausted())
                                <button type="button" @click="$dispatch('open-panel', 'renew-cycle-{{ $subscription->id }}')" class="w-full mb-2.5 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-accent text-white rounded-lg text-xs font-semibold hover:opacity-90 transition-opacity">
                                    <x-nav-icon name="repeat" class="w-3 h-3" />
                                    Renew
                                </button>
                                <x-renew-cycle-modal :subscription="$subscription" :packages="$subscriptionPackages" />
                            @endif
                        @endcan

                        <div class="space-y-2.5">
                            <div>
                                <div class="flex justify-between text-xs text-ink-muted mb-1">
                                    <span>Collections</span>
                                    <span class="font-mono tabular-nums">{{ $completed }} / {{ $total }}</span>
                                </div>
                                <div class="w-full h-1.5 bg-surface-2 rounded-full overflow-hidden">
                                    <div class="h-full bg-accent rounded-full transition-all" style="width: {{ $collectionsPct }}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs text-ink-muted mb-1">
                                    <span>Clothes</span>
                                    <span class="font-mono tabular-nums {{ $clothesOver ? 'text-critical font-semibold' : '' }}">
                                        {{ $clothesUsed }} / {{ $cycle->max_clothes_snapshot }}
                                        @if ($clothesOver) (+{{ $clothesUsed - $cycle->max_clothes_snapshot }} over) @endif
                                    </span>
                                </div>
                                <div class="w-full h-1.5 bg-surface-2 rounded-full overflow-hidden">
                                    <div class="h-full {{ $clothesOver ? 'bg-critical' : 'bg-accent' }} rounded-full transition-all" style="width: {{ $clothesPct }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            <div class="bg-surface border border-line rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-pill-bg text-pill-ink flex items-center justify-center flex-none"><x-nav-icon name="clipboard" class="w-3.5 h-3.5" /></span>
                        <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Recent Orders</div>
                    </div>
                    <button type="button" @click="tab = 'orders'" class="text-xs text-accent-ink hover:underline">View All</button>
                </div>
                @forelse ($recentOrders as $order)
                    <div class="flex items-center justify-between py-2 border-b border-line last:border-0 text-sm">
                        <a href="{{ route('orders.show', $order) }}" class="font-mono text-ink hover:text-accent-ink">{{ $order->order_number }}</a>
                        <x-status-pill :status="$order->status" />
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($order->total_amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-ink-faint text-sm">No orders yet.</p>
                @endforelse
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 rounded-lg bg-success-soft text-success flex items-center justify-center flex-none"><x-nav-icon name="zap" class="w-3.5 h-3.5" /></span>
                    <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Quick Actions</div>
                </div>
                <div class="flex flex-col gap-1.5">
                    @can('terminal.use')
                        @if ($hasOpenSubscriptionCycle)
                            <button type="button" @click="$dispatch('open-panel', 'new-order-choice')" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-ink hover:bg-accent-soft/50 hover:text-accent-ink transition-colors text-left w-full">
                                <span class="w-8 h-8 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center flex-none"><x-nav-icon name="clipboard" class="w-4 h-4" /></span>
                                <span class="flex-1">Create Order</span>
                                <span class="text-ink-faint">&rarr;</span>
                            </button>

                            <div
                                x-data="{ open: false }"
                                x-on:open-panel.window="$event.detail === 'new-order-choice' && (open = true)"
                                x-on:close-panel.window="$event.detail === 'new-order-choice' && (open = false)"
                                x-on:keydown.escape.window="open = false"
                            >
                                <div x-show="open" x-cloak class="fixed inset-0 bg-ink/40 backdrop-blur-sm z-40 flex items-center justify-center p-4" x-transition.opacity @click.self="open = false">
                                    <div class="bg-surface border border-line rounded-2xl shadow-xl w-full max-w-sm p-6" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="font-semibold text-ink">Create Order</h3>
                                            <button type="button" @click="open = false" class="text-ink-faint hover:text-ink" aria-label="Close">✕</button>
                                        </div>
                                        <p class="text-sm text-ink-muted mb-4">This customer has a subscription cycle running. How should this order be recorded?</p>
                                        <div class="space-y-2">
                                            <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-line-strong text-sm font-medium text-ink hover:border-accent hover:bg-accent-soft transition-colors">
                                                <span class="w-8 h-8 rounded-lg bg-success-soft text-success flex items-center justify-center flex-none"><x-nav-icon name="repeat" class="w-4 h-4" /></span>
                                                <span class="flex-1">
                                                    <span class="block">Use Subscription</span>
                                                    <span class="block text-xs text-ink-faint font-normal">Record against their current plan</span>
                                                </span>
                                            </a>
                                            <a href="{{ route('orders.create', ['customer' => $customer->id, 'mode' => 'walk_in']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-line-strong text-sm font-medium text-ink hover:border-accent hover:bg-accent-soft transition-colors">
                                                <span class="w-8 h-8 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center flex-none"><x-nav-icon name="clipboard" class="w-4 h-4" /></span>
                                                <span class="flex-1">
                                                    <span class="block">Walk-in Order</span>
                                                    <span class="block text-xs text-ink-faint font-normal">A separate one-off order, paid on its own</span>
                                                </span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-ink hover:bg-accent-soft/50 hover:text-accent-ink transition-colors">
                                <span class="w-8 h-8 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center flex-none"><x-nav-icon name="clipboard" class="w-4 h-4" /></span>
                                <span class="flex-1">Create Order</span>
                                <span class="text-ink-faint">&rarr;</span>
                            </a>
                        @endif
                    @endcan
                    @can('subscriptions.manage')
                        @if ($customer->customer_type === 'subscription')
                            <button type="button" @click="$dispatch('open-panel', 'new-subscription')" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-ink hover:bg-accent-soft/50 hover:text-accent-ink transition-colors text-left">
                                <span class="w-8 h-8 rounded-lg bg-success-soft text-success flex items-center justify-center flex-none"><x-nav-icon name="repeat" class="w-4 h-4" /></span>
                                <span class="flex-1">New Subscription</span>
                                <span class="text-ink-faint">&rarr;</span>
                            </button>
                        @endif
                    @endcan
                </div>
            </div>

            @can('subscriptions.manage')
                @php $manageableSubscriptions = $subscriptions->whereIn('status', ['active', 'paused']); @endphp
                @if ($manageableSubscriptions->isNotEmpty())
                    <div class="bg-surface border border-line rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-7 h-7 rounded-lg bg-pill-bg text-pill-ink flex items-center justify-center flex-none"><x-nav-icon name="gear" class="w-3.5 h-3.5" /></span>
                            <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Manage Subscription</div>
                        </div>
                        @foreach ($manageableSubscriptions as $subscription)
                            <div class="py-2.5 border-b border-line last:border-0">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <div class="text-ink text-sm">{{ $subscription->subscriptionPackage->name }}</div>
                                        <x-status-pill :status="$subscription->status" />
                                    </div>
                                    <a href="{{ route('subscriptions.show', $subscription) }}" class="text-accent-ink text-xs hover:underline">View</a>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($subscription->status === 'active')
                                        <form method="POST" action="{{ route('subscriptions.pause', $subscription) }}" class="flex-1">
                                            @csrf
                                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-pill-bg text-pill-ink rounded-lg text-xs font-semibold hover:opacity-90 transition-opacity">
                                                <x-nav-icon name="pause" class="w-3 h-3" />
                                                Pause
                                            </button>
                                        </form>
                                    @elseif ($subscription->status === 'paused')
                                        <form method="POST" action="{{ route('subscriptions.resume', $subscription) }}" class="flex-1">
                                            @csrf
                                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-success-soft text-success rounded-lg text-xs font-semibold hover:opacity-90 transition-opacity">
                                                <x-nav-icon name="play" class="w-3 h-3" />
                                                Resume
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('subscriptions.cancel', $subscription) }}" class="flex-1" onsubmit="return confirm('Cancel this subscription? This cannot be undone.')">
                                        @csrf
                                        <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-critical text-white rounded-lg text-xs font-semibold hover:opacity-90 transition-opacity">
                                            <x-nav-icon name="x" class="w-3 h-3" />
                                            Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endcan

            <div class="space-y-5">
                <div class="bg-surface border border-line rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center flex-none"><x-nav-icon name="wallet" class="w-3.5 h-3.5" /></span>
                            <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Payment Summary</div>
                        </div>
                        <button type="button" @click="tab = 'payments'" class="text-xs text-accent-ink hover:underline">View All</button>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Lifetime Spend</dt>
                            <dd class="font-mono text-ink">GMD {{ number_format($stats['lifetimeSpend'], 2) }}</dd>
                        </div>
                        @foreach ($currentCycles as $cycle)
                            @php
                                // isPaid() itself just checks balanceDue() <= 0
                                // -- compute the due amount once and derive
                                // both from it instead of two live queries.
                                $cycleDue = $cycle->balanceDue();
                                $cyclePaid = $cycleDue <= 0;
                            @endphp
                            <div class="flex justify-between">
                                <dt class="text-ink-muted">{{ $cycle->subscription->subscriptionPackage->name }} ({{ $cycle->starts_on->format('M Y') }})</dt>
                                <dd class="font-mono font-semibold {{ $cyclePaid ? 'text-success' : 'text-critical' }}">
                                    {{ $cyclePaid ? 'Paid' : 'GMD '.number_format($cycleDue, 2).' due' }}
                                </dd>
                            </div>
                        @endforeach
                        @if ($stats['balanceDue'] > 0)
                            <div class="flex justify-between font-semibold">
                                <dt class="text-critical">Balance Due</dt>
                                <dd class="font-mono text-critical">GMD {{ number_format($stats['balanceDue'], 2) }}</dd>
                            </div>
                        @endif
                        @if ($lastPayment)
                            <div class="flex justify-between">
                                <dt class="text-ink-muted">Last Payment</dt>
                                <dd class="text-right">
                                    <span class="font-mono text-ink">GMD {{ number_format($lastPayment->amount, 2) }}</span>
                                    <div class="text-ink-faint text-xs">{{ $lastPayment->created_at->format('Y-m-d') }}</div>
                                </dd>
                            </div>
                        @endif
                        @if ($nextCollection)
                            <div class="flex justify-between">
                                <dt class="text-ink-muted">Next Collection</dt>
                                <dd class="font-mono text-accent-ink">{{ $nextCollection->scheduled_date?->format('Y-m-d') ?? 'Anytime' }}</dd>
                            </div>
                        @endif
                    </dl>
                    @can('orders.manage')
                        @if ($unpaidCycles->isNotEmpty() || $unpaidOrders->isNotEmpty())
                            <div class="mt-4 pt-4 border-t border-line space-y-2">
                                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint">Open Balances</div>
                                @foreach ($unpaidCycles as $cycle)
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <div class="min-w-0">
                                            <span class="text-ink truncate block">{{ $cycle->subscription->subscriptionPackage->name }}</span>
                                            <span class="text-xs text-ink-faint">{{ $cycle->starts_on->format('M Y') }} subscription fee</span>
                                        </div>
                                        <div class="flex items-center gap-2 flex-none">
                                            <span class="font-mono tabular-nums text-critical">GMD {{ number_format($cycle->balanceDue(), 2) }}</span>
                                            <button type="button" @click="$dispatch('open-panel', 'record-cycle-payment-{{ $cycle->id }}')" class="px-2.5 py-1 bg-critical-soft text-critical rounded-lg text-xs font-semibold hover:opacity-90">
                                                Pay
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                                @foreach ($unpaidOrders as $order)
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <div class="min-w-0">
                                            <a href="{{ route('orders.show', $order) }}" class="font-mono text-ink hover:text-accent-ink truncate block">{{ $order->order_number }}</a>
                                            <span class="text-xs text-ink-faint">{{ $order->created_at->format('Y-m-d') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 flex-none">
                                            <span class="font-mono tabular-nums text-critical">GMD {{ number_format($order->balanceDue(), 2) }}</span>
                                            <button type="button" @click="$dispatch('open-panel', 'record-payment-{{ $order->id }}')" class="px-2.5 py-1 bg-critical-soft text-critical rounded-lg text-xs font-semibold hover:opacity-90">
                                                Pay
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endcan
                </div>

                @if ($customer->notes)
                    <div class="bg-surface border border-line rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-pill-bg text-pill-ink flex items-center justify-center flex-none"><x-nav-icon name="log" class="w-3.5 h-3.5" /></span>
                            <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Customer Notes</div>
                        </div>
                        <p class="text-sm text-ink">{{ $customer->notes }}</p>
                        <p class="text-xs text-ink-faint mt-2">Updated {{ $customer->updated_at->format('Y-m-d') }}</p>
                    </div>
                @endif
            </div>

            <div class="bg-surface border border-line rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 rounded-lg bg-pill-bg text-pill-ink flex items-center justify-center flex-none"><x-nav-icon name="user" class="w-3.5 h-3.5" /></span>
                    <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Customer Information</div>
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2 text-ink-muted">
                            <span class="w-6 h-6 rounded-md bg-pill-bg text-pill-ink flex items-center justify-center flex-none"><x-nav-icon name="phone" class="w-3 h-3" /></span>
                            Phone
                        </dt>
                        <dd class="font-mono text-ink">{{ $customer->phone }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2 text-ink-muted">
                            <span class="w-6 h-6 rounded-md bg-pill-bg text-pill-ink flex items-center justify-center flex-none"><x-nav-icon name="mail" class="w-3 h-3" /></span>
                            Email
                        </dt>
                        <dd class="text-ink">{{ $customer->email ?: '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2 text-ink-muted">
                            <span class="w-6 h-6 rounded-md bg-pill-bg text-pill-ink flex items-center justify-center flex-none"><x-nav-icon name="map-pin" class="w-3 h-3" /></span>
                            Address
                        </dt>
                        <dd class="text-ink text-right">{{ $customer->address ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            @if ($upcomingCollections->isNotEmpty())
                <div class="lg:col-span-3 bg-surface border border-line rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-pill-bg text-pill-ink flex items-center justify-center flex-none"><x-nav-icon name="truck" class="w-3.5 h-3.5" /></span>
                            <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Collection Schedule</div>
                        </div>
                        <a href="{{ route('collections.index') }}" class="text-xs text-accent-ink hover:underline">View All Collections</a>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach ($upcomingCollections as $collection)
                            <div class="bg-surface-2 rounded-xl p-3 text-sm">
                                <div class="font-mono text-xs text-ink-faint uppercase">{{ $collection->scheduled_date?->format('D, M d') ?? 'Anytime' }}</div>
                                <div class="text-ink mt-1">{{ $collection->subscription->subscriptionPackage->name }}</div>
                                <x-status-pill :status="$collection->status" />
                                @if ($collection->status === 'scheduled')
                                    @can('collections.manage')
                                        <div class="flex items-center gap-1 mt-2 pt-2 border-t border-line">
                                            @if ($collection->subscription->status === 'active')
                                                <a href="{{ route('collections.collect', $collection) }}" class="flex-1 inline-flex items-center justify-center gap-0.5 bg-success-soft text-success text-[11px] font-semibold px-1 py-1 rounded-lg">
                                                    <x-nav-icon name="truck" class="w-3 h-3" />
                                                    Collect
                                                </a>
                                                <button type="button" @click="$dispatch('open-panel', 'cancel-{{ $collection->id }}')" class="flex-1 inline-flex items-center justify-center gap-0.5 bg-critical-soft text-critical text-[11px] font-semibold px-1 py-1 rounded-lg">
                                                    <x-nav-icon name="x" class="w-3 h-3" />
                                                    Skip
                                                </button>
                                                <form method="POST" action="{{ route('collections.cancel', $collection) }}" onsubmit="return confirm('Cancel this collection outright? No order will be created.')" class="flex-1">
                                                    @csrf
                                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-0.5 bg-surface-2 text-ink-faint text-[11px] font-semibold px-1 py-1 rounded-lg hover:bg-critical hover:text-white transition-colors">
                                                        <x-nav-icon name="trash" class="w-3 h-3" />
                                                        Cancel
                                                    </button>
                                                </form>
                                            @else
                                                <span title="Subscription is paused" class="flex-1 inline-flex items-center justify-center gap-0.5 bg-surface-2 text-ink-faint text-[11px] font-semibold px-1 py-1 rounded-lg cursor-not-allowed">
                                                    <x-nav-icon name="truck" class="w-3 h-3" />
                                                    Collect
                                                </span>
                                                <span title="Subscription is paused" class="flex-1 inline-flex items-center justify-center gap-0.5 bg-surface-2 text-ink-faint text-[11px] font-semibold px-1 py-1 rounded-lg cursor-not-allowed">
                                                    <x-nav-icon name="x" class="w-3 h-3" />
                                                    Skip
                                                </span>
                                                <span title="Subscription is paused" class="flex-1 inline-flex items-center justify-center gap-0.5 bg-surface-2 text-ink-faint text-[11px] font-semibold px-1 py-1 rounded-lg cursor-not-allowed">
                                                    <x-nav-icon name="trash" class="w-3 h-3" />
                                                    Cancel
                                                </span>
                                            @endif
                                        </div>
                                    @endcan
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div x-show="tab === 'packages'" x-cloak class="bg-surface border border-line rounded-2xl overflow-hidden shadow-sm hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Package</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Allowance</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Since</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscriptions as $subscription)
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3 text-ink font-medium">{{ $subscription->subscriptionPackage->name }}</td>
                            <td class="px-4 py-3 font-mono text-ink-muted">{{ $subscription->subscriptionPackage->clothes_allowance }} items</td>
                            <td class="px-4 py-3">
                                <x-status-pill :status="$subscription->status" />
                                @if ($subscription->status === 'active' && $subscription->cycles->first()?->isExhausted())
                                    <span class="ml-1 inline-flex items-center font-mono text-xs font-semibold px-2 py-0.5 rounded-full bg-accent-soft text-accent-ink">Renewal due</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $subscription->start_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('subscriptions.show', $subscription) }}" class="text-accent-ink text-xs hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint text-sm">No packages yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div x-show="tab === 'packages'" x-cloak class="md:hidden space-y-3">
            @forelse ($subscriptions as $subscription)
                <a href="{{ route('subscriptions.show', $subscription) }}" class="block bg-surface border border-line rounded-2xl p-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-medium text-ink">{{ $subscription->subscriptionPackage->name }}</span>
                        <div class="flex items-center gap-1">
                            <x-status-pill :status="$subscription->status" />
                            @if ($subscription->status === 'active' && $subscription->cycles->first()?->isExhausted())
                                <span class="inline-flex items-center font-mono text-xs font-semibold px-2 py-0.5 rounded-full bg-accent-soft text-accent-ink">Renewal due</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm text-ink-muted">
                        <span>{{ $subscription->subscriptionPackage->clothes_allowance }} items</span>
                        <span class="font-mono text-xs">{{ $subscription->start_date->format('Y-m-d') }}</span>
                    </div>
                </a>
            @empty
                <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No packages yet.</div>
            @endforelse
        </div>

        <div x-show="tab === 'orders'" x-cloak class="bg-surface border border-line rounded-2xl overflow-hidden shadow-sm hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Order</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Total</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3"><a href="{{ route('orders.show', $order) }}" class="font-mono font-medium text-ink hover:text-accent-ink">{{ $order->order_number }}</a></td>
                            <td class="px-4 py-3"><x-status-pill :status="$order->status" /></td>
                            <td class="px-4 py-3 font-mono tabular-nums text-ink">GMD {{ number_format($order->total_amount, 2) }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-ink-faint text-sm">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div x-show="tab === 'orders'" x-cloak class="md:hidden space-y-3">
            @forelse ($orders as $order)
                <a href="{{ route('orders.show', $order) }}" class="block bg-surface border border-line rounded-2xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-mono font-medium text-ink">{{ $order->order_number }}</span>
                        <x-status-pill :status="$order->status" />
                    </div>
                    <div class="flex items-center justify-between text-sm text-ink-muted">
                        <span class="font-mono text-xs">{{ $order->created_at->format('Y-m-d H:i') }}</span>
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($order->total_amount, 2) }}</span>
                    </div>
                </a>
            @empty
                <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No orders yet.</div>
            @endforelse
        </div>

        <div x-show="tab === 'payments'" x-cloak class="bg-surface border border-line rounded-2xl overflow-hidden shadow-sm hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Date</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Source</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Method</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">
                                @if ($payment->order)
                                    <a href="{{ route('orders.show', $payment->order) }}" class="font-mono text-accent-ink hover:underline">{{ $payment->order->order_number }}</a>
                                @else
                                    <span class="text-ink-faint">Subscription</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-muted">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                            <td class="px-4 py-3"><x-status-pill :status="$payment->status" /></td>
                            <td class="px-4 py-3 font-mono tabular-nums text-ink">GMD {{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint text-sm">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div x-show="tab === 'payments'" x-cloak class="md:hidden space-y-3">
            @forelse ($payments as $payment)
                <div class="bg-surface border border-line rounded-2xl p-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-ink">
                            @if ($payment->order)
                                <a href="{{ route('orders.show', $payment->order) }}" class="font-mono text-accent-ink hover:underline">{{ $payment->order->order_number }}</a>
                            @else
                                Subscription
                            @endif
                        </span>
                        <x-status-pill :status="$payment->status" />
                    </div>
                    <div class="flex items-center justify-between text-sm text-ink-muted">
                        <span>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</span>
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($payment->amount, 2) }}</span>
                    </div>
                </div>
            @empty
                <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No payments yet.</div>
            @endforelse
        </div>

        <div x-show="tab === 'damage'" x-cloak class="bg-surface border border-line rounded-2xl overflow-hidden shadow-sm hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Reported</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Order</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Type</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($damageRecords as $damage)
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $damage->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3"><a href="{{ route('damage.show', $damage) }}" class="font-mono text-accent-ink hover:underline">{{ $damage->order->order_number }}</a></td>
                            <td class="px-4 py-3 text-ink-muted">{{ $damage->damageType->name ?? '—' }}</td>
                            <td class="px-4 py-3"><x-status-pill :status="$damage->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-ink-faint text-sm">No damage reports.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div x-show="tab === 'damage'" x-cloak class="md:hidden space-y-3">
            @forelse ($damageRecords as $damage)
                <a href="{{ route('damage.show', $damage) }}" class="block bg-surface border border-line rounded-2xl p-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono text-accent-ink">{{ $damage->order->order_number }}</span>
                        <x-status-pill :status="$damage->status" />
                    </div>
                    <div class="flex items-center justify-between text-sm text-ink-muted">
                        <span>{{ $damage->damageType->name ?? '—' }}</span>
                        <span class="font-mono text-xs">{{ $damage->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                </a>
            @empty
                <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No damage reports.</div>
            @endforelse
        </div>

        <div x-show="tab === 'credit'" x-cloak class="bg-surface border border-line rounded-2xl overflow-hidden shadow-sm hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Date</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Type</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Notes</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Amount</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($creditTransactions as $credit)
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $credit->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $credit->type === 'credit' ? 'bg-success-soft text-success' : 'bg-critical-soft text-critical' }}">
                                    {{ ucfirst($credit->type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-muted">{{ $credit->notes ?: ucfirst(str_replace('_', ' ', $credit->reference_type ?? '—')) }}</td>
                            <td class="px-4 py-3 font-mono tabular-nums text-ink">{{ $credit->type === 'credit' ? '+' : '−' }} GMD {{ number_format($credit->amount, 2) }}</td>
                            <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">GMD {{ number_format($credit->balance_after, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint text-sm">No credit activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div x-show="tab === 'credit'" x-cloak class="md:hidden space-y-3">
            @forelse ($creditTransactions as $credit)
                <div class="bg-surface border border-line rounded-2xl p-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $credit->type === 'credit' ? 'bg-success-soft text-success' : 'bg-critical-soft text-critical' }}">
                            {{ ucfirst($credit->type) }}
                        </span>
                        <span class="font-mono tabular-nums text-ink">{{ $credit->type === 'credit' ? '+' : '−' }} GMD {{ number_format($credit->amount, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-ink-muted">
                        <span>{{ $credit->notes ?: ucfirst(str_replace('_', ' ', $credit->reference_type ?? '—')) }}</span>
                        <span class="font-mono text-xs">{{ $credit->created_at->format('Y-m-d') }}</span>
                    </div>
                </div>
            @empty
                <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No credit activity yet.</div>
            @endforelse
        </div>

        @can('orders.manage')
            @foreach ($unpaidCycles as $cycle)
                @php $cycleDue = $cycle->balanceDue(); @endphp
                <x-slide-panel name="record-cycle-payment-{{ $cycle->id }}" title="Record Payment" :open="$errors->any() && (int) old('subscription_cycle_id') === $cycle->id">
                    <form method="POST" action="{{ route('subscriptionCycles.payments.record', $cycle) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="subscription_cycle_id" value="{{ $cycle->id }}">
                        <p class="text-sm text-ink-muted">
                            {{ $cycle->subscription->subscriptionPackage->name }} — {{ $cycle->starts_on->format('M Y') }} —
                            balance due: <span class="font-mono text-ink font-semibold">GMD {{ number_format($cycleDue, 2) }}</span>
                        </p>

                        @if ($customer->store_credit_balance > 0)
                            <div>
                                <x-input-label for="cyclecp_credit_applied_{{ $cycle->id }}" value="Apply store credit" />
                                <x-text-input id="cyclecp_credit_applied_{{ $cycle->id }}" name="credit_applied" type="number" step="0.01" min="0" max="{{ min($customer->store_credit_balance, $cycleDue) }}" class="block w-full" />
                                <p class="text-xs text-ink-faint mt-1">Of GMD {{ number_format($customer->store_credit_balance, 2) }} available.</p>
                                @if ((int) old('subscription_cycle_id') === $cycle->id)
                                    <x-input-error :messages="$errors->get('credit_applied')" class="mt-1.5" />
                                @endif
                            </div>
                        @endif

                        <div>
                            <x-input-label for="cyclecp_amount_{{ $cycle->id }}" value="Amount collected (GMD)" />
                            <x-text-input id="cyclecp_amount_{{ $cycle->id }}" name="amount" type="number" step="0.01" min="0" class="block w-full" required />
                            @if ((int) old('subscription_cycle_id') === $cycle->id)
                                <x-input-error :messages="$errors->get('amount')" class="mt-1.5" />
                            @endif
                        </div>

                        <div>
                            <x-input-label for="cyclecp_method_{{ $cycle->id }}" value="Method" />
                            <select id="cyclecp_method_{{ $cycle->id }}" name="method" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mixed">Mixed</option>
                            </select>
                            @if ((int) old('subscription_cycle_id') === $cycle->id)
                                <x-input-error :messages="$errors->get('method')" class="mt-1.5" />
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>Record Payment</x-primary-button>
                            <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                        </div>
                    </form>
                </x-slide-panel>
            @endforeach
        @endcan

        @can('orders.manage')
            @foreach ($unpaidOrders as $order)
                @php $orderDue = $order->balanceDue(); @endphp
                <x-slide-panel name="record-payment-{{ $order->id }}" title="Record Payment" :open="$errors->any() && (int) old('order_id') === $order->id">
                    <form method="POST" action="{{ route('orders.payments.record', $order) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                        <p class="text-sm text-ink-muted">
                            Order <a href="{{ route('orders.show', $order) }}" class="font-mono text-accent-ink hover:underline">{{ $order->order_number }}</a> —
                            balance due: <span class="font-mono text-ink font-semibold">GMD {{ number_format($orderDue, 2) }}</span>
                        </p>

                        @if ($customer->store_credit_balance > 0)
                            <div>
                                <x-input-label for="cp_credit_applied_{{ $order->id }}" value="Apply store credit" />
                                <x-text-input id="cp_credit_applied_{{ $order->id }}" name="credit_applied" type="number" step="0.01" min="0" max="{{ min($customer->store_credit_balance, $orderDue) }}" class="block w-full" />
                                <p class="text-xs text-ink-faint mt-1">Of GMD {{ number_format($customer->store_credit_balance, 2) }} available.</p>
                                @if ((int) old('order_id') === $order->id)
                                    <x-input-error :messages="$errors->get('credit_applied')" class="mt-1.5" />
                                @endif
                            </div>
                        @endif

                        <div>
                            <x-input-label for="cp_amount_{{ $order->id }}" value="Amount collected (GMD)" />
                            <x-text-input id="cp_amount_{{ $order->id }}" name="amount" type="number" step="0.01" min="0" class="block w-full" required />
                            @if ((int) old('order_id') === $order->id)
                                <x-input-error :messages="$errors->get('amount')" class="mt-1.5" />
                            @endif
                        </div>

                        <div>
                            <x-input-label for="cp_method_{{ $order->id }}" value="Method" />
                            <select id="cp_method_{{ $order->id }}" name="method" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mixed">Mixed</option>
                            </select>
                            @if ((int) old('order_id') === $order->id)
                                <x-input-error :messages="$errors->get('method')" class="mt-1.5" />
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>Record Payment</x-primary-button>
                            <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                        </div>
                    </form>
                </x-slide-panel>
            @endforeach
        @endcan

        @can('collections.manage')
            @foreach ($upcomingCollections as $collection)
                @if ($collection->status === 'scheduled')
                    <x-slide-panel name="cancel-{{ $collection->id }}" title="Skip Collection" :open="$errors->any() && (int) old('cancel_collection_id') === $collection->id">
                        <form method="POST" action="{{ route('collections.cancel', $collection) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="cancel_collection_id" value="{{ $collection->id }}">
                            <p class="text-sm text-ink-muted">
                                Cancels the <span class="font-mono text-ink">{{ $collection->scheduled_date?->format('Y-m-d') ?? 'anytime' }}</span> pickup and folds it into another one — that visit will cover both.
                            </p>
                            @php
                                $combineOptions = $collection->subscription->collections()
                                    ->where('status', 'scheduled')
                                    ->where('id', '!=', $collection->id)
                                    ->when($collection->subscription_cycle_id, fn ($q) => $q->where('subscription_cycle_id', $collection->subscription_cycle_id))
                                    ->orderBy('scheduled_date')
                                    ->get();
                            @endphp
                            <div>
                                <x-input-label for="cust_combine_into_{{ $collection->id }}" value="Combine into" />
                                <select id="cust_combine_into_{{ $collection->id }}" name="combined_into_collection_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                                    <option value="">Select a pickup…</option>
                                    @foreach ($combineOptions as $option)
                                        <option value="{{ $option->id }}">{{ $option->scheduled_date?->format('Y-m-d') ?? 'Anytime (#'.$option->id.')' }}</option>
                                    @endforeach
                                </select>
                                @if ((int) old('cancel_collection_id') === $collection->id)
                                    <x-input-error :messages="$errors->get('combined_into_collection_id')" class="mt-1.5" />
                                @endif
                            </div>
                            <div>
                                <x-input-label for="cust_cancel_reason_{{ $collection->id }}" value="Reason" />
                                <textarea id="cust_cancel_reason_{{ $collection->id }}" name="reason" rows="2" placeholder="e.g. Customer travelling" class="w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm" required></textarea>
                                @if ((int) old('cancel_collection_id') === $collection->id)
                                    <x-input-error :messages="$errors->get('reason')" class="mt-1.5" />
                                @endif
                            </div>
                            @if ($combineOptions->isEmpty())
                                <p class="text-xs text-critical">No other scheduled pickup to combine this into{{ $collection->subscription_cycle_id ? ' in this cycle' : '' }}.</p>
                            @endif
                            <div class="flex items-center gap-3">
                                <x-primary-button>Skip Collection</x-primary-button>
                                <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Close</button>
                            </div>
                        </form>
                    </x-slide-panel>
                @endif
            @endforeach
        @endcan

        @can('subscriptions.manage')
            @if ($customer->customer_type === 'subscription')
                <x-slide-panel name="new-subscription" title="New Subscription" :error-fields="['subscription_package_id', 'start_date', 'end_date', 'customer_id', 'collections_per_month', 'max_clothes_per_cycle', 'collection_type']">
                    <form
                        method="POST"
                        action="{{ route('subscriptions.store') }}"
                        class="space-y-4"
                        x-data="{
                            packages: {{ Js::from($subscriptionPackages->mapWithKeys(fn ($p) => [$p->id => [
                                'collections_per_month' => $p->collections_per_month,
                                'max_clothes_per_cycle' => $p->max_clothes_per_cycle,
                            ]])) }},
                            applyDefaults(id) {
                                const pkg = this.packages[id];
                                if (pkg) {
                                    this.$refs.collectionsPerMonth.value = pkg.collections_per_month;
                                    this.$refs.maxClothesPerCycle.value = pkg.max_clothes_per_cycle;
                                }
                            },
                        }"
                    >
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                        <input type="hidden" name="return_to_profile" value="1">

                        <div>
                            <x-input-label for="ns_package" value="Package" />
                            <select id="ns_package" name="subscription_package_id" @change="applyDefaults($event.target.value)" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                                <option value="">Select a package…</option>
                                @foreach ($subscriptionPackages as $package)
                                    <option value="{{ $package->id }}" @selected(old('subscription_package_id') == $package->id)>
                                        {{ $package->name }} — GMD {{ number_format($package->monthly_price, 2) }}/mo, {{ $package->clothes_allowance }} items
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('subscription_package_id')" class="mt-1.5" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="ns_start_date" value="Start date" />
                                <x-text-input id="ns_start_date" name="start_date" type="date" class="block w-full" value="{{ old('start_date', now()->format('Y-m-d')) }}" required />
                                <x-input-error :messages="$errors->get('start_date')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="ns_end_date" value="End date (optional)" />
                                <x-text-input id="ns_end_date" name="end_date" type="date" class="block w-full" value="{{ old('end_date') }}" />
                                <x-input-error :messages="$errors->get('end_date')" class="mt-1.5" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="ns_collections_per_month" value="Number of collections" />
                                <x-text-input x-ref="collectionsPerMonth" id="ns_collections_per_month" name="collections_per_month" type="number" min="1" max="28" class="block w-full" value="{{ old('collections_per_month') }}" required />
                                <x-input-error :messages="$errors->get('collections_per_month')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="ns_max_clothes_per_cycle" value="Max clothes per cycle" />
                                <x-text-input x-ref="maxClothesPerCycle" id="ns_max_clothes_per_cycle" name="max_clothes_per_cycle" type="number" min="1" class="block w-full" value="{{ old('max_clothes_per_cycle') }}" required />
                                <x-input-error :messages="$errors->get('max_clothes_per_cycle')" class="mt-1.5" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Collection type" />
                            <div class="flex gap-3 mt-1">
                                <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                                    <input type="radio" name="collection_type" value="scheduled" @checked(old('collection_type', 'scheduled') === 'scheduled') class="text-accent focus:ring-accent">
                                    Scheduled
                                </label>
                                <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                                    <input type="radio" name="collection_type" value="non_scheduled" @checked(old('collection_type') === 'non_scheduled') class="text-accent focus:ring-accent">
                                    Non-scheduled
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('collection_type')" class="mt-1.5" />
                        </div>

                        <p class="text-xs text-ink-faint">The first collection is scheduled automatically for the start date.</p>

                        <div class="flex items-center gap-3">
                            <x-primary-button>Create subscription</x-primary-button>
                            <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                        </div>
                    </form>
                </x-slide-panel>
            @endif
        @endcan

        @can('customers.manage')
            <x-slide-panel name="customer-edit" title="Edit Customer" :error-fields="['full_name', 'phone', 'email', 'customer_type', 'address', 'notes']">
                <form method="POST" action="{{ route('customers.update', $customer) }}">
                    @method('PUT')
                    @include('customers._form', ['panel' => true])
                </form>
            </x-slide-panel>
        @endcan
    </div>
</x-app-layout>
