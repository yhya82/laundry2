<x-app-layout>
    <x-slot name="header">New Order — Laundry Terminal</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink-muted hover:text-accent-ink transition-colors">
            <x-nav-icon name="clipboard" class="w-4 h-4" />
            All Orders
        </a>
    </x-slot>

    <livewire:laundry-terminal :customer-id="$customerId" />
</x-app-layout>
