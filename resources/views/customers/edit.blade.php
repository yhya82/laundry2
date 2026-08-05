<x-app-layout>
    <x-slot name="header">Edit {{ $customer->full_name }}</x-slot>

    <x-breadcrumbs :items="[
        ['label' => 'Customers', 'url' => route('customers.index')],
        ['label' => $customer->full_name, 'url' => route('customers.show', $customer)],
        ['label' => 'Edit', 'url' => null],
    ]" />

    <div class="bg-surface border border-line rounded-2xl p-6 max-w-2xl shadow-sm">
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @method('PUT')
            @include('customers._form')
        </form>
    </div>
</x-app-layout>
