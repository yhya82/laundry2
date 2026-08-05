<x-app-layout>
    <x-slot name="header">Customers</x-slot>

    <div class="flex items-center justify-between mb-5 gap-4">
        <form method="GET" class="flex-1 max-w-sm">
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search by name or phone…"
                class="w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm"
            >
        </form>

        @can('customers.manage')
            <x-panel-trigger panel="customer-create">+ New Customer</x-panel-trigger>
        @endcan
    </div>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden shadow-sm hidden md:block">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-2">
                    <tr>
                        @foreach (['full_name' => 'Name', 'created_at' => 'Added'] as $key => $label)
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc']) }}" class="hover:text-ink">
                                    {{ $label }}
                                    @if ($sort === $key) {{ $direction === 'asc' ? '↑' : '↓' }} @endif
                                </a>
                            </th>
                        @endforeach
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Phone</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Type</th>
                        <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Store Credit</th>
                        <th class="text-right font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr class="border-t border-line hover:bg-surface-2">
                            <td class="px-4 py-3">
                                <a href="{{ route('customers.show', $customer) }}" class="font-medium text-ink hover:text-accent-ink">{{ $customer->full_name }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink-muted font-mono text-xs">{{ $customer->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-ink-muted font-mono">{{ $customer->phone }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $customer->customer_type === 'subscription' ? 'bg-accent-soft text-accent-ink' : 'bg-pill-bg text-pill-ink' }}">
                                    {{ $customer->customer_type === 'subscription' ? 'Subscription' : 'Walk-in' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-ink tabular-nums">GMD {{ number_format($customer->store_credit_balance, 2) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    @can('terminal.use')
                                        <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" title="Create Order" class="w-8 h-8 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center hover:opacity-80">
                                            <x-nav-icon name="clipboard" class="w-4 h-4" />
                                        </a>
                                    @endcan
                                    @can('customers.manage')
                                        <button type="button" @click="$dispatch('open-panel', 'customer-edit-{{ $customer->id }}')" title="Edit" class="w-8 h-8 rounded-lg bg-accent text-white flex items-center justify-center hover:opacity-90">
                                            <x-nav-icon name="edit" class="w-4 h-4" />
                                        </button>
                                    @endcan
                                    <a href="{{ route('customers.show', $customer) }}" title="View Profile" class="w-8 h-8 rounded-lg bg-surface-2 text-ink-muted flex items-center justify-center hover:text-ink">
                                        <x-nav-icon name="arrow-right" class="w-4 h-4" />
                                    </a>
                                    @can('customers.manage')
                                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete {{ $customer->full_name }}? This can be restored later if needed.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Delete" class="w-8 h-8 rounded-lg bg-critical-soft text-critical flex items-center justify-center hover:bg-critical hover:text-white transition-colors">
                                                <x-nav-icon name="trash" class="w-4 h-4" />
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-ink-faint text-sm">
                                {{ request('q') ? 'No customers match "'.request('q').'".' : 'No customers yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($customers as $customer)
            <div class="bg-surface border border-line rounded-2xl p-4">
                <a href="{{ route('customers.show', $customer) }}" class="block">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-ink">{{ $customer->full_name }}</span>
                        <span class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $customer->customer_type === 'subscription' ? 'bg-accent-soft text-accent-ink' : 'bg-pill-bg text-pill-ink' }}">
                            {{ $customer->customer_type === 'subscription' ? 'Subscription' : 'Walk-in' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-ink-muted">
                        <span class="font-mono">{{ $customer->phone }}</span>
                        <span class="font-mono text-ink tabular-nums">GMD {{ number_format($customer->store_credit_balance, 2) }}</span>
                    </div>
                </a>
                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-line">
                    @can('terminal.use')
                        <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="flex-1 flex items-center justify-center gap-1.5 bg-accent-soft text-accent-ink text-xs font-semibold px-3 py-2 rounded-lg">
                            <x-nav-icon name="clipboard" class="w-3.5 h-3.5" /> Order
                        </a>
                    @endcan
                    @can('customers.manage')
                        <button type="button" @click="$dispatch('open-panel', 'customer-edit-{{ $customer->id }}')" class="flex-1 flex items-center justify-center gap-1.5 bg-accent text-white text-xs font-semibold px-3 py-2 rounded-lg">
                            <x-nav-icon name="edit" class="w-3.5 h-3.5" /> Edit
                        </button>
                    @endcan
                    <a href="{{ route('customers.show', $customer) }}" class="flex-1 flex items-center justify-center gap-1.5 bg-surface-2 text-ink-muted text-xs font-semibold px-3 py-2 rounded-lg">
                        View <x-nav-icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                    @can('customers.manage')
                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete {{ $customer->full_name }}? This can be restored later if needed.')" class="flex-1">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full flex items-center justify-center gap-1.5 bg-critical-soft text-critical text-xs font-semibold px-3 py-2 rounded-lg">
                                <x-nav-icon name="trash" class="w-3.5 h-3.5" /> Delete
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">
                {{ request('q') ? 'No customers match "'.request('q').'".' : 'No customers yet.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>

    @can('customers.manage')
        <x-slide-panel name="customer-create" title="New Customer" :error-fields="['full_name', 'phone', 'email', 'customer_type', 'address', 'notes']">
            <form method="POST" action="{{ route('customers.store') }}">
                @include('customers._form', ['panel' => true, 'customer' => null])
            </form>
        </x-slide-panel>

        @foreach ($customers as $customer)
            <x-slide-panel name="customer-edit-{{ $customer->id }}" title="Edit {{ $customer->full_name }}" :open="$errors->any() && old('editing_customer_id') == $customer->id">
                <form method="POST" action="{{ route('customers.update', $customer) }}">
                    @method('PUT')
                    <input type="hidden" name="editing_customer_id" value="{{ $customer->id }}">
                    @include('customers._form', ['panel' => true])
                </form>
            </x-slide-panel>
        @endforeach
    @endcan
</x-app-layout>
