<x-app-layout>
    <x-slot name="header">New Customer</x-slot>

    <div class="bg-surface border border-line rounded-2xl p-6 max-w-2xl">
        <form method="POST" action="{{ route('customers.store') }}">
            @include('customers._form')
        </form>
    </div>
</x-app-layout>
