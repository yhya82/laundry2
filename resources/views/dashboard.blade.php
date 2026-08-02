<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="bg-surface border border-line rounded-2xl p-8">
        <p class="text-ink">
            Welcome back, <strong>{{ auth()->user()->name }}</strong>.
        </p>
        <p class="text-ink-muted text-sm mt-2">
            Role{{ auth()->user()->roles->count() > 1 ? 's' : '' }}:
            {{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'none assigned' }}
            &middot; {{ auth()->user()->getAllPermissions()->count() }} permissions
        </p>
        <p class="text-ink-faint text-xs mt-4 font-mono">
            Stat cards, the live order queue, and the damage snapshot are built in Phase 09 — this confirms the permission-gated shell and login are working end to end.
        </p>
    </div>
</x-app-layout>
