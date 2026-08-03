<x-app-layout>
    <x-slot name="header">Collect — {{ $collection->subscription->customer->full_name }}</x-slot>

    <livewire:laundry-terminal :collection-id="$collection->id" />
</x-app-layout>
