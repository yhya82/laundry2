<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Receipt;

class Numbering
{
    /**
     * ORD-YYYYMMDD-#### sequential per day. Called inside the order-creation
     * transaction, so the count-then-insert is safe from same-request races;
     * a genuine concurrent-request collision would violate orders.order_number
     * (unique) and simply fail loudly rather than silently duplicate.
     */
    public static function nextOrderNumber(): string
    {
        $today = now()->format('Ymd');
        $count = Order::where('order_number', 'like', "ORD-{$today}-%")->lockForUpdate()->count();

        return sprintf('ORD-%s-%04d', $today, $count + 1);
    }

    public static function nextReceiptNumber(): string
    {
        $today = now()->format('Ymd');
        $count = Receipt::where('receipt_number', 'like', "RCT-{$today}-%")->lockForUpdate()->count();

        return sprintf('RCT-%s-%04d', $today, $count + 1);
    }
}
