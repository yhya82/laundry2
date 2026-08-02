<x-app-layout>
    <x-slot name="header">{{ $title }}</x-slot>

    <div class="bg-surface border border-line rounded-2xl p-10 text-center">
        <div class="text-ink-faint font-mono text-xs uppercase tracking-wider mb-2">Not built yet</div>
        <p class="text-ink-muted text-sm max-w-md mx-auto">
            {{ $title }} is on the implementation plan for a later phase. This route and its
            <code class="font-mono text-xs bg-surface-2 px-1.5 py-0.5 rounded">{{ $permission }}</code>
            permission gate are wired up now so the sidebar and access control can be tested end-to-end.
        </p>
    </div>
</x-app-layout>
