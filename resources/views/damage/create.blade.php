<x-app-layout>
    <x-slot name="header">Report Damage — {{ $order->order_number }}</x-slot>

    <div class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
        <form method="POST" action="{{ route('damage.store', $order) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <x-input-label for="damage_type_id" value="Damage type" />
                <select id="damage_type_id" name="damage_type_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                    <option value="">Select a type…</option>
                    @foreach ($damageTypes as $type)
                        <option value="{{ $type->id }}" @selected(old('damage_type_id') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('damage_type_id')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label for="item_description" value="Item" />
                <x-text-input id="item_description" name="item_description" type="text" class="block w-full" value="{{ old('item_description') }}" placeholder="e.g. White Shirt" required />
                <x-input-error :messages="$errors->get('item_description')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label for="description" value="Description (optional)" />
                <textarea id="description" name="description" rows="3" class="block w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label for="photo" value="Photo evidence (optional)" />
                <input id="photo" name="photo" type="file" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-accent-soft file:text-accent-ink file:text-xs file:font-semibold">
                <x-input-error :messages="$errors->get('photo')" class="mt-1.5" />
            </div>

            <p class="text-xs text-ink-faint">Reported at stage: <span class="font-mono">{{ ucfirst($order->status) }}</span></p>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button>Submit report</x-primary-button>
                <a href="{{ route('orders.show', $order) }}" class="text-sm text-ink-muted hover:text-ink">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
