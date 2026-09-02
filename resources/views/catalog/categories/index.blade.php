<x-app-layout>
    <x-slot name="header">Clothes Categories</x-slot>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-ink">Categories</h2>
                @can('catalog.manage')
                    <x-panel-trigger panel="category-create">+ New Category</x-panel-trigger>
                @endcan
            </div>
            <div class="bg-surface border border-line rounded-2xl overflow-hidden mb-4 hidden md:block">
                <table class="w-full text-sm">
                    <thead class="bg-surface-2">
                        <tr>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Name</th>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Items</th>
                            @can('catalog.manage')
                                <th class="text-right font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Actions</th>
                            @endcan
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
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @can('catalog.manage')
                                            <button type="button" @click="$dispatch('open-panel', 'category-edit-{{ $category->id }}')" title="Edit" class="w-8 h-8 rounded-lg bg-accent text-white flex items-center justify-center hover:opacity-90">
                                                <x-nav-icon name="edit" class="w-4 h-4" />
                                            </button>
                                            <form method="POST" action="{{ route('catalog.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
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
                            <tr><td colspan="3" class="px-4 py-10 text-center text-ink-faint text-sm">No categories yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="md:hidden space-y-3 mb-4">
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
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" @click="$dispatch('open-panel', 'category-edit-{{ $category->id }}')" class="flex-1 flex items-center justify-center gap-1.5 bg-accent text-white text-xs font-semibold px-3 py-1.5 rounded-lg">
                                    <x-nav-icon name="edit" class="w-3.5 h-3.5" /> Edit
                                </button>
                                <form method="POST" action="{{ route('catalog.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')" class="flex-1">
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
                    <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No categories yet.</div>
                @endforelse
            </div>

            <div class="mb-4">{{ $categories->links() }}</div>
        </section>

        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-ink">Clothes</h2>
                @can('catalog.manage')
                    @if ($allCategories->isNotEmpty())
                        <x-panel-trigger panel="item-create">+ New Item</x-panel-trigger>
                    @endif
                @endcan
            </div>
            <div class="bg-surface border border-line rounded-2xl overflow-hidden mb-4 hidden md:block">
                <table class="w-full text-sm">
                    <thead class="bg-surface-2">
                        <tr>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Name</th>
                            <th class="text-left font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Category</th>
                             @can('catalog.manage')
                                <th class="text-right font-mono text-xs uppercase tracking-wide text-ink-faint px-4 py-3">Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clothingItems as $item)
                            <tr class="border-t border-line hover:bg-surface-2">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-surface-2 flex-none overflow-hidden flex items-center justify-center">
                                            @if ($itemImageUrl = \App\Support\MediaUrl::temporary($item->image_path))
                                                <img src="{{ $itemImageUrl }}" alt="" class="w-full h-full object-cover">
                                            @else
                                                <span class="text-ink-faint text-xs font-semibold">{{ Str::substr($item->name, 0, 1) }}</span>
                                            @endif
                                        </div>
                                        <span class="font-medium text-ink">{{ $item->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-ink-muted">{{ $item->category->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @can('catalog.manage')
                                            <button type="button" @click="$dispatch('open-panel', 'item-edit-{{ $item->id }}')" title="Edit" class="w-8 h-8 rounded-lg bg-accent text-white flex items-center justify-center hover:opacity-90">
                                                <x-nav-icon name="edit" class="w-4 h-4" />
                                            </button>
                                            <form method="POST" action="{{ route('catalog.categories.items.destroy', [$item->category, $item]) }}" onsubmit="return confirm('Remove this item?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" title="Remove" class="w-8 h-8 rounded-lg bg-critical-soft text-critical flex items-center justify-center hover:bg-critical hover:text-white transition-colors">
                                                    <x-nav-icon name="trash" class="w-4 h-4" />
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-10 text-center text-ink-faint text-sm">No clothing items yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="md:hidden space-y-3 mb-4">
                @forelse ($clothingItems as $item)
                    <div class="bg-surface border border-line rounded-2xl p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-surface-2 flex-none overflow-hidden flex items-center justify-center">
                                    @if ($itemImageUrl = \App\Support\MediaUrl::temporary($item->image_path))
                                        <img src="{{ $itemImageUrl }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-ink-faint text-xs font-semibold">{{ Str::substr($item->name, 0, 1) }}</span>
                                    @endif
                                </div>
                                <span class="font-medium text-ink">{{ $item->name }}</span>
                            </div>
                            <span class="font-mono text-ink-muted text-xs">{{ $item->category->name ?? '—' }}</span>
                        </div>
                        @can('catalog.manage')
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" @click="$dispatch('open-panel', 'item-edit-{{ $item->id }}')" class="flex-1 flex items-center justify-center gap-1.5 bg-accent text-white text-xs font-semibold px-3 py-1.5 rounded-lg">
                                    <x-nav-icon name="edit" class="w-3.5 h-3.5" /> Edit
                                </button>
                                <form method="POST" action="{{ route('catalog.categories.items.destroy', [$item->category, $item]) }}" onsubmit="return confirm('Remove this item?')" class="flex-1">
                                    <div class="flex">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="w-full flex items-center justify-center gap-1.5 bg-critical-soft text-critical text-xs font-semibold px-3 py-1.5 rounded-lg">
                                            <x-nav-icon name="trash" class="w-3.5 h-3.5" /> Remove
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endcan
                    </div>
                @empty
                    <div class="bg-surface border border-line rounded-2xl p-10 text-center text-ink-faint text-sm">No clothing items yet.</div>
                @endforelse
            </div>

            <div class="mb-4">{{ $clothingItems->links() }}</div>
        </section>

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

        @foreach ($categories as $category)
            <x-slide-panel name="category-edit-{{ $category->id }}" title="Edit {{ $category->name }}" :open="$errors->any() && old('editing_category_id') == $category->id">
                <form method="POST" action="{{ route('catalog.categories.update', $category) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="editing_category_id" value="{{ $category->id }}">
                    <div>
                        <x-input-label for="cat_edit_name_{{ $category->id }}" value="Name" />
                        <x-text-input id="cat_edit_name_{{ $category->id }}" name="name" type="text" class="block w-full" value="{{ $category->name }}" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="cat_edit_desc_{{ $category->id }}" value="Description (optional)" />
                        <x-text-input id="cat_edit_desc_{{ $category->id }}" name="description" type="text" class="block w-full" value="{{ $category->description }}" />
                        <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
                    </div>
                    <div class="flex items-center gap-3">
                        <x-primary-button>Save changes</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                    </div>
                </form>
            </x-slide-panel>
        @endforeach

        @if ($allCategories->isNotEmpty())
            <x-slide-panel name="item-create" title="New Item" :error-fields="['clothes_category_id', 'name', 'image']">
                <form method="POST" action="{{ route('catalog.items.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="item_category" value="Category" />
                        <select id="item_category" name="clothes_category_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                            @foreach ($allCategories as $option)
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

        @foreach ($clothingItems as $item)
            <x-slide-panel name="item-edit-{{ $item->id }}" title="Edit {{ $item->name }}" :open="$errors->any() && old('editing_item_id') == $item->id">
                <form method="POST" action="{{ route('catalog.categories.items.update', [$item->category, $item]) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="editing_item_id" value="{{ $item->id }}">
                    <div>
                        <x-input-label for="item_edit_name_{{ $item->id }}" value="Name" />
                        <x-text-input id="item_edit_name_{{ $item->id }}" name="name" type="text" class="block w-full" value="{{ $item->name }}" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="item_edit_image_{{ $item->id }}" value="Photo (optional)" />
                        @if ($itemImageUrl = \App\Support\MediaUrl::temporary($item->image_path))
                            <img src="{{ $itemImageUrl }}" alt="" class="w-16 h-16 rounded-lg object-cover mb-2">
                        @endif
                        <input id="item_edit_image_{{ $item->id }}" name="image" type="file" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-accent-soft file:text-accent-ink file:text-xs file:font-semibold">
                        <p class="text-xs text-ink-faint mt-1">Leave blank to keep the current photo.</p>
                        <x-input-error :messages="$errors->get('image')" class="mt-1.5" />
                    </div>
                    <div class="flex items-center gap-3">
                        <x-primary-button>Save changes</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-ink-muted hover:text-ink">Cancel</button>
                    </div>
                </form>
            </x-slide-panel>
        @endforeach
    @endcan
</x-app-layout>
