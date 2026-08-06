<?php

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $subscriptionId;

    public string $toStatus;

    public function __construct(Subscription $subscription)
    {
        $this->subscriptionId = $subscription->id;
        $this->toStatus = $subscription->status;
    }

    public function broadcastOn(): array
    {
        return [new Channel('subscriptions')];
    }

    public function broadcastAs(): string
    {
        return 'subscription.status-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'subscriptionId' => $this->subscriptionId,
            'toStatus' => $this->toStatus,
        ];
    }
}
