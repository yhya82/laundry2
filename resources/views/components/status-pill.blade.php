@props(['status'])

@php
$tones = [
    'received' => 'neutral', 'refunded' => 'neutral', 'closed' => 'neutral', 'pending_review' => 'neutral', 'paused' => 'neutral', 'normal' => 'neutral',
    'sorting' => 'active', 'washing' => 'active', 'drying' => 'active', 'ironing' => 'active', 'packaging' => 'active',
    'under_investigation' => 'active', 'approved' => 'active', 'partially_refunded' => 'active', 'scheduled' => 'active', 'partial' => 'active',
    'completed' => 'success', 'resolved' => 'success', 'active' => 'success', 'collected' => 'success', 'paid' => 'success', 'collection' => 'success',
    'cancelled' => 'critical', 'rejected' => 'critical', 'skipped' => 'critical', 'unpaid' => 'critical', 'high' => 'critical',
];
$tone = $tones[$status] ?? 'neutral';
$classes = [
    'neutral' => 'bg-pill-bg text-pill-ink',
    'active' => 'bg-accent-soft text-accent-ink',
    'success' => 'bg-success-soft text-success',
    'critical' => 'bg-critical-soft text-critical',
][$tone];
// 'collection' is the order status enum value (processing done, picked
// up) -- reads as "Collected" everywhere it's shown, matching the order
// timeline's own past-tense labels, not the raw noun form of the value.
$labels = ['collection' => 'Collected'];
$label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 font-mono text-xs font-semibold px-2.5 py-1 rounded-full $classes"]) }}>
    {{ $label }}
</span>
