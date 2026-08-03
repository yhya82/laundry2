<x-app-layout>
    <x-slot name="header">Subscriptions</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif

    <div class="flex items-center justify-end mb-5">
        @can('subscriptions.manage')
            <a href="{{ route('subscriptions.create') }}" class="inline-flex items-center justify-center px-5 py-2.5 bg-accent border border-transparent rounded-lg font-semibold text-sm text-white hover:opacity-90">
                + New Subscription
            </a>
        @endcan
    </div>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Customer</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Package</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Start date</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $subscription)
                    <tr class="border-t border-line hover:bg-surface-2">
                        <td class="px-4 py-3">
                            <a href="{{ route('subscriptions.show', $subscription) }}" class="font-medium text-ink hover:text-accent-ink">{{ $subscription->customer->full_name }}</a>
                        </td>
                        <td class="px-4 py-3 text-ink-muted">{{ $subscription->subscriptionPackage->name }}</td>
                        <td class="px-4 py-3"><x-status-pill :status="$subscription->status" /></td>
                        <td class="px-4 py-3 font-mono text-xs text-ink-faint">{{ $subscription->start_date->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-ink-faint text-sm">No subscriptions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $subscriptions->links() }}</div>
</x-app-layout>
