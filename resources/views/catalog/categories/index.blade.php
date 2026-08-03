<x-app-layout>
    <x-slot name="header">Clothes Categories</x-slot>

    <div class="flex items-center justify-end gap-3 mb-5">
        @can('catalog.manage')
            <x-panel-trigger panel="category-create">+ New Category</x-panel-trigger>
            @if ($categories->isNotEmpty())
                <x-panel-trigger panel="item-create">+ New Item</x-panel-trigger>
            @endif
        @endcan
    </div>

    <div class="bg-surface border border-line rounded-2xl overflow-hidden hidden md:block">
        <table class="w-full text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Name</th>
                    <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Items</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr class="border-t border-line hover:bg-surface-2">
                        <td class="px-4 py-3">
                            <a href="{{ route('catalog.categories.show', $category) }}" class="font-medium text-ink hover:text-accent-ink">{{ $category->name }}</a>
                            @if ($category->description)
                                <div class="text-ink-faint text-xs">{{ $category->description }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-ink-muted">{{ $category->clothing_items_count }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('catalog.manage')
                                <form method="POST" action="{{ route('catalog.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-critical text-xs hover:underline">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-ink-faint text-sm">No categories yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse ($categories as $category)
            <div class="bg-surface border border-line rounded-2xl p-4">
                <div class="flex items-center justify-between">
                    <a href="{{ route('catalog.categories.show', $category) }}" class="font-medium text-ink hover:text-accent-ink">{{ $category->name }}</a>
                    <span class="font-mono text-ink-muted text-sm">{{ $category->clothing_items_count }} items</span>
                </div>
                @if ($category->description)
                    <div class="text-ink-faint text-xs mt-1">{{ $category->description }}</div>
                @endif
                @can('catalog.manage')
                    <form method="POST" action="{{ route('catalog.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')" class="mt-2">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-critical text-xs hover:underline">Delete</button>
                    </form>
                @endcan
            </div>
        @empty
            <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No categories yet.</div>
        @endforelse
    </div>

    @can('catalog.manage')
        <x-slide-panel name="category-create" title="New Category" :error-fields="['name', 'description']">
            <form method="POST" action="{{ route('catalog.categories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="description" value="Description (optional)" />
                    <x-text-input id="description" name="description" type="text" class="block w-full" value="{{ old('description') }}" />
                    <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Add category</x-primary-button>
                    <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                </div>
            </form>
        </x-slide-panel>

        @if ($categories->isNotEmpty())
            <x-slide-panel name="item-create" title="New Item" :error-fields="['clothes_category_id', 'name', 'image']">
                <form method="POST" action="{{ route('catalog.items.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="item_category" value="Category" />
                        <select id="item_category" name="clothes_category_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                            @foreach ($categories as $option)
                                <option value="{{ $option->id }}" @selected(old('clothes_category_id') == $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('clothes_category_id')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="item_name" value="Name" />
                        <x-text-input id="item_name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="item_image" value="Photo (optional)" />
                        <input id="item_image" name="image" type="file" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-accent-soft file:text-accent-ink file:text-xs file:font-semibold">
                        <x-input-error :messages="$errors->get('image')" class="mt-1.5" />
                    </div>
                    <div class="flex items-center gap-3">
                        <x-primary-button>Add item</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                    </div>
                </form>
            </x-slide-panel>
        @endif
    @endcan
</x-app-layout>
