<x-app-layout>
    <x-slot name="header">Collect — {{ $collection->subscription->customer?->full_name ?? 'Deleted customer' }}</x-slot>

    <livewire:laundry-terminal :collection-id="$collection->id" />
</x-app-layout>
