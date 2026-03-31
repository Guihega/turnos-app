<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
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
        return [new Channel("branch.{$this->newTicket->branch_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'new_ticket_id' => $this->newTicket->id,
            'new_display_number' => $this->newTicket->display_number,
            'original_display_number' => $this->originalTicket->display_number,
            'target_queue' => $this->newTicket->queue?->name,
        ];
    }
}
