@props(['status'])

@php
$tones = [
    'received' => 'neutral', 'refunded' => 'neutral', 'closed' => 'neutral', 'pending_review' => 'neutral',
    'sorting' => 'active', 'washing' => 'active', 'drying' => 'active', 'ironing' => 'active', 'packaging' => 'active',
    'under_investigation' => 'active', 'approved' => 'active', 'partially_refunded' => 'active',
    'completed' => 'success', 'resolved' => 'success',
    'cancelled' => 'critical', 'rejected' => 'critical',
];
$tone = $tones[$status] ?? 'neutral';
$classes = [
    'neutral' => 'bg-pill-bg text-pill-ink',
    'active' => 'bg-accent-soft text-accent-ink',
    'success' => 'bg-success-soft text-success',
    'critical' => 'bg-critical-soft text-critical',
][$tone];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full $classes"]) }}>
    {{ ucfirst(str_replace('_', ' ', $status)) }}
</span>
