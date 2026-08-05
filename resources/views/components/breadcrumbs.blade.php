@props(['items'])

<nav class="flex items-center gap-1.5 text-sm mb-4 flex-wrap" aria-label="Breadcrumb">
    @foreach ($items as $item)
        @if (!$loop->first)
            <span class="text-ink-faint">/</span>
        @endif
        @if ($item['url'] ?? null)
            <a href="{{ $item['url'] }}" class="text-ink-faint hover:text-accent-ink">{{ $item['label'] }}</a>
        @else
            <span class="text-ink font-medium">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
