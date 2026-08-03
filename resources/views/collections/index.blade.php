<x-app-layout>
    <x-slot name="header">Collections Schedule</x-slot>

    <form method="GET" class="mb-5 max-w-xs">
        <select name="status" onchange="this.form.submit()" class="w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
            <option value="">All statuses</option>
            @foreach (['scheduled', 'collected', 'skipped'] as $status)
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
                            <a href="{{ route('subscriptions.show', $collection->subscription) }}" class="text-ink hover:text-accent-ink">{{ $collection->subscription->customer->full_name }}</a>
                        </td>
                        <td class="px-4 py-3 text-ink-muted">{{ $collection->subscription->subscriptionPackage->name }}</td>
                        <td class="px-4 py-3"><x-status-pill :status="$collection->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @if ($collection->status === 'scheduled')
                                @can('collections.manage')
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ route('collections.collect', $collection) }}" class="text-accent-ink text-xs font-semibold hover:underline">Collect</a>
                                        <form method="POST" action="{{ route('collections.skip', $collection) }}" onsubmit="return confirm('Skip this collection? No order will be created.')">
                                            @csrf
                                            <button type="submit" class="text-critical text-xs hover:underline">Skip</button>
                                        </form>
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
                    <a href="{{ route('subscriptions.show', $collection->subscription) }}" class="text-ink hover:text-accent-ink font-medium">{{ $collection->subscription->customer->full_name }}</a>
                    <x-status-pill :status="$collection->status" />
                </div>
                <div class="flex items-center justify-between text-sm text-ink-muted">
                    <span>{{ $collection->subscription->subscriptionPackage->name }}</span>
                    <span class="font-mono text-xs">{{ $collection->scheduled_date->format('Y-m-d') }}</span>
                </div>
                @if ($collection->status === 'scheduled')
                    @can('collections.manage')
                        <div class="flex gap-4 mt-2">
                            <a href="{{ route('collections.collect', $collection) }}" class="text-accent-ink text-xs font-semibold hover:underline">Collect</a>
                            <form method="POST" action="{{ route('collections.skip', $collection) }}" onsubmit="return confirm('Skip this collection? No order will be created.')">
                                @csrf
                                <button type="submit" class="text-critical text-xs hover:underline">Skip</button>
                            </form>
                        </div>
                    @endcan
                @endif
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No collections scheduled.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $collections->links() }}</div>
</x-app-layout>
