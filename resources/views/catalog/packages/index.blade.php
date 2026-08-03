<x-app-layout>
    <x-slot name="header">Packages</x-slot>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-ink">Laundry Packages <span class="text-ink-faint font-normal text-sm">(walk-in orders)</span></h2>
                @can('catalog.manage')
                    <x-panel-trigger panel="laundry-package-create">+ New</x-panel-trigger>
                @endcan
            </div>
            <div class="bg-surface border border-line rounded-2xl overflow-hidden mb-4 hidden md:block">
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
            <div class="md:hidden space-y-3 mb-4">
                @forelse ($laundryPackages as $package)
                    <div class="bg-surface border border-line rounded-2xl p-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-medium text-ink">{{ $package->name }}</span>
                            <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $package->is_active ? 'bg-success-soft text-success' : 'bg-pill-bg text-pill-ink' }}">
                                {{ $package->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-mono tabular-nums text-ink-muted">GMD {{ number_format($package->base_price, 2) }}</span>
                            @can('catalog.manage')
                                <form method="POST" action="{{ route('catalog.packages.laundry.destroy', $package) }}" onsubmit="return confirm('Delete this package?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-critical text-xs hover:underline">Delete</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="bg-surface border border-line rounded-2xl p-8 text-center text-ink-faint text-sm">No laundry packages yet.</div>
                @endforelse
            </div>
        </section>

        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-ink">Subscription Packages <span class="text-ink-faint font-normal text-sm">(recurring plans)</span></h2>
                @can('catalog.manage')
                    <x-panel-trigger panel="subscription-package-create">+ New</x-panel-trigger>
                @endcan
            </div>
            <div class="bg-surface border border-line rounded-2xl overflow-hidden mb-4 hidden md:block">
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
            <div class="md:hidden space-y-3 mb-4">
                @forelse ($subscriptionPackages as $package)
                    <div class="bg-surface border border-line rounded-2xl p-4">
                        <div class="font-medium text-ink mb-1">{{ $package->name }}</div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-mono tabular-nums text-ink-muted">GMD {{ number_format($package->monthly_price, 2) }} · {{ $package->clothes_allowance }} items</span>
                            @can('catalog.manage')
                                <form method="POST" action="{{ route('catalog.packages.subscription.destroy', $package) }}" onsubmit="return confirm('Delete this package?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-critical text-xs hover:underline">Delete</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="bg-surface border border-line rounded-2xl p-8 text-center text-ink-faint text-sm">No subscription packages yet.</div>
                @endforelse
            </div>
        </section>

    </div>

    @can('catalog.manage')
        <x-slide-panel name="laundry-package-create" title="New Laundry Package" :error-fields="['name', 'base_price']">
            <form method="POST" action="{{ route('catalog.packages.laundry.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="lp_name" value="Name" />
                    <x-text-input id="lp_name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="lp_price" value="Price (GMD)" />
                    <x-text-input id="lp_price" name="base_price" type="number" step="0.01" min="0" class="block w-full" value="{{ old('base_price') }}" required />
                    <x-input-error :messages="$errors->get('base_price')" class="mt-1.5" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Add</x-primary-button>
                    <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                </div>
            </form>
        </x-slide-panel>

        <x-slide-panel name="subscription-package-create" title="New Subscription Package" :error-fields="['name', 'monthly_price', 'clothes_allowance']">
            <form method="POST" action="{{ route('catalog.packages.subscription.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="sp_name" value="Name" />
                    <x-text-input id="sp_name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="sp_price" value="Monthly (GMD)" />
                    <x-text-input id="sp_price" name="monthly_price" type="number" step="0.01" min="0" class="block w-full" value="{{ old('monthly_price') }}" required />
                    <x-input-error :messages="$errors->get('monthly_price')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="sp_allowance" value="Allowance" />
                    <x-text-input id="sp_allowance" name="clothes_allowance" type="number" min="1" class="block w-full" value="{{ old('clothes_allowance') }}" required />
                    <x-input-error :messages="$errors->get('clothes_allowance')" class="mt-1.5" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Add</x-primary-button>
                    <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                </div>
            </form>
        </x-slide-panel>
    @endcan
</x-app-layout>
