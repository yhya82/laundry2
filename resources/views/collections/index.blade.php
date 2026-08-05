<x-app-layout>
    <x-slot name="header">Collections Schedule</x-slot>

    <form method="GET" class="mb-5 max-w-xs">
        <select name="status" onchange="this.form.submit()" class="w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
            <option value="">All statuses</option>
            @foreach (['scheduled', 'collected', 'skipped', 'cancelled'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </form>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden hidden md:block">
        <table class="w-full text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Scheduled</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Customer</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Package</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($collections as $collection)
                    <tr class="border-t border-line hover:bg-surface-2">
                        <td class="px-4 py-3 font-mono text-xs text-ink">{{ $collection->scheduled_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('subscriptions.show', $collection->subscription) }}" class="text-ink hover:text-accent-ink">{{ $collection->subscription->customer?->full_name ?? 'Deleted customer' }}</a>
                        </td>
                        <td class="px-4 py-3 text-ink-muted">{{ $collection->subscription->subscriptionPackage->name }}</td>
                        <td class="px-4 py-3"><x-status-pill :status="$collection->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @if ($collection->status === 'scheduled')
                                @can('collections.manage')
                                    <div class="flex justify-end gap-2">
                                        @if ($collection->subscription->status === 'active')
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
                    <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint text-sm">No collections scheduled.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($collections as $collection)
            <div class="bg-surface border border-line rounded-2xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <a href="{{ route('subscriptions.show', $collection->subscription) }}" class="text-ink hover:text-accent-ink font-medium">{{ $collection->subscription->customer?->full_name ?? 'Deleted customer' }}</a>
                    <x-status-pill :status="$collection->status" />
                </div>
                <div class="flex items-center justify-between text-sm text-ink-muted">
                    <span>{{ $collection->subscription->subscriptionPackage->name }}</span>
                    <span class="font-mono text-xs">{{ $collection->scheduled_date->format('Y-m-d') }}</span>
                </div>
                @if ($collection->status === 'scheduled')
                    @can('collections.manage')
                        <div class="flex gap-2 mt-2">
                            @if ($collection->subscription->status === 'active')
                                <a href="{{ route('collections.collect', $collection) }}" class="flex-1 inline-flex items-center justify-center gap-1 bg-accent-soft text-accent-ink text-xs font-semibold px-2.5 py-1.5 rounded-lg">
                                    <x-nav-icon name="truck" class="w-3.5 h-3.5" />
                                    Collect
                                </a>
                                <button type="button" @click="$dispatch('open-panel', 'cancel-{{ $collection->id }}')" class="flex-1 inline-flex items-center justify-center gap-1 bg-critical-soft text-critical text-xs font-semibold px-2.5 py-1.5 rounded-lg">
                                    <x-nav-icon name="x" class="w-3.5 h-3.5" />
                                    Skip
                                </button>
                                <form method="POST" action="{{ route('collections.cancel', $collection) }}" onsubmit="return confirm('Cancel this collection outright? No order will be created.')">
                                    @csrf
                                    <button type="submit" title="Cancel" class="flex-none h-full inline-flex items-center justify-center px-2.5 py-1.5 bg-surface-2 text-ink-faint rounded-lg hover:bg-critical hover:text-white transition-colors">
                                        <x-nav-icon name="trash" class="w-3.5 h-3.5" />
                                    </button>
                                </form>
                            @else
                                <span title="Subscription is paused" class="flex-1 inline-flex items-center justify-center gap-1 bg-surface-2 text-ink-faint text-xs font-semibold px-2.5 py-1.5 rounded-lg cursor-not-allowed">
                                    <x-nav-icon name="truck" class="w-3.5 h-3.5" />
                                    Collect
                                </span>
                                <span title="Subscription is paused" class="flex-1 inline-flex items-center justify-center gap-1 bg-surface-2 text-ink-faint text-xs font-semibold px-2.5 py-1.5 rounded-lg cursor-not-allowed">
                                    <x-nav-icon name="x" class="w-3.5 h-3.5" />
                                    Skip
                                </span>
                                <span title="Subscription is paused" class="flex-none h-full inline-flex items-center justify-center px-2.5 py-1.5 bg-surface-2 text-ink-faint rounded-lg cursor-not-allowed">
                                    <x-nav-icon name="trash" class="w-3.5 h-3.5" />
                                </span>
                            @endif
                        </div>
                    @endcan
                @endif
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No collections scheduled.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $collections->links() }}</div>

    @can('collections.manage')
        @foreach ($collections as $collection)
            @if ($collection->status === 'scheduled')
                <x-slide-panel name="cancel-{{ $collection->id }}" title="Skip Collection" :open="$errors->any() && (int) old('cancel_collection_id') === $collection->id">
                    <form method="POST" action="{{ route('collections.cancel', $collection) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="cancel_collection_id" value="{{ $collection->id }}">
                        <p class="text-sm text-ink-muted">
                            Cancels the <span class="font-mono text-ink">{{ $collection->scheduled_date->format('Y-m-d') }}</span> pickup and folds it into another one -- that visit will cover both.
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
                            <x-input-label for="combine_into_{{ $collection->id }}" value="Combine into" />
                            <select id="combine_into_{{ $collection->id }}" name="combined_into_collection_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                                <option value="">Select a pickup…</option>
                                @foreach ($combineOptions as $option)
                                    <option value="{{ $option->id }}">{{ $option->scheduled_date->format('Y-m-d') }}</option>
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
