@props(['name'])

<svg {{ $attributes->merge(['class' => 'w-4 h-4 flex-none']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('chart')
            <rect x="4" y="12" width="3" height="8" rx="0.5"/>
            <rect x="10.5" y="8" width="3" height="12" rx="0.5"/>
            <rect x="17" y="4" width="3" height="16" rx="0.5"/>
            @break

        @case('people')
            <circle cx="9" cy="7" r="3"/>
            <path d="M3 20c0-3.5 2.5-6 6-6s6 2.5 6 6"/>
            <circle cx="17" cy="8" r="2.3"/>
            <path d="M15 20c.3-2.6 2-4.5 4.5-4.8"/>
            @break

        @case('clipboard')
            <rect x="5" y="4" width="14" height="17" rx="2"/>
            <rect x="9" y="2.5" width="6" height="3" rx="1"/>
            <line x1="8" y1="10" x2="16" y2="10"/>
            <line x1="8" y1="13.5" x2="16" y2="13.5"/>
            <line x1="8" y1="17" x2="13" y2="17"/>
            @break

        @case('truck')
            <rect x="2" y="9" width="11" height="7" rx="1"/>
            <path d="M13 11h4l3 3v2h-7z"/>
            <circle cx="6" cy="18" r="1.6"/>
            <circle cx="16.5" cy="18" r="1.6"/>
            @break

        @case('repeat')
            <path d="M4 7h11a3 3 0 013 3v1"/>
            <polyline points="15 4 18 7 15 10"/>
            <path d="M20 17H9a3 3 0 01-3-3v-1"/>
            <polyline points="9 20 6 17 9 14"/>
            @break

        @case('shirt')
            <path d="M8 4l4 2 4-2 4 3-2.5 3V20a1 1 0 01-1 1H7.5a1 1 0 01-1-1V10L4 7z"/>
            @break

        @case('wallet')
            <rect x="3" y="6" width="18" height="13" rx="2"/>
            <path d="M3 10h18"/>
            <circle cx="17" cy="14" r="1.3" fill="currentColor" stroke="none"/>
            @break

        @case('receipt')
            <path d="M6 3h12v17l-2-1.3-2 1.3-2-1.3-2 1.3-2-1.3L6 20V3z"/>
            <line x1="8.5" y1="8" x2="15.5" y2="8"/>
            <line x1="8.5" y1="11.5" x2="15.5" y2="11.5"/>
            @break

        @case('alert')
            <path d="M12 4l9 15H3z"/>
            <line x1="12" y1="10" x2="12" y2="14"/>
            <circle cx="12" cy="17" r="0.9" fill="currentColor" stroke="none"/>
            @break

        @case('analytics')
            <polyline points="4 16 9 11 13 14 20 6"/>
            <circle cx="9" cy="11" r="1" fill="currentColor" stroke="none"/>
            <circle cx="13" cy="14" r="1" fill="currentColor" stroke="none"/>
            <circle cx="20" cy="6" r="1" fill="currentColor" stroke="none"/>
            @break

        @case('users')
            <circle cx="8" cy="8" r="3"/>
            <circle cx="17" cy="9" r="2.5"/>
            <path d="M2.5 20c0-3.5 2.5-6 5.5-6s5.5 2.5 5.5 6"/>
            <path d="M14.5 20c.3-2.8 2-4.7 4.5-5"/>
            @break

        @case('log')
            <line x1="9" y1="6" x2="20" y2="6"/>
            <line x1="9" y1="12" x2="20" y2="12"/>
            <line x1="9" y1="18" x2="20" y2="18"/>
            <circle cx="5" cy="6" r="1" fill="currentColor" stroke="none"/>
            <circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/>
            <circle cx="5" cy="18" r="1" fill="currentColor" stroke="none"/>
            @break

        @case('gear')
            <circle cx="12" cy="12" r="3"/>
            <circle cx="12" cy="12" r="7.5" stroke-dasharray="2 2.2"/>
            @break

        @case('edit')
            <path d="M4 20l1-4 11-11 3 3-11 11-4 1z"/>
            <line x1="14" y1="6" x2="18" y2="10"/>
            @break

        @case('arrow-right')
            <line x1="4" y1="12" x2="18" y2="12"/>
            <polyline points="13 7 18 12 13 17"/>
            @break

        @case('box')
            <path d="M3 8l9-4 9 4-9 4-9-4z"/>
            <path d="M3 8v9l9 4 9-4V8"/>
            <line x1="12" y1="12" x2="12" y2="21"/>
            @break

        @case('washer')
            <rect x="4" y="3" width="16" height="18" rx="2"/>
            <circle cx="7" cy="6" r="0.6" fill="currentColor" stroke="none"/>
            <circle cx="9.5" cy="6" r="0.6" fill="currentColor" stroke="none"/>
            <circle cx="12" cy="14" r="4.5"/>
            <circle cx="12" cy="14" r="1.8"/>
            @break

        @case('dryer')
            <rect x="4" y="3" width="16" height="18" rx="2"/>
            <circle cx="7" cy="6" r="0.6" fill="currentColor" stroke="none"/>
            <circle cx="12" cy="14" r="4.5"/>
            <path d="M9 14a3 3 0 006 0"/>
            @break

        @case('iron')
            <path d="M4 16c0-4 2-8 7-8h5a3 3 0 013 3v2a3 3 0 01-3 3H7z"/>
            <line x1="6" y1="19" x2="17" y2="19"/>
            <circle cx="16" cy="10" r="0.6" fill="currentColor" stroke="none"/>
            @break

        @case('gift')
            <rect x="4" y="9" width="16" height="11" rx="1"/>
            <line x1="4" y1="13" x2="20" y2="13"/>
            <line x1="12" y1="9" x2="12" y2="20"/>
            <path d="M8 9c-1.5 0-2.5-1-2.5-2.5S6.5 4 8 4c2 0 4 2.5 4 5"/>
            <path d="M16 9c1.5 0 2.5-1 2.5-2.5S17.5 4 16 4c-2 0-4 2.5-4 5"/>
            @break

        @case('check')
            <polyline points="5 13 10 18 19 7"/>
            @break

        @case('trash')
            <path d="M4 7h16"/>
            <path d="M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
            <path d="M6 7l1 13a2 2 0 002 2h6a2 2 0 002-2l1-13"/>
            <line x1="10" y1="11" x2="10" y2="17"/>
            <line x1="14" y1="11" x2="14" y2="17"/>
            @break

        @case('pause')
            <line x1="9" y1="5" x2="9" y2="19"/>
            <line x1="15" y1="5" x2="15" y2="19"/>
            @break

        @case('play')
            <polygon points="6 4 20 12 6 20" fill="currentColor" stroke="none"/>
            @break

        @case('x')
            <line x1="6" y1="6" x2="18" y2="18"/>
            <line x1="18" y1="6" x2="6" y2="18"/>
            @break

        @case('phone')
            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
            @break

        @case('mail')
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="M22 6l-10 7L2 6"/>
            @break

        @case('map-pin')
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
            <circle cx="12" cy="10" r="3"/>
            @break

        @case('user')
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
            @break

        @case('zap')
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            @break

        @default
            <circle cx="12" cy="12" r="8"/>
    @endswitch
</svg>
