<x-app-layout>
    <x-slot name="header">Damage Report #{{ $damageRecord->id }} — {{ $damageRecord->order->order_number }}</x-slot>

    <x-breadcrumbs :items="[
        ['label' => 'Customers', 'url' => route('customers.index')],
        ['label' => $damageRecord->order->customer->full_name, 'url' => route('customers.show', $damageRecord->order->customer)],
        ['label' => $damageRecord->order->order_number, 'url' => route('orders.show', $damageRecord->order)],
        ['label' => 'Damage #'.$damageRecord->id, 'url' => null],
    ]" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-2 bg-surface border border-line rounded-2xl p-6">
            <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                <div>
                    <div class="text-ink-faint text-xs font-mono uppercase mb-1">Customer</div>
                    <a href="{{ route('customers.show', $damageRecord->order->customer) }}" class="text-accent-ink hover:underline">{{ $damageRecord->order->customer->full_name }}</a>
                </div>
                <div>
                    <div class="text-ink-faint text-xs font-mono uppercase mb-1">Item</div>
                    <div class="text-ink">{{ $damageRecord->item_description }}</div>
                </div>
                <div>
                    <div class="text-ink-faint text-xs font-mono uppercase mb-1">Type</div>
                    <div class="text-ink">{{ $damageRecord->damageType->name }}</div>
                </div>
                <div>
                    <div class="text-ink-faint text-xs font-mono uppercase mb-1">Stage at report</div>
                    <div class="text-ink">{{ ucfirst($damageRecord->stage_at_report ?? '—') }}</div>
                </div>
                <div>
                    <div class="text-ink-faint text-xs font-mono uppercase mb-1">Reported by</div>
                    <div class="text-ink">{{ $damageRecord->reportedBy?->name ?? 'Unknown' }} <span class="text-ink-faint font-mono text-xs">· {{ $damageRecord->created_at->format('M j, Y g:ia') }}</span></div>
                </div>
            </div>

            @if ($damageRecord->photo_path)
                <div class="mb-4">
                    <div class="text-ink-faint text-xs font-mono uppercase mb-2">Photo evidence</div>
                    <img src="{{ route('damage.photo', $damageRecord) }}" alt="Damage photo" class="rounded-xl max-h-64 border border-line">
                </div>
            @endif

            @if ($damageRecord->description)
                <div>
                    <div class="text-ink-faint text-xs font-mono uppercase mb-1">Description</div>
                    <p class="text-ink text-sm">{{ $damageRecord->description }}</p>
                </div>
            @endif
        </div>

        <div class="lg:col-span-2 bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Review History</div>
            <ul class="space-y-3 text-sm">
                <li class="flex items-start justify-between gap-3">
                    <div>
                        <span class="text-ink">Reported</span>
                        <span class="text-ink-faint"> by {{ $damageRecord->reportedBy?->name ?? 'Unknown' }}</span>
                    </div>
                    <span class="text-ink-faint font-mono text-xs whitespace-nowrap">{{ $damageRecord->created_at->format('M j, Y g:ia') }}</span>
                </li>
                @foreach ($damageRecord->statusHistory->sortBy('created_at') as $entry)
                    <li class="flex items-start justify-between gap-3 pt-3 border-t border-line">
                        <div>
                            <span class="text-ink">{{ ucfirst(str_replace('_', ' ', $entry->from_status ?? 'start')) }} → {{ ucfirst(str_replace('_', ' ', $entry->to_status)) }}</span>
                            <span class="text-ink-faint"> by {{ $entry->changedBy?->name ?? 'Unknown' }}</span>
                        </div>
                        <span class="text-ink-faint font-mono text-xs whitespace-nowrap">{{ $entry->created_at->format('M j, Y g:ia') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="space-y-5">
            <div class="bg-surface border border-line rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint">Status</div>
                    <x-status-pill :status="$damageRecord->status" />
                </div>

                @can('damage.manage')
                    <div class="space-y-2">
                        @if ($damageRecord->canTransitionTo('under_investigation'))
                            <form method="POST" action="{{ route('damage.transition', $damageRecord) }}">
                                @csrf
                                <input type="hidden" name="status" value="under_investigation">
                                <button type="submit" class="w-full px-4 py-2 bg-surface-2 text-ink rounded-lg text-sm font-semibold hover:bg-line">Move to Under Investigation</button>
                            </form>
                        @endif

                        @if ($damageRecord->canTransitionTo('approved'))
                            <form method="POST" action="{{ route('damage.transition', $damageRecord) }}">
                                @csrf
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="w-full px-4 py-2 bg-success-soft text-success rounded-lg text-sm font-semibold">Approve</button>
                            </form>
                        @endif

                        @if ($damageRecord->canTransitionTo('rejected'))
                            <form method="POST" action="{{ route('damage.transition', $damageRecord) }}">
                                @csrf
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="w-full px-4 py-2 bg-critical-soft text-critical rounded-lg text-sm font-semibold">Reject</button>
                            </form>
                        @endif

                        @if ($damageRecord->canTransitionTo('closed'))
                            <form method="POST" action="{{ route('damage.transition', $damageRecord) }}">
                                @csrf
                                <input type="hidden" name="status" value="closed">
                                <button type="submit" class="w-full px-4 py-2 bg-surface-2 text-ink-muted rounded-lg text-sm font-semibold hover:bg-line">Close</button>
                            </form>
                        @endif
                    </div>
                @endcan
            </div>

            @if ($damageRecord->resolution)
                <div class="bg-surface border border-line rounded-2xl p-6">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Resolution</div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Type</dt>
                            <dd class="text-ink">{{ ucfirst(str_replace('_', ' ', $damageRecord->resolution->resolution_type)) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Resolved by</dt>
                            <dd class="text-ink">{{ $damageRecord->resolution->resolvedBy?->name ?? 'Unknown' }}</dd>
                        </div>
                        @if ($damageRecord->resolution->amount)
                            <div class="flex justify-between">
                                <dt class="text-ink-muted">Amount</dt>
                                <dd class="font-mono tabular-nums text-ink">GMD {{ number_format($damageRecord->resolution->amount, 2) }}</dd>
                            </div>
                        @endif
                        @if ($damageRecord->resolution->notes)
                            <div class="pt-2 border-t border-line">
                                <dt class="text-ink-muted mb-1">Notes</dt>
                                <dd class="text-ink">{{ $damageRecord->resolution->notes }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @elseif ($damageRecord->status === 'approved')
                @can('damage.manage')
                    <div class="bg-surface border border-line rounded-2xl p-6">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Resolution</div>
                        <form method="POST" action="{{ route('damage.resolve', $damageRecord) }}" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-3 gap-2">
                                @foreach (['cash' => 'Cash', 'store_credit' => 'Store Credit', 'replacement' => 'Replacement'] as $value => $label)
                                    <label class="flex items-center justify-center gap-1.5 border border-line-strong rounded-lg py-2 text-xs text-ink-muted cursor-pointer has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink has-[:checked]:border-accent">
                                        <input type="radio" name="resolution_type" value="{{ $value }}" class="sr-only" required>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('resolution_type')" />

                            <div>
                                <x-input-label for="amount" value="Amount (GMD)" />
                                <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="block w-full" value="{{ old('amount') }}" />
                                <x-input-error :messages="$errors->get('amount')" class="mt-1.5" />
                            </div>

                            <div>
                                <x-input-label for="notes" value="Notes (optional)" />
                                <x-text-input id="notes" name="notes" type="text" class="block w-full" value="{{ old('notes') }}" />
                            </div>

                            <button type="submit" class="w-full px-4 py-2.5 bg-accent text-white rounded-lg text-sm font-semibold">Confirm Resolution</button>
                        </form>
                    </div>
                @endcan
            @endif
        </div>
    </div>
</x-app-layout>
