@if (session('status'))
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 5000)"
        x-show="show"
        x-cloak
        x-transition
        class="fixed top-4 right-4 z-[60] max-w-sm w-full"
    >
        <div class="flex items-start gap-2 text-sm text-success bg-surface border border-success/30 shadow-lg rounded-lg px-4 py-3">
            <span class="flex-none">✓</span>
            <span class="flex-1">{{ session('status') }}</span>
            <button type="button" @click="show = false" class="text-ink-faint hover:text-ink flex-none" aria-label="Dismiss">✕</button>
        </div>
    </div>
@endif

@if ($errors->any())
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 8000)"
        x-show="show"
        x-cloak
        x-transition
        class="fixed top-4 right-4 z-[60] max-w-sm w-full"
    >
        <div class="flex items-start gap-2 text-sm text-critical bg-surface border border-critical/30 shadow-lg rounded-lg px-4 py-3">
            <span class="flex-none">⚠</span>
            <span class="flex-1">{{ $errors->first() }}</span>
            <button type="button" @click="show = false" class="text-ink-faint hover:text-ink flex-none" aria-label="Dismiss">✕</button>
        </div>
    </div>
@endif
