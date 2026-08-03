@props(['panel'])

<button
    type="button"
    @click="$dispatch('open-panel', '{{ $panel }}')"
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-accent border border-transparent rounded-lg font-semibold text-sm text-white hover:opacity-90']) }}
>
    {{ $slot }}
</button>
