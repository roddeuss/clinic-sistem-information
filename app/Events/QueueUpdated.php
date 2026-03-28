<?php

namespace App\Events;

use App\Models\QueueTicket;
use App\Services\QueueBoardService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        private readonly QueueTicket $queueTicket,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('queues.branch.' . $this->queueTicket->branch_id),
            new Channel('queues.section.' . $this->queueTicket->section_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'queue' => app(QueueBoardService::class)->ticketPayload($this->queueTicket),
        ];
    }
}
