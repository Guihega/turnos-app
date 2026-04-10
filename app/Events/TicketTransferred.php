<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketTransferred implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $newTicket,
        public readonly Ticket $originalTicket,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->newTicket->branch_id}"),
            new Channel("kiosk.{$this->newTicket->branch_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.transferred';
    }

    public function broadcastWith(): array
    {
        return [
            'new_ticket' => [
                'id' => $this->newTicket->id,
                'display_number' => $this->newTicket->display_number,
                'queue_name' => $this->newTicket->queue?->name,
            ],
            'original_display_number' => $this->originalTicket->display_number,
            'status' => 'transferred',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
