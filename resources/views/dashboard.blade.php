<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="bg-surface border border-line rounded-2xl p-8 mb-5">
        <p class="text-ink">
            Welcome back, <strong>{{ auth()->user()->name }}</strong>.
        </p>
        <p class="text-ink-muted text-sm mt-2">
            Role{{ auth()->user()->roles->count() > 1 ? 's' : '' }}:
            {{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'none assigned' }}
            &middot; {{ auth()->user()->getAllPermissions()->count() }} permissions
        </p>
    </div>

    @can('orders.view')
        <div class="bg-surface border border-line rounded-2xl p-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">Laundry Queue</div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach (array_keys(\App\Models\Order::STAGE_SEQUENCE) as $stage)
                    <div class="bg-surface-2 rounded-xl p-4">
                        <div class="text-2xl font-bold text-ink tabular-nums">{{ $queueCounts[$stage] ?? 0 }}</div>
                        <div class="text-xs text-ink-muted mt-1">{{ ucfirst($stage) }}</div>
                    </div>
                @endforeach
            </div>
            <p class="text-ink-faint text-xs mt-4 font-mono">
                Revenue cards, the damage snapshot, and the live activity feed are built in Phase 09/10.
            </p>
        </div>
    @endcan
</x-app-layout>
