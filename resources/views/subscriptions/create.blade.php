<x-app-layout>
    <x-slot name="header">New Subscription</x-slot>

    <div class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
        @if ($eligibleCustomers->isEmpty())
            <p class="text-sm text-ink-muted">
                No customers are set to <strong>Customer type: Subscription</strong> yet.
                <a href="{{ route('customers.index') }}" class="text-accent-ink hover:underline">Edit a customer's profile</a> to change their type first.
            </p>
        @else
            <form method="POST" action="{{ route('subscriptions.store') }}" class="space-y-4">
                @csrf

                <div>
                    <x-input-label for="customer_id" value="Customer" />
                    <select id="customer_id" name="customer_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                        <option value="">Select a customer…</option>
                        @foreach ($eligibleCustomers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id', $customerId) == $customer->id)>{{ $customer->full_name }} — {{ $customer->phone }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="subscription_package_id" value="Package" />
                    <select id="subscription_package_id" name="subscription_package_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
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
                        <x-input-label for="start_date" value="Start date" />
                        <x-text-input id="start_date" name="start_date" type="date" class="block w-full" value="{{ old('start_date', now()->format('Y-m-d')) }}" required />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="End date (optional)" />
                        <x-text-input id="end_date" name="end_date" type="date" class="block w-full" value="{{ old('end_date') }}" />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-1.5" />
                    </div>
                </div>

                <p class="text-xs text-ink-faint">The first collection is scheduled automatically for the start date.</p>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Create subscription</x-primary-button>
                    <a href="{{ route('subscriptions.index') }}" class="text-sm text-ink-muted hover:text-ink">Cancel</a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
