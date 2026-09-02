<x-app-layout>
    <x-slot name="header">{{ $category->name }}</x-slot>

    <x-breadcrumbs :items="[
        ['label' => 'Categories', 'url' => route('catalog.categories')],
        ['label' => $category->name, 'url' => null],
    ]" />

    <div class="flex items-center justify-end mb-5">
        @can('catalog.manage')
            <x-panel-trigger panel="item-create">+ New Item</x-panel-trigger>
        @endcan
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        @forelse ($category->clothingItems as $item)
            <div class="bg-surface border border-line rounded-2xl overflow-hidden">
                <div class="aspect-square bg-surface-2 flex items-center justify-center">
                    @if ($itemImageUrl = \App\Support\MediaUrl::temporary($item->image_path))
                        <img src="{{ $itemImageUrl }}" alt="{{ $item->name }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-ink-faint text-xs">No image</span>
                    @endif
                </div>
                <div class="p-3">
                    <div class="text-sm font-medium text-ink truncate">{{ $item->name }}</div>
                    @can('catalog.manage')
                        <form method="POST" action="{{ route('catalog.categories.items.destroy', [$category, $item]) }}" onsubmit="return confirm('Remove this item?')" class="mt-1">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-critical text-xs hover:underline">Remove</button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <div class="col-span-full bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">
                No clothing items in this category yet.
            </div>
        @endforelse
    </div>

    @can('catalog.manage')
        <x-slide-panel name="item-create" title="New Item" :error-fields="['name', 'image']">
            <form method="POST" action="{{ route('catalog.categories.items.store', $category) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="image" value="Photo (optional)" />
                    <input id="image" name="image" type="file" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-accent-soft file:text-accent-ink file:text-xs file:font-semibold">
                    <x-input-error :messages="$errors->get('image')" class="mt-1.5" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Add item</x-primary-button>
                    <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                </div>
            </form>
        </x-slide-panel>
    @endcan
</x-app-layout>
