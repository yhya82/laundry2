<x-app-layout>
    <x-slot name="header">Clothes Categories</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 text-sm text-critical bg-critical-soft border border-critical/30 rounded-lg px-4 py-2.5">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-surface border border-line rounded-2xl overflow-hidden">
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

        @can('catalog.manage')
            <div class="space-y-5 h-fit">
                <div class="bg-surface border border-line rounded-2xl p-6">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">New category</div>
                    <form method="POST" action="{{ route('catalog.categories.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required />
                        </div>
                        <div>
                            <x-input-label for="description" value="Description (optional)" />
                            <x-text-input id="description" name="description" type="text" class="block w-full" value="{{ old('description') }}" />
                        </div>
                        <x-primary-button class="w-full">Add category</x-primary-button>
                    </form>
                </div>

                <div class="bg-surface border border-line rounded-2xl p-6">
                    <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">New item</div>
                    @if ($categories->isEmpty())
                        <p class="text-sm text-ink-faint">Add a category first, then items can be added here.</p>
                    @else
                        <form method="POST" action="{{ route('catalog.items.store') }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="item_category" value="Category" />
                                <select id="item_category" name="clothes_category_id" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent" required>
                                    @foreach ($categories as $option)
                                        <option value="{{ $option->id }}" @selected(old('clothes_category_id') == $option->id)>{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="item_name" value="Name" />
                                <x-text-input id="item_name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required />
                            </div>
                            <div>
                                <x-input-label for="item_image" value="Photo (optional)" />
                                <input id="item_image" name="image" type="file" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-accent-soft file:text-accent-ink file:text-xs file:font-semibold">
                            </div>
                            <x-primary-button class="w-full">Add item</x-primary-button>
                        </form>
                    @endif
                </div>
            </div>
        @endcan
    </div>
</x-app-layout>
