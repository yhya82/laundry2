@props(['customer', 'packages', 'redirectToTerminal' => false, 'open' => null])

<div
    x-data="{
        open: @js($open ?? (old('customer_id') == $customer->id && $errors->any())),
        packages: {{ Js::from($packages->mapWithKeys(fn ($p) => [$p->id => [
            'collections_per_month' => $p->collections_per_month,
            'max_clothes_per_cycle' => $p->max_clothes_per_cycle,
        ]])) }},
        applyDefaults(id) {
            const pkg = this.packages[id];
            if (pkg) {
                this.$refs.collectionsPerMonth.value = pkg.collections_per_month;
                this.$refs.maxClothesPerCycle.value = pkg.max_clothes_per_cycle;
            }
        },
    }"
    x-on:open-panel.window="$event.detail === 'new-subscription' && (open = true)"
    x-on:close-panel.window="$event.detail === 'new-subscription' && (open = false)"
    x-on:keydown.escape.window="open = false"
>
    <div
        x-show="open" x-cloak
        class="fixed inset-0 bg-ink/40 backdrop-blur-sm z-40 flex items-center justify-center p-4"
        x-transition.opacity
        @click.self="open = false"
    >
        <div
            class="bg-surface border border-line rounded-2xl shadow-xl w-full max-w-md p-6"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-ink">New Subscription</h3>
                <button type="button" @click="open = false" class="text-ink-faint hover:text-ink" aria-label="Close">✕</button>
            </div>

            <form method="POST" action="{{ route('subscriptions.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                @if ($redirectToTerminal)
                    <input type="hidden" name="redirect_customer_id" value="{{ $customer->id }}">
                @else
                    <input type="hidden" name="return_to_profile" value="1">
                @endif

                <div>
                    <x-input-label for="ns_package_{{ $customer->id }}" value="Package" />
                    <select id="ns_package_{{ $customer->id }}" name="subscription_package_id" @change="applyDefaults($event.target.value)" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                        <option value="">Select a package…</option>
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" @selected(old('subscription_package_id') == $package->id)>
                                {{ $package->name }} — GMD {{ number_format($package->monthly_price, 2) }}/mo, {{ $package->clothes_allowance }} items
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('subscription_package_id')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="ns_start_date_{{ $customer->id }}" value="Start date" />
                        <x-text-input id="ns_start_date_{{ $customer->id }}" name="start_date" type="date" class="block w-full" value="{{ old('start_date', now()->format('Y-m-d')) }}" required />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="ns_end_date_{{ $customer->id }}" value="End date (optional)" />
                        <x-text-input id="ns_end_date_{{ $customer->id }}" name="end_date" type="date" class="block w-full" value="{{ old('end_date') }}" />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-1.5" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="ns_collections_per_month_{{ $customer->id }}" value="Number of collections" />
                        <x-text-input x-ref="collectionsPerMonth" id="ns_collections_per_month_{{ $customer->id }}" name="collections_per_month" type="number" min="1" max="28" class="block w-full" value="{{ old('collections_per_month') }}" required />
                        <x-input-error :messages="$errors->get('collections_per_month')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="ns_max_clothes_per_cycle_{{ $customer->id }}" value="Max clothes per cycle" />
                        <x-text-input x-ref="maxClothesPerCycle" id="ns_max_clothes_per_cycle_{{ $customer->id }}" name="max_clothes_per_cycle" type="number" min="1" class="block w-full" value="{{ old('max_clothes_per_cycle') }}" required />
                        <x-input-error :messages="$errors->get('max_clothes_per_cycle')" class="mt-1.5" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Collection type" />
                    <div class="flex gap-3 mt-1">
                        <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                            <input type="radio" name="collection_type" value="scheduled" @checked(old('collection_type', 'scheduled') === 'scheduled') class="text-accent focus:ring-accent">
                            Scheduled
                        </label>
                        <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                            <input type="radio" name="collection_type" value="non_scheduled" @checked(old('collection_type') === 'non_scheduled') class="text-accent focus:ring-accent">
                            Non-scheduled
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('collection_type')" class="mt-1.5" />
                </div>

                <p class="text-xs text-ink-faint">The first collection is scheduled automatically for the start date.</p>

                <div class="flex items-center gap-3">
                    <x-primary-button>Create subscription</x-primary-button>
                    <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
