<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $order->receipt?->receipt_number ?? $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            max-width: 380px;
            margin: 2rem auto;
            padding: 0 1rem;
            color: #1a2430;
            font-size: 13px;
            line-height: 1.5;
        }
        .center { text-align: center; }
        .business-name { font-size: 18px; font-weight: bold; margin-bottom: 0.25rem; }
        .logo { max-width: 120px; max-height: 80px; object-fit: contain; margin: 0 auto 0.5rem; display: block; }
        .muted { color: #55647a; }
        hr { border: none; border-top: 1px dashed #8592a6; margin: 0.85rem 0; }
        .row { display: flex; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.25rem; }
        .row.total { font-weight: bold; font-size: 15px; }
        .line-item { margin-bottom: 0.6rem; }
        .line-item .name { font-weight: bold; }
        .clothes { margin-left: 0.5rem; color: #55647a; }
        .print-button {
            display: block;
            width: 100%;
            margin-top: 1.5rem;
            padding: 0.65rem;
            background: #1d4ed8;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            font-family: inherit;
        }
        @media print {
            body { margin: 0; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    @php
        // A subscription order's own subtotal is always 0 -- the flat
        // monthly fee lives on the cycle, not the visit -- so the figures a
        // customer actually cares about (what's the real total, what's been
        // paid, what's left) have to fold the cycle in too. Walk-in orders
        // have no cycle, so these just fall back to the order's own numbers
        // unchanged.
        $cycle = $order->subscriptionCycle();
        $grandTotal = (float) $order->total_amount + ($cycle->monthly_price_snapshot ?? 0);
        $combinedPaid = $order->amountPaid() + ($cycle?->amountPaid() ?? 0);
        $combinedDue = $order->balanceDue() + ($cycle?->balanceDue() ?? 0);
        $allPayments = $cycle
            ? $order->payments->concat($cycle->payments)->where('status', '!=', 'refunded')->sortBy('created_at')
            : $order->payments->where('status', '!=', 'refunded');
    @endphp

    <div class="center">
        @if (\App\Models\Setting::get('receipt.show_logo') === 'true' && \App\Models\Setting::get('branding.logo_path'))
            <img src="{{ Storage::url(\App\Models\Setting::get('branding.logo_path')) }}" alt="" class="logo">
        @endif
        <div class="business-name">{{ \App\Models\Setting::get('branding.business_name') ?? config('app.name') }}</div>
        @if ($address = \App\Models\Setting::get('branding.address'))
            <div class="muted">{{ $address }}</div>
        @endif
        @if ($phone = \App\Models\Setting::get('branding.phone'))
            <div class="muted">{{ $phone }}</div>
        @endif
        @if ($email = \App\Models\Setting::get('branding.email'))
            <div class="muted">{{ $email }}</div>
        @endif
        <div class="muted">{{ $order->order_source === 'subscription' ? 'Subscription Collection' : 'Walk-in Order' }}</div>
    </div>

    <hr>

    <div class="row"><span>Receipt #</span><span>{{ $order->receipt?->receipt_number ?? '—' }}</span></div>
    <div class="row"><span>Order #</span><span>{{ $order->order_number }}</span></div>
    <div class="row"><span>Date</span><span>{{ $order->created_at->format('Y-m-d H:i') }}</span></div>
    <div class="row"><span>Customer</span><span>{{ $order->customer?->full_name ?? 'Deleted customer' }}</span></div>

    <hr>

    @forelse ($order->packageLines as $line)
        <div class="line-item">
            <div class="row">
                <span class="name">{{ $line->package_name_snapshot }} × {{ $line->quantity }}</span>
                <span>GMD {{ number_format($line->package_price_snapshot * $line->quantity, 2) }}</span>
            </div>
            @foreach ($line->clothesLines as $clothes)
                <div class="clothes">
                    {{ $clothes->item_name_snapshot }} × {{ $clothes->quantity }}{{ $clothes->is_extra ? ' (extra)' : '' }}
                </div>
            @endforeach
        </div>
    @empty
        <div class="muted center">No items recorded.</div>
    @endforelse

    <hr>

    @if ($cycle)
        <div class="row"><span>Subscription Plan{{ $order->collection?->subscription?->subscriptionPackage?->name ? ' ('.$order->collection->subscription->subscriptionPackage->name.')' : '' }}</span><span>GMD {{ number_format($cycle->monthly_price_snapshot, 2) }}</span></div>
    @else
        <div class="row"><span>Subtotal</span><span>GMD {{ number_format($order->subtotal, 2) }}</span></div>
    @endif
    @if ($order->discount > 0)
        <div class="row"><span>Discount{{ $order->discount_reason ? ' ('.$order->discount_reason.')' : '' }}</span><span>− GMD {{ number_format($order->discount, 2) }}</span></div>
    @endif
    @if ($order->extra_charge > 0)
        <div class="row"><span>Extra charge{{ $order->extra_charge_reason ? ' ('.$order->extra_charge_reason.')' : '' }}</span><span>+ GMD {{ number_format($order->extra_charge, 2) }}</span></div>
    @endif
    @if ($order->cycle_overage_charge > 0)
        <div class="row"><span>Cycle overage charge</span><span>+ GMD {{ number_format($order->cycle_overage_charge, 2) }}</span></div>
    @endif

    <div class="row total"><span>{{ $cycle ? 'Cycle Total' : 'Total' }}</span><span>GMD {{ number_format($grandTotal, 2) }}</span></div>

    <hr>

    @forelse ($allPayments as $payment)
        <div class="row"><span>Paid ({{ ucfirst(str_replace('_', ' ', $payment->method)) }})</span><span>GMD {{ number_format($payment->amount, 2) }}</span></div>
    @empty
        <div class="muted">No payment recorded.</div>
    @endforelse

    <div class="row"><span>Amount Paid Now</span><span>GMD {{ number_format($combinedPaid, 2) }}</span></div>

    @if ($combinedDue > 0)
        <div class="row"><span>{{ $cycle ? 'Cycle Balance Due' : 'Balance Due at Pickup' }}</span><span>GMD {{ number_format($combinedDue, 2) }}</span></div>
    @endif

    <hr>

    <div class="center muted">{{ \App\Models\Setting::get('receipt.footer_message', 'Thank you for your business.') }}</div>

    <button type="button" class="print-button no-print" onclick="window.print()">Print</button>
</body>
</html>
