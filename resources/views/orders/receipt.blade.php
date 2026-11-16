<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $order->receipt?->receipt_number ?? $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            max-width: 380px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        @include('orders._receipt-styles')
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
            font-family: 'Courier New', Courier, monospace;
        }
        @media print {
            body { margin: 0; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    {{-- Shared with the Terminal's receipt printer -- see orders/_receipt-body. --}}
    @include('orders._receipt-body', ['order' => $order])

    <button type="button" class="print-button no-print" onclick="window.print()">Print</button>
</body>
</html>
