@csrf

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-input-label for="full_name" value="Full name" />
        <x-text-input id="full_name" name="full_name" type="text" class="block w-full" value="{{ old('full_name', $customer->full_name ?? '') }}" required autofocus />
        <x-input-error :messages="$errors->get('full_name')" class="mt-1.5" />
    </div>

    <div>
        <x-input-label for="phone" value="Phone" />
        <x-text-input id="phone" name="phone" type="text" class="block w-full" value="{{ old('phone', $customer->phone ?? '') }}" placeholder="+220 555 1234" required />
        <x-input-error :messages="$errors->get('phone')" class="mt-1.5" />
    </div>

    <div>
        <x-input-label for="email" value="Email (optional)" />
        <x-text-input id="email" name="email" type="email" class="block w-full" value="{{ old('email', $customer->email ?? '') }}" />
        <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
    </div>

    <div>
        <x-input-label for="customer_type" value="Customer type" />
        <select id="customer_type" name="customer_type" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
            @foreach (['walk_in' => 'Walk-in', 'subscription' => 'Subscription'] as $value => $label)
                <option value="{{ $value }}" @selected(old('customer_type', $customer->customer_type ?? 'walk_in') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('customer_type')" class="mt-1.5" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="address" value="Address (optional)" />
        <x-text-input id="address" name="address" type="text" class="block w-full" value="{{ old('address', $customer->address ?? '') }}" />
        <x-input-error :messages="$errors->get('address')" class="mt-1.5" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="notes" value="Notes (optional)" />
        <textarea id="notes" name="notes" rows="3" class="block w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm">{{ old('notes', $customer->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-1.5" />
    </div>
</div>

<div class="flex items-center gap-3 mt-6">
    <x-primary-button>{{ isset($customer) ? 'Save changes' : 'Create customer' }}</x-primary-button>
    <a href="{{ isset($customer) ? route('customers.show', $customer) : route('customers.index') }}" class="text-sm text-ink-muted hover:text-ink">Cancel</a>
</div>
