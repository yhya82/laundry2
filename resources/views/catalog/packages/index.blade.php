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
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-2">
                            <tr>
                                <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Name</th>
                                <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Price</th>
                                <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Priority</th>
                                <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Clothes Allowed</th>
                                <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Status</th>
                                <th class="text-right font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($laundryPackages as $package)
                                <tr class="border-t border-line">
                                    <td class="px-4 py-3 text-ink font-medium">{{ $package->name }}</td>
                                    <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">GMD {{ number_format($package->base_price, 2) }}</td>
                                    <td class="px-4 py-3"><x-status-pill :status="$package->priority" /></td>
                                    <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">{{ $package->clothes_allowed !== null ? $package->clothes_allowed.' items' : '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $package->is_active ? 'bg-success-soft text-success' : 'bg-pill-bg text-pill-ink' }}">
                                            {{ $package->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @can('catalog.manage')
                                                <button type="button" @click="$dispatch('open-panel', 'laundry-edit-{{ $package->id }}')" title="Edit" class="w-8 h-8 rounded-lg bg-accent text-white flex items-center justify-center hover:opacity-90">
                                                    <x-nav-icon name="edit" class="w-4 h-4" />
                                                </button>
                                                <form method="POST" action="{{ route('catalog.packages.laundry.destroy', $package) }}" onsubmit="return confirm('Delete this package?')">
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
                                <tr><td colspan="6" class="px-4 py-8 text-center text-ink-faint text-sm">No laundry packages yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="md:hidden space-y-3 mb-4">
                @forelse ($laundryPackages as $package)
                    <div class="bg-surface border border-line rounded-2xl p-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-medium text-ink">{{ $package->name }}</span>
                            <div class="flex items-center gap-1.5">
                                @if ($package->priority === 'high')
                                    <x-status-pill :status="$package->priority" />
                                @endif
                                <span class="font-mono text-xs font-semibold px-2.5 py-1 rounded-full {{ $package->is_active ? 'bg-success-soft text-success' : 'bg-pill-bg text-pill-ink' }}">
                                    {{ $package->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                        <div class="text-sm mb-2">
                            <span class="font-mono tabular-nums text-ink-muted">GMD {{ number_format($package->base_price, 2) }}{{ $package->clothes_allowed !== null ? ' · '.$package->clothes_allowed.' items' : '' }}</span>
                        </div>
                        @can('catalog.manage')
                            <div class="flex items-center gap-2">
                                <button type="button" @click="$dispatch('open-panel', 'laundry-edit-{{ $package->id }}')" class="flex-1 flex items-center justify-center gap-1.5 bg-accent text-white text-xs font-semibold px-3 py-1.5 rounded-lg">
                                    <x-nav-icon name="edit" class="w-3.5 h-3.5" /> Edit
                                </button>
                                <form method="POST" action="{{ route('catalog.packages.laundry.destroy', $package) }}" onsubmit="return confirm('Delete this package?')" class="flex-1">
                                    <div class="flex">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="w-full flex items-center justify-center gap-1.5 bg-critical-soft text-critical text-xs font-semibold px-3 py-1.5 rounded-lg">
                                            <x-nav-icon name="trash" class="w-3.5 h-3.5" /> Delete
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="bg-surface border border-line rounded-2xl p-8 text-center text-ink-faint text-sm">No laundry packages yet.</div>
                @endforelse
            </div>
            <div class="mb-4">{{ $laundryPackages->links() }}</div>
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
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Collections/mo</th>
                            <th class="text-right font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptionPackages as $package)
                            <tr class="border-t border-line">
                                <td class="px-4 py-3 text-ink font-medium">{{ $package->name }}</td>
                                <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">GMD {{ number_format($package->monthly_price, 2) }}</td>
                                <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">{{ $package->clothes_allowance }} items</td>
                                <td class="px-4 py-3 font-mono tabular-nums text-ink-muted">{{ $package->collections_per_month }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @can('catalog.manage')
                                            <button type="button" @click="$dispatch('open-panel', 'subscription-edit-{{ $package->id }}')" title="Edit" class="w-8 h-8 rounded-lg bg-accent text-white flex items-center justify-center hover:opacity-90">
                                                <x-nav-icon name="edit" class="w-4 h-4" />
                                            </button>
                                            <form method="POST" action="{{ route('catalog.packages.subscription.destroy', $package) }}" onsubmit="return confirm('Delete this package?')">
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
                            <tr><td colspan="5" class="px-4 py-8 text-center text-ink-faint text-sm">No subscription packages yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="md:hidden space-y-3 mb-4">
                @forelse ($subscriptionPackages as $package)
                    <div class="bg-surface border border-line rounded-2xl p-4">
                        <div class="font-medium text-ink mb-1">{{ $package->name }}</div>
                        <div class="text-sm mb-2">
                            <span class="font-mono tabular-nums text-ink-muted">GMD {{ number_format($package->monthly_price, 2) }} · {{ $package->clothes_allowance }} items · {{ $package->collections_per_month }}/mo</span>
                        </div>
                        @can('catalog.manage')
                            <div class="flex items-center gap-2">
                                <button type="button" @click="$dispatch('open-panel', 'subscription-edit-{{ $package->id }}')" class="flex-1 flex items-center justify-center gap-1.5 bg-accent text-white text-xs font-semibold px-3 py-1.5 rounded-lg">
                                    <x-nav-icon name="edit" class="w-3.5 h-3.5" /> Edit
                                </button>
                                <form method="POST" action="{{ route('catalog.packages.subscription.destroy', $package) }}" onsubmit="return confirm('Delete this package?')" class="flex-1">
                                    <div class="flex">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="w-full flex items-center justify-center gap-1.5 bg-critical-soft text-critical text-xs font-semibold px-3 py-1.5 rounded-lg">
                                            <x-nav-icon name="trash" class="w-3.5 h-3.5" /> Delete
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="bg-surface border border-line rounded-2xl p-8 text-center text-ink-faint text-sm">No subscription packages yet.</div>
                @endforelse
            </div>
            <div class="mb-4">{{ $subscriptionPackages->links() }}</div>
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
                <div>
                    <x-input-label value="Priority" />
                    <div class="flex gap-3 mt-1">
                        <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                            <input type="radio" name="priority" value="normal" checked class="text-accent focus:ring-accent">
                            Normal
                        </label>
                        <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-critical has-[:checked]:bg-critical-soft has-[:checked]:text-critical transition-colors">
                            <input type="radio" name="priority" value="high" class="text-critical focus:ring-critical">
                            High
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('priority')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="lp_clothes_allowed" value="Clothes allowed (optional)" />
                    <x-text-input id="lp_clothes_allowed" name="clothes_allowed" type="number" min="0" class="block w-full" value="{{ old('clothes_allowed') }}" />
                    <p class="text-xs text-ink-faint mt-1">Leave blank for no limit.</p>
                    <x-input-error :messages="$errors->get('clothes_allowed')" class="mt-1.5" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Add</x-primary-button>
                    <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                </div>
            </form>
        </x-slide-panel>

        @foreach ($laundryPackages as $package)
            <x-slide-panel name="laundry-edit-{{ $package->id }}" title="Edit {{ $package->name }}" :open="$errors->any() && old('editing_laundry_id') == $package->id">
                <form method="POST" action="{{ route('catalog.packages.laundry.update', $package) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="editing_laundry_id" value="{{ $package->id }}">
                    <div>
                        <x-input-label for="lp_edit_name_{{ $package->id }}" value="Name" />
                        <x-text-input id="lp_edit_name_{{ $package->id }}" name="name" type="text" class="block w-full" value="{{ $package->name }}" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="lp_edit_price_{{ $package->id }}" value="Price (GMD)" />
                        <x-text-input id="lp_edit_price_{{ $package->id }}" name="base_price" type="number" step="0.01" min="0" class="block w-full" value="{{ $package->base_price }}" required />
                        <x-input-error :messages="$errors->get('base_price')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Priority" />
                        <div class="flex gap-3 mt-1">
                            <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                                <input type="radio" name="priority" value="normal" @checked($package->priority === 'normal') class="text-accent focus:ring-accent">
                                Normal
                            </label>
                            <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-critical has-[:checked]:bg-critical-soft has-[:checked]:text-critical transition-colors">
                                <input type="radio" name="priority" value="high" @checked($package->priority === 'high') class="text-critical focus:ring-critical">
                                High
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('priority')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="lp_edit_allowed_{{ $package->id }}" value="Clothes allowed (optional)" />
                        <x-text-input id="lp_edit_allowed_{{ $package->id }}" name="clothes_allowed" type="number" min="0" class="block w-full" value="{{ $package->clothes_allowed }}" />
                        <p class="text-xs text-ink-faint mt-1">Leave blank for no limit.</p>
                        <x-input-error :messages="$errors->get('clothes_allowed')" class="mt-1.5" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($package->is_active)>
                        Active
                    </label>
                    <div class="flex items-center gap-3">
                        <x-primary-button>Save changes</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                    </div>
                </form>
            </x-slide-panel>
        @endforeach

        <x-slide-panel name="subscription-package-create" title="New Subscription Package" :error-fields="['name', 'monthly_price', 'clothes_allowance', 'collections_per_month']">
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
                <div>
                    <x-input-label for="sp_collections_per_month" value="Collections per month" />
                    <x-text-input id="sp_collections_per_month" name="collections_per_month" type="number" min="1" class="block w-full" value="{{ old('collections_per_month', 1) }}" required />
                    <p class="text-xs text-ink-faint mt-1">Pickups are spaced evenly across the month based on this count.</p>
                    <x-input-error :messages="$errors->get('collections_per_month')" class="mt-1.5" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Add</x-primary-button>
                    <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                </div>
            </form>
        </x-slide-panel>

        @foreach ($subscriptionPackages as $package)
            <x-slide-panel name="subscription-edit-{{ $package->id }}" title="Edit {{ $package->name }}" :open="$errors->any() && old('editing_subscription_id') == $package->id">
                <form method="POST" action="{{ route('catalog.packages.subscription.update', $package) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="editing_subscription_id" value="{{ $package->id }}">
                    <div>
                        <x-input-label for="sp_edit_name_{{ $package->id }}" value="Name" />
                        <x-text-input id="sp_edit_name_{{ $package->id }}" name="name" type="text" class="block w-full" value="{{ $package->name }}" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="sp_edit_price_{{ $package->id }}" value="Monthly (GMD)" />
                        <x-text-input id="sp_edit_price_{{ $package->id }}" name="monthly_price" type="number" step="0.01" min="0" class="block w-full" value="{{ $package->monthly_price }}" required />
                        <x-input-error :messages="$errors->get('monthly_price')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="sp_edit_allowance_{{ $package->id }}" value="Allowance" />
                        <x-text-input id="sp_edit_allowance_{{ $package->id }}" name="clothes_allowance" type="number" min="1" class="block w-full" value="{{ $package->clothes_allowance }}" required />
                        <x-input-error :messages="$errors->get('clothes_allowance')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="sp_edit_collections_per_month_{{ $package->id }}" value="Collections per month" />
                        <x-text-input id="sp_edit_collections_per_month_{{ $package->id }}" name="collections_per_month" type="number" min="1" class="block w-full" value="{{ $package->collections_per_month }}" required />
                        <p class="text-xs text-ink-faint mt-1">Pickups are spaced evenly across the month based on this count.</p>
                        <x-input-error :messages="$errors->get('collections_per_month')" class="mt-1.5" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($package->is_active)>
                        Active
                    </label>
                    <div class="flex items-center gap-3">
                        <x-primary-button>Save changes</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                    </div>
                </form>
            </x-slide-panel>
        @endforeach
    @endcan
</x-app-layout>
