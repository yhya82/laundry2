<x-app-layout>
    <x-slot name="header">{{ $category->name }}</x-slot>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success/30 rounded-lg px-4 py-2.5">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 text-sm text-critical bg-critical-soft border border-critical/30 rounded-lg px-4 py-2.5">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <a href="{{ route('catalog.categories') }}" class="text-sm text-ink-muted hover:text-ink mb-5 inline-block">&larr; All categories</a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-4">
            @forelse ($category->clothingItems as $item)
                <div class="bg-surface border border-line rounded-2xl overflow-hidden">
                    <div class="aspect-square bg-surface-2 flex items-center justify-center">
                        @if ($item->image_path)
                            <img src="{{ Storage::url($item->image_path) }}" alt="{{ $item->name }}" class="w-full h-full object-cover">
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
            <div class="bg-surface border border-line rounded-2xl p-6 h-fit">
                <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-4">New item</div>
                <form method="POST" action="{{ route('catalog.categories.items.store', $category) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="block w-full" value="{{ old('name') }}" required />
                    </div>
                    <div>
                        <x-input-label for="image" value="Photo (optional)" />
                        <input id="image" name="image" type="file" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-accent-soft file:text-accent-ink file:text-xs file:font-semibold">
                    </div>
                    <x-primary-button class="w-full">Add item</x-primary-button>
                </form>
            </div>
        @endcan
    </div>
</x-app-layout>
