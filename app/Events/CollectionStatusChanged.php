<?php

namespace App\Events;

use App\Models\Collection;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CollectionStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $collectionId;

    public string $toStatus;

    public function __construct(Collection $collection)
    {
        $this->collectionId = $collection->id;
        $this->toStatus = $collection->status;
    }

    public function broadcastOn(): array
    {
        return [new Channel('collections')];
    }

    public function broadcastAs(): string
    {
        return 'collection.status-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'collectionId' => $this->collectionId,
            'toStatus' => $this->toStatus,
        ];
    }
}
