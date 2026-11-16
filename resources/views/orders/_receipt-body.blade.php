{{-- The receipt itself, shared by the printable page (orders/receipt) and
     the Terminal's receipt printer, so the two can never disagree. Styled by
     orders/_receipt-styles. Expects $order with customer, packageLines.clothesLines,
     payments, receipt, collection.subscriptionCycle.payments and
     collection.subscription.subscriptionPackage loaded. --}}
@php
    // A subscription order's own subtotal is always 0 -- the flat monthly
    // fee lives on the cycle, not the visit -- so the figures a customer
    // actually cares about (the real total, what's been paid, what's left)
    // fold the cycle in too. Walk-in orders have no cycle, so these fall
    // back to the order's own numbers unchanged.
    $cycle = $order->subscriptionCycle();
    $grandTotal = (float) $order->total_amount + ($cycle->monthly_price_snapshot ?? 0);
    $combinedPaid = $order->amountPaid() + ($cycle?->amountPaid() ?? 0);
    // combinedBalanceDue() (not $order->balanceDue() + cycle inline) so a
    // cancelled order's own share doesn't print as still owed.
    $combinedDue = $order->combinedBalanceDue();
    $allPayments = $cycle
        ? $order->payments->concat($cycle->payments)->where('status', '!=', 'refunded')->sortBy('created_at')
        : $order->payments->where('status', '!=', 'refunded');
@endphp
<div class="rcpt">
    <div class="rcpt-center">
        @if (\App\Models\Setting::get('receipt.show_logo') === 'true' && $receiptLogoUrl = \App\Support\MediaUrl::temporary(\App\Models\Setting::get('branding.logo_path')))
            <img src="{{ $receiptLogoUrl }}" alt="" class="rcpt-logo">
        @endif
        <div class="rcpt-business">{{ \App\Models\Setting::get('branding.business_name') ?? config('app.name') }}</div>
        @if ($address = \App\Models\Setting::get('branding.address'))
            <div class="rcpt-muted">{{ $address }}</div>
        @endif
        @if (\App\Models\Setting::get('receipt.show_phone') === 'true' && $phone = \App\Models\Setting::get('branding.phone'))
            <div class="rcpt-muted">{{ $phone }}</div>
        @endif
        @if ($email = \App\Models\Setting::get('branding.email'))
            <div class="rcpt-muted">{{ $email }}</div>
        @endif
        <div class="rcpt-muted">{{ $order->order_source === 'subscription' ? 'Subscription Collection' : 'Walk-in Order' }}</div>
    </div>

    <hr>

    <div class="rcpt-row"><span>Receipt #</span><span>{{ $order->receipt?->receipt_number ?? '—' }}</span></div>
    <div class="rcpt-row"><span>Order #</span><span>{{ $order->order_number }}</span></div>
    <div class="rcpt-row"><span>Date</span><span>{{ $order->created_at->format('Y-m-d H:i') }}</span></div>
    <div class="rcpt-row"><span>Customer</span><span>{{ $order->customer?->full_name ?? 'Deleted customer' }}</span></div>

    <hr>

    @forelse ($order->packageLines as $line)
        <div class="rcpt-line">
            <div class="rcpt-row">
                <span class="rcpt-name">{{ $line->package_name_snapshot }} × {{ $line->quantity }}</span>
                <span>GMD {{ number_format($line->package_price_snapshot * $line->quantity, 2) }}</span>
            </div>
            @foreach ($line->clothesLines as $clothes)
                <div class="rcpt-clothes">
                    {{ $clothes->item_name_snapshot }} × {{ $clothes->quantity }}{{ $clothes->is_extra ? ' (extra)' : '' }}
                </div>
            @endforeach
        </div>
    @empty
        <div class="rcpt-muted rcpt-center">No items recorded.</div>
    @endforelse

    <hr>

    @if ($cycle)
        <div class="rcpt-row"><span>Subscription Plan{{ $order->collection?->subscription?->subscriptionPackage?->name ? ' ('.$order->collection->subscription->subscriptionPackage->name.')' : '' }}</span><span>GMD {{ number_format($cycle->monthly_price_snapshot, 2) }}</span></div>
    @else
        <div class="rcpt-row"><span>Subtotal</span><span>GMD {{ number_format($order->subtotal, 2) }}</span></div>
    @endif

    @if ($order->discount > 0)
        <div class="rcpt-row"><span>Discount{{ $order->discount_reason ? ' ('.$order->discount_reason.')' : '' }}</span><span>− GMD {{ number_format($order->discount, 2) }}</span></div>
    @endif
    @if ($order->extra_charge > 0)
        <div class="rcpt-row"><span>Extra charge{{ $order->extra_charge_reason ? ' ('.$order->extra_charge_reason.')' : '' }}</span><span>+ GMD {{ number_format($order->extra_charge, 2) }}</span></div>
    @endif
    @if ($order->cycle_overage_charge > 0)
        <div class="rcpt-row"><span>Cycle overage charge</span><span>+ GMD {{ number_format($order->cycle_overage_charge, 2) }}</span></div>
    @endif

    <div class="rcpt-row rcpt-total"><span>{{ $cycle ? 'Cycle Total' : 'Total' }}</span><span>GMD {{ number_format($grandTotal, 2) }}</span></div>

    <hr>

    @forelse ($allPayments as $payment)
        <div class="rcpt-row"><span>Paid ({{ $payment->methodLabel() }})</span><span>GMD {{ number_format($payment->amount, 2) }}</span></div>
    @empty
        <div class="rcpt-muted">No payment recorded.</div>
    @endforelse

    <div class="rcpt-row"><span>Amount Paid Now</span><span>GMD {{ number_format($combinedPaid, 2) }}</span></div>

    @if ($combinedDue > 0)
        <div class="rcpt-row"><span>{{ $cycle ? 'Cycle Balance Due' : 'Balance Due at Pickup' }}</span><span>GMD {{ number_format($combinedDue, 2) }}</span></div>
    @endif

    <hr>

    <div class="rcpt-center rcpt-muted">{{ \App\Models\Setting::get('receipt.footer_message', 'Thank you for your business.') }}</div>
</div>
