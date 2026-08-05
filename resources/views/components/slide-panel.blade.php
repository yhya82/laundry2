@props(['name', 'title', 'errorFields' => [], 'open' => null])

<div
    x-data="{ open: @js($open ?? collect($errorFields)->contains(fn ($f) => $errors->has($f))) }"
    x-on:open-panel.window="$event.detail === '{{ $name }}' && (open = true)"
    x-on:close-panel.window="$event.detail === '{{ $name }}' && (open = false)"
    x-on:keydown.escape.window="open = false"
>
    <div
        x-show="open" x-cloak
        class="fixed inset-0 bg-ink/30 z-40"
        x-transition.opacity
        @click="open = false"
    ></div>

    <div
        x-show="open" x-cloak
        class="fixed inset-y-0 right-0 z-50 w-full sm:w-96 bg-surface border-l border-line shadow-xl flex flex-col"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
    >
        <div class="flex items-center justify-between px-5 py-4 border-b border-line flex-none">
            <h3 class="font-semibold text-ink">{{ $title }}</h3>
            <button type="button" @click="open = false" class="text-ink-faint hover:text-ink" aria-label="Close">✕</button>
        </div>
        <div class="flex-1 overflow-y-auto p-5">
            {{ $slot }}
        </div>
    </div>
</div>
