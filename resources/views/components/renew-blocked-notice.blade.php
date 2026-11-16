@props(['customer', 'link' => true])

{{-- Shown in place of a Renew button while the customer owes anything
     (an unpaid order or subscription cycle). SubscriptionController::renew()
     refuses the renewal server-side regardless; this just says so up front. --}}
<div {{ $attributes->merge(['class' => 'rounded-xl border border-critical/30 bg-critical/5 p-3']) }}>
    <p class="text-sm font-medium text-critical">Outstanding balance — renewal unavailable</p>
    <p class="text-xs text-ink-muted mt-0.5">Settle what this customer owes before starting a new cycle.</p>
    @if ($link && $customer)
        <a href="{{ route('customers.show', $customer) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-critical hover:underline">
            Settle balance <span aria-hidden="true">&rarr;</span>
        </a>
    @endif
</div>
