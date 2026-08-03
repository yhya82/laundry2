<x-app-layout>
    <x-slot name="header">{{ $subscription->customer->full_name }} — {{ $subscription->subscriptionPackage->name }}</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">Subscription</div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-ink-muted">Customer</dt>
                    <dd><a href="{{ route('customers.show', $subscription->customer) }}" class="text-accent-ink hover:underline">{{ $subscription->customer->full_name }}</a></dd>
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
                            <td class="py-2.5 font-mono text-xs text-ink">{{ $collection->scheduled_date->format('Y-m-d') }}</td>
                            <td class="py-2.5"><x-status-pill :status="$collection->status" /></td>
                            <td class="py-2.5 font-mono text-xs text-ink-faint">{{ $collection->collected_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="py-2.5 text-right">
                                @if ($collection->status === 'scheduled')
                                    <a href="{{ route('collections.index') }}" class="text-accent-ink text-xs hover:underline">Manage in Collections Schedule</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-ink-faint text-sm">No collections yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
