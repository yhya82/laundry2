@props(['status' => 'idle'])

@php
    // Card/panel artwork only -- distinct from the small flat <x-nav-icon
    // name="washer"> used in the sidebar and order-stage badges. Color comes
    // entirely from the wrapping text-* class (set below per status) so it
    // stays theme-aware in light/dark without per-instance gradient ids.
    $toneClass = match ($status) {
        'washing' => 'text-accent-ink',
        'retired' => 'text-ink-faint',
        default => 'text-ink-muted',
    };
@endphp

<svg {{ $attributes->merge(['class' => "w-full h-full $toneClass"]) }} viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
    <!-- isometric body -->
    <path d="M10 20L32 8l22 12v28L32 60 10 48V20z" fill="currentColor" fill-opacity="0.08" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
    <!-- top face -->
    <path d="M10 20L32 8l22 12-22 12-22-12z" fill="currentColor" fill-opacity="0.14" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
    <!-- right shaded face -->
    <path d="M32 32l22-12v28L32 60V32z" fill="currentColor" fill-opacity="0.05" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>

    <!-- control dots on the top face -->
    <circle cx="22" cy="17" r="1.4" fill="currentColor"/>
    <circle cx="27" cy="14.5" r="1.4" fill="currentColor"/>

    <!-- drum / porthole on the front-left face -->
    <circle cx="21" cy="40" r="9" fill="currentColor" fill-opacity="0.06" stroke="currentColor" stroke-width="1.6"/>
    <circle cx="21" cy="40" r="5.2" fill="none" stroke="currentColor" stroke-width="1.4"/>

    @if ($status === 'washing')
        <g stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
            <path d="M18 38.5c0-1.8 1.4-3 3-3s3 1.4 3 3-1.2 3.2-3 3.2">
                <animateTransform attributeName="transform" type="rotate" from="0 21 40" to="360 21 40" dur="2.2s" repeatCount="indefinite"/>
            </path>
        </g>
    @endif
</svg>
