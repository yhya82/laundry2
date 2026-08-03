<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast immediately (no queue worker needed to see it) rather than
 * ShouldBroadcast -- this is a dev/demo environment with no queue worker
 * necessarily running, and the payload is small enough that synchronous
 * dispatch is not a performance concern.
 */
class OrderStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $orderId;

    public string $orderNumber;

    public ?string $fromStatus;

    public string $toStatus;

    public function __construct(Order $order, ?string $fromStatus)
    {
        $this->orderId = $order->id;
        $this->orderNumber = $order->order_number;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $order->status;
    }

    public function broadcastOn(): array
    {
        return [new Channel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'order.status-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'orderId' => $this->orderId,
            'orderNumber' => $this->orderNumber,
            'fromStatus' => $this->fromStatus,
            'toStatus' => $this->toStatus,
        ];
    }
}
