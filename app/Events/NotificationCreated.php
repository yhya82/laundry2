<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;

    public int $notificationId;

    public string $title;

    public string $body;

    public function __construct(Notification $notification)
    {
        $this->userId = $notification->user_id;
        $this->notificationId = $notification->id;
        $this->title = $notification->title;
        $this->body = $notification->body;
    }

    public function broadcastOn(): array
    {
        return [new Channel('notifications.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notificationId,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
