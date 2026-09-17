<?php
namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationDispatcher;

/**
 * OrderStatusChanged fires on every transition, from all three places
 * OrderController changes an order's status -- filtering to 'completed'
 * here means one hook point covers all of them, instead of duplicating
 * this check in each controller method. Every user with orders.manage
 * gets notified, including whoever just made the change themselves.
 */
class NotifyStaffOrderCompleted
{
    public function __construct(protected NotificationDispatcher $notifications)
    {
    }

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->toStatus !== 'completed') {
            return;
        }

        $order = Order::with('customer')->find($event->orderId);

        if (! $order) {
            return;
        }

        User::where('is_active', true)->permission('orders.manage')->get()->each(
            fn (User $staff) => $this->notifications->toStaff(
                $staff,
                'Order completed',
                "{$order->order_number} for {$order->customer->full_name} is ready for collection."
            )
        );
    }
}
