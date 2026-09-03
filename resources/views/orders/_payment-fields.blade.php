{{--
    Shared by the Record Payment panel and the Record Collection panel, so
    both render identical fields/validation-error wiring instead of two
    copies drifting apart. $prefix namespaces element ids since both panels
    can be on the page at once.

    Expects: $order, $orderDue, $prefix (string). $amountRequired (bool,
    default false) -- Record Payment requires an amount; Record Collection's
    payment is optional (pickup can be recorded with nothing owed changing hands).
    $dueOverride (float, default null) -- Record Collection may be settling
    the subscription cycle's balance instead of the order's own (a
    subscription order's flat fee lives on the cycle, not the order, so
    $orderDue alone is usually 0 there); when set, this is the balance the
    credit-applied cap is measured against instead of $orderDue.
--}}
@php
    $amountRequired = $amountRequired ?? false;
    $effectiveDue = $dueOverride ?? $orderDue;
@endphp
@if ($order->customer->store_credit_balance > 0)
    <div>
        <x-input-label for="{{ $prefix }}_credit_applied" value="Apply store credit" />
        <x-text-input id="{{ $prefix }}_credit_applied" name="credit_applied" type="number" step="0.01" min="0" max="{{ min($order->customer->store_credit_balance, $effectiveDue) }}" class="block w-full" />
        <p class="text-xs text-ink-faint mt-1">Of GMD {{ number_format($order->customer->store_credit_balance, 2) }} available.</p>
        <x-input-error :messages="$errors->get('credit_applied')" class="mt-1.5" />
    </div>
@endif

<div>
    <x-input-label for="{{ $prefix }}_amount" value="Amount collected (GMD)" />
    <x-text-input id="{{ $prefix }}_amount" name="amount" type="number" step="0.01" min="0" class="block w-full" :required="$amountRequired" />
    <x-input-error :messages="$errors->get('amount')" class="mt-1.5" />
</div>

<div>
    <x-input-label for="{{ $prefix }}_method" value="Method" />
    <select id="{{ $prefix }}_method" name="method" class="block w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
        <option value="cash">Cash</option>
        <option value="card">Card</option>
        <option value="mixed">Mixed</option>
    </select>
    <x-input-error :messages="$errors->get('method')" class="mt-1.5" />
</div>
