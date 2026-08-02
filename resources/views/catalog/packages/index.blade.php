<x-app-layout>
    <x-slot name="header">Packages</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 text-sm text-critical bg-critical-soft border border-critical/30 rounded-lg px-4 py-2.5">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <section>
            <h2 class="font-semibold text-ink mb-3">Laundry Packages <span class="text-ink-faint font-normal text-sm">(walk-in orders)</span></h2>
            <div class="bg-surface border border-line rounded-2xl overflow-hidden mb-4">
                <table class="w-full text-sm">
                    <thead class="bg-surface-2">
                        <tr>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Name</th>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Price</th>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($laundryPackages as $package)
                            <tr class="border-t border-line">
                                <td class="px-4 py-3 text-ink font-medium">{{ $package->name }}</td>
                                <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">GMD {{ number_format($package->base_price, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $package->is_active ? 'bg-success-soft text-success' : 'bg-pill-bg text-pill-ink' }}">
                                        {{ $package->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('catalog.manage')
                                        <form method="POST" action="{{ route('catalog.packages.laundry.destroy', $package) }}" onsubmit="return confirm('Delete this package?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-critical text-xs hover:underline">Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-ink-faint text-sm">No laundry packages yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('catalog.manage')
                <div class="bg-surface border border-line rounded-2xl p-5">
                    <form method="POST" action="{{ route('catalog.packages.laundry.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        @csrf
                        <div class="sm:col-span-1">
                            <x-input-label for="lp_name" value="Name" />
                            <x-text-input id="lp_name" name="name" type="text" class="block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="lp_price" value="Price (GMD)" />
                            <x-text-input id="lp_price" name="base_price" type="number" step="0.01" min="0" class="block w-full" required />
                        </div>
                        <x-primary-button>Add</x-primary-button>
                    </form>
                </div>
            @endcan
        </section>

        <section>
            <h2 class="font-semibold text-ink mb-3">Subscription Packages <span class="text-ink-faint font-normal text-sm">(recurring plans)</span></h2>
            <div class="bg-surface border border-line rounded-2xl overflow-hidden mb-4">
                <table class="w-full text-sm">
                    <thead class="bg-surface-2">
                        <tr>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Name</th>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Monthly</th>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Allowance</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptionPackages as $package)
                            <tr class="border-t border-line">
                                <td class="px-4 py-3 text-ink font-medium">{{ $package->name }}</td>
                                <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">GMD {{ number_format($package->monthly_price, 2) }}</td>
                                <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">{{ $package->clothes_allowance }} items</td>
                                <td class="px-4 py-3 text-right">
                                    @can('catalog.manage')
                                        <form method="POST" action="{{ route('catalog.packages.subscription.destroy', $package) }}" onsubmit="return confirm('Delete this package?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-critical text-xs hover:underline">Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-ink-faint text-sm">No subscription packages yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('catalog.manage')
                <div class="bg-surface border border-line rounded-2xl p-5">
                    <form method="POST" action="{{ route('catalog.packages.subscription.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        @csrf
                        <div>
                            <x-input-label for="sp_name" value="Name" />
                            <x-text-input id="sp_name" name="name" type="text" class="block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="sp_price" value="Monthly (GMD)" />
                            <x-text-input id="sp_price" name="monthly_price" type="number" step="0.01" min="0" class="block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="sp_allowance" value="Allowance" />
                            <x-text-input id="sp_allowance" name="clothes_allowance" type="number" min="1" class="block w-full" required />
                        </div>
                        <div class="sm:col-span-3">
                            <x-primary-button>Add</x-primary-button>
                        </div>
                    </form>
                </div>
            @endcan
        </section>

    </div>
</x-app-layout>
