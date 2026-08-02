@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-xs text-ink-muted mb-1.5']) }}>
    {{ $value ?? $slot }}
</label>
