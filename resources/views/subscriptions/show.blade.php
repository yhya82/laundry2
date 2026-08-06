<x-app-layout>
    <x-slot name="header">{{ $subscription->customer?->full_name ?? 'Deleted customer' }} — {{ $subscription->subscriptionPackage->name }}</x-slot>

    <x-breadcrumbs :items="[
        ['label' => 'Customers', 'url' => route('customers.index')],
        ['label' => $subscription->customer?->full_name ?? 'Deleted customer', 'url' => $subscription->customer ? route('customers.show', $subscription->customer) : null],
        ['label' => $subscription->subscriptionPackage->name, 'url' => null],
    ]" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="space-y-5">
        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">Subscription</div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-ink-muted">Customer</dt>
                    <dd>
                        @if ($subscription->customer)
                            <a href="{{ route('customers.show', $subscription->customer) }}" class="text-accent-ink hover:underline">{{ $subscription->customer->full_name }}</a>
                        @else
                            <span class="text-ink-faint">Deleted customer</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-ink-muted">Status</dt>
                    <dd><x-status-pill :status="$subscription->status" /></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-ink-muted">Package</dt>
                    <dd class="text-ink">{{ $subscription->subscriptionPackage->name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-ink-muted">Monthly</dt>
                    <dd class="font-mono tabular-nums text-ink">GMD {{ number_format($subscription->subscriptionPackage->monthly_price, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-ink-muted">Allowance</dt>
                    <dd class="font-mono tabular-nums text-ink">{{ $subscription->subscriptionPackage->clothes_allowance }} items</dd>
                </div>
                <div class="flex justify-between pt-2 border-t border-line">
                    <dt class="text-ink-muted">Start date</dt>
                    <dd class="font-mono text-xs text-ink">{{ $subscription->start_date->format('Y-m-d') }}</dd>
                </div>
                @if ($subscription->end_date)
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">End date</dt>
                        <dd class="font-mono text-xs text-ink">{{ $subscription->end_date->format('Y-m-d') }}</dd>
                    </div>
                @endif
            </dl>

            @can('subscriptions.manage')
                @if ($subscription->status !== 'cancelled')
                    <div class="flex items-center gap-3 mt-5 pt-5 border-t border-line">
                        @if ($subscription->status === 'active')
                            <form method="POST" action="{{ route('subscriptions.pause', $subscription) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-pill-bg text-pill-ink rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
                                    <x-nav-icon name="pause" class="w-3.5 h-3.5" />
                                    Pause
                                </button>
                            </form>
                        @elseif ($subscription->status === 'paused')
                            <form method="POST" action="{{ route('subscriptions.resume', $subscription) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-success-soft text-success rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
                                    <x-nav-icon name="play" class="w-3.5 h-3.5" />
                                    Resume
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('subscriptions.cancel', $subscription) }}" class="flex-1" onsubmit="return confirm('Cancel this subscription? This cannot be undone.')">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-critical text-white rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
                                <x-nav-icon name="x" class="w-3.5 h-3.5" />
                                Cancel
                            </button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>

        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">Current Cycle</div>
            @if ($currentCycle)
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Period</dt>
                        <dd class="font-mono text-xs text-ink">
                            {{ $currentCycle->starts_on->format('M d') }}@if ($currentCycle->ends_on) – {{ $currentCycle->ends_on->format('M d') }}@endif
                        </dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-ink-muted">Collection type</dt>
                        <dd class="text-ink">
                            @can('subscriptions.manage')
                                @if ($cycleCollectionsCompleted === 0)
                                    <form method="POST" action="{{ route('subscriptions.collection-type.update', $subscription) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <select name="collection_type" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-xs focus:border-accent focus:ring-accent py-1">
                                            <option value="scheduled" @selected($subscription->collection_type === 'scheduled')>Scheduled</option>
                                            <option value="non_scheduled" @selected($subscription->collection_type === 'non_scheduled')>Non-scheduled</option>
                                        </select>
                                        <button type="submit" class="text-xs font-semibold text-accent-ink hover:underline">Save</button>
                                    </form>
                                @else
                                    {{ $subscription->collection_type === 'scheduled' ? 'Scheduled' : 'Non-scheduled' }}
                                @endif
                            @else
                                {{ $subscription->collection_type === 'scheduled' ? 'Scheduled' : 'Non-scheduled' }}
                            @endcan
                        </dd>
                    </div>
                    @error('collection_type') <p class="text-critical text-xs text-right">{{ $message }}</p> @enderror
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Collections</dt>
                        <dd class="font-mono tabular-nums text-ink">{{ $cycleCollectionsCompleted }} completed of {{ $cycleCollectionsTotal }} planned</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-muted">Clothes used</dt>
                        <dd class="font-mono tabular-nums {{ $currentCycle->clothesCollected() > $currentCycle->max_clothes_snapshot ? 'text-critical font-semibold' : 'text-ink' }}">
                            {{ $currentCycle->clothesCollected() }} / {{ $currentCycle->max_clothes_snapshot }}
                            @if ($currentCycle->clothesCollected() > $currentCycle->max_clothes_snapshot)
                                (+{{ $currentCycle->clothesCollected() - $currentCycle->max_clothes_snapshot }} over)
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-line">
                        <dt class="text-ink-muted">Cycle balance</dt>
                        <dd>
                            @if ($currentCycle->balanceDue() > 0)
                                <span class="font-mono tabular-nums text-critical font-semibold">GMD {{ number_format($currentCycle->balanceDue(), 2) }} due</span>
                            @else
                                <span class="font-mono text-success font-semibold">Payment completed</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @can('subscriptions.manage')
                    @if ($needsRenewal)
                        <div class="mt-5 pt-5 border-t border-line">
                            <div class="bg-accent-soft rounded-xl p-3 mb-3">
                                <p class="text-sm text-accent-ink font-medium">This cycle's collections are all resolved.</p>
                                <p class="text-xs text-ink-muted mt-0.5">Nothing continues automatically — renew to start the next cycle.</p>
                            </div>
                            <button type="button" @click="$dispatch('open-panel', 'renew-cycle-{{ $subscription->id }}')" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-accent text-white rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
                                <x-nav-icon name="repeat" class="w-3.5 h-3.5" />
                                Renew
                            </button>
                            <x-renew-cycle-modal :subscription="$subscription" :packages="$packages" />
                        </div>
                    @endif
                @endcan
            @else
                <p class="text-sm text-ink-faint">No active cycle for this subscription.</p>
            @endif
        </div>
        </div>

        <div class="lg:col-span-2 bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">Collection History</div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="font-mono text-xs uppercase tracking-wide text-ink-faint pb-2">Scheduled</th>
                        <th class="font-mono text-xs uppercase tracking-wide text-ink-faint pb-2">Status</th>
                        <th class="font-mono text-xs uppercase tracking-wide text-ink-faint pb-2">Collected</th>
                        <th class="pb-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscription->collections as $collection)
                        <tr class="border-t border-line">
                            <td class="py-2.5 font-mono text-xs text-ink">{{ $collection->scheduled_date?->format('Y-m-d') ?? 'Anytime' }}</td>
                            <td class="py-2.5">
                                <x-status-pill :status="$collection->status" />
                                @if ($collection->status === 'cancelled')
                                    <div class="text-xs text-ink-faint mt-1">
                                        {{ $collection->cancellation_reason }}
                                        @if ($collection->combinedInto)
                                            — combined into {{ $collection->combinedInto->scheduled_date?->format('Y-m-d') ?? 'anytime pickup #'.$collection->combinedInto->id }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="py-2.5 font-mono text-xs text-ink-faint">{{ $collection->collected_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="py-2.5 text-right">
                                @if ($collection->status === 'scheduled')
                                    @can('collections.manage')
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($subscription->status === 'active')
                                                <a href="{{ route('collections.collect', $collection) }}" class="inline-flex items-center gap-1 bg-accent-soft text-accent-ink text-xs font-semibold px-2.5 py-1 rounded-full hover:bg-accent hover:text-white transition-colors">
                                                    <x-nav-icon name="truck" class="w-3 h-3" />
                                                    Collect
                                                </a>
                                                <button type="button" @click="$dispatch('open-panel', 'cancel-{{ $collection->id }}')" class="inline-flex items-center gap-1 bg-critical-soft text-critical text-xs font-semibold px-2.5 py-1 rounded-full hover:bg-critical hover:text-white transition-colors">
                                                    <x-nav-icon name="x" class="w-3 h-3" />
                                                    Skip
                                                </button>
                                                <form method="POST" action="{{ route('collections.cancel', $collection) }}" onsubmit="return confirm('Cancel this collection outright? No order will be created.')">
                                                    @csrf
                                                    <button type="submit" title="Cancel" class="inline-flex items-center gap-1 bg-surface-2 text-ink-faint text-xs font-semibold px-2 py-1 rounded-full hover:bg-critical hover:text-white transition-colors">
                                                        <x-nav-icon name="trash" class="w-3 h-3" />
                                                    </button>
                                                </form>
                                            @else
                                                <span title="Subscription is paused" class="inline-flex items-center gap-1 bg-surface-2 text-ink-faint text-xs font-semibold px-2.5 py-1 rounded-full cursor-not-allowed">
                                                    <x-nav-icon name="truck" class="w-3 h-3" />
                                                    Collect
                                                </span>
                                                <span title="Subscription is paused" class="inline-flex items-center gap-1 bg-surface-2 text-ink-faint text-xs font-semibold px-2.5 py-1 rounded-full cursor-not-allowed">
                                                    <x-nav-icon name="x" class="w-3 h-3" />
                                                    Skip
                                                </span>
                                                <span title="Subscription is paused" class="inline-flex items-center gap-1 bg-surface-2 text-ink-faint text-xs font-semibold px-2 py-1 rounded-full cursor-not-allowed">
                                                    <x-nav-icon name="trash" class="w-3 h-3" />
                                                </span>
                                            @endif
                                        </div>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-ink-faint text-sm">No collections yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lg:col-span-3 bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">Billing Cycles</div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="font-mono text-xs uppercase tracking-wide text-ink-faint pb-2">Cycle</th>
                        <th class="font-mono text-xs uppercase tracking-wide text-ink-faint pb-2">Price</th>
                        <th class="font-mono text-xs uppercase tracking-wide text-ink-faint pb-2">Paid</th>
                        <th class="font-mono text-xs uppercase tracking-wide text-ink-faint pb-2">Due</th>
                        <th class="pb-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscription->cycles as $cycle)
                        <tr class="border-t border-line">
                            <td class="py-2.5 font-mono text-xs text-ink">{{ $cycle->starts_on->format('M Y') }}</td>
                            <td class="py-2.5 font-mono text-xs text-ink-muted">GMD {{ number_format($cycle->monthly_price_snapshot, 2) }}</td>
                            <td class="py-2.5 font-mono text-xs text-ink-muted">GMD {{ number_format($cycle->amountPaid(), 2) }}</td>
                            <td class="py-2.5">
                                @if ($cycle->balanceDue() > 0)
                                    <span class="font-mono text-xs text-critical font-semibold">GMD {{ number_format($cycle->balanceDue(), 2) }}</span>
                                @else
                                    <span class="font-mono text-xs text-success font-semibold">Paid</span>
                                @endif
                            </td>
                            <td class="py-2.5 text-right">
                                @if ($cycle->balanceDue() > 0)
                                    @can('orders.manage')
                                        <button type="button" @click="$dispatch('open-panel', 'record-cycle-payment-{{ $cycle->id }}')" class="inline-flex items-center gap-1 bg-critical-soft text-critical text-xs font-semibold px-2.5 py-1 rounded-full hover:bg-critical hover:text-white transition-colors">
                                            Pay
                                        </button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-ink-faint text-sm">No billing cycles yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('orders.manage')
        @foreach ($subscription->cycles as $cycle)
            @if ($cycle->balanceDue() > 0)
                <x-slide-panel name="record-cycle-payment-{{ $cycle->id }}" title="Record Payment" :open="$errors->any() && (int) old('subscription_cycle_id') === $cycle->id">
                    <form method="POST" action="{{ route('subscriptionCycles.payments.record', $cycle) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="subscription_cycle_id" value="{{ $cycle->id }}">
                        <p class="text-sm text-ink-muted">
                            {{ $cycle->starts_on->format('M Y') }} —
                            balance due: <span class="font-mono text-ink font-semibold">GMD {{ number_format($cycle->balanceDue(), 2) }}</span>
                        </p>

                        @if ($subscription->customer?->store_credit_balance > 0)
                            <div>
                                <x-input-label for="subcyclecp_credit_applied_{{ $cycle->id }}" value="Apply store credit" />
                                <x-text-input id="subcyclecp_credit_applied_{{ $cycle->id }}" name="credit_applied" type="number" step="0.01" min="0" max="{{ min($subscription->customer->store_credit_balance, $cycle->balanceDue()) }}" class="block w-full" />
                                <p class="text-xs text-ink-faint mt-1">Of GMD {{ number_format($subscription->customer->store_credit_balance, 2) }} available.</p>
                                @if ((int) old('subscription_cycle_id') === $cycle->id)
                                    <x-input-error :messages="$errors->get('credit_applied')" class="mt-1.5" />
                                @endif
                            </div>
                        @endif

                        <div>
                            <x-input-label for="subcyclecp_amount_{{ $cycle->id }}" value="Amount collected (GMD)" />
                            <x-text-input id="subcyclecp_amount_{{ $cycle->id }}" name="amount" type="number" step="0.01" min="0" class="block w-full" required />
                            @if ((int) old('subscription_cycle_id') === $cycle->id)
                                <x-input-error :messages="$errors->get('amount')" class="mt-1.5" />
                            @endif
                        </div>

                        <div>
                            <x-input-label for="subcyclecp_method_{{ $cycle->id }}" value="Method" />
                            <select id="subcyclecp_method_{{ $cycle->id }}" name="method" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
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
            @endif
        @endforeach
    @endcan

    @can('collections.manage')
        @foreach ($subscription->collections as $collection)
            @if ($collection->status === 'scheduled')
                <x-slide-panel name="cancel-{{ $collection->id }}" title="Skip Collection" :open="$errors->any() && (int) old('cancel_collection_id') === $collection->id">
                    <form method="POST" action="{{ route('collections.cancel', $collection) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="cancel_collection_id" value="{{ $collection->id }}">
                        <p class="text-sm text-ink-muted">
                            Cancels the <span class="font-mono text-ink">{{ $collection->scheduled_date?->format('Y-m-d') ?? 'anytime' }}</span> pickup and folds it into another one — that visit will cover both.
                        </p>
                        @php
                            $combineOptions = $subscription->collections
                                ->where('status', 'scheduled')
                                ->where('id', '!=', $collection->id)
                                ->when($collection->subscription_cycle_id, fn ($c) => $c->where('subscription_cycle_id', $collection->subscription_cycle_id))
                                ->sortBy('scheduled_date');
                        @endphp
                        <div>
                            <x-input-label for="combine_into_{{ $collection->id }}" value="Combine into" />
                            <select id="combine_into_{{ $collection->id }}" name="combined_into_collection_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
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
                            <x-input-label for="cancel_reason_{{ $collection->id }}" value="Reason" />
                            <textarea id="cancel_reason_{{ $collection->id }}" name="reason" rows="2" placeholder="e.g. Customer travelling" class="w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm" required></textarea>
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
</x-app-layout>
