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

class TicketIssued implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
    ) {}

    /**
     * Broadcast to the branch channel (operators) and a public kiosk channel (display screens).
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->ticket->branch_id}"),
            new Channel("kiosk.{$this->ticket->branch_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.issued';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticket->id,
            'display_number' => $this->ticket->display_number,
            'queue_name' => $this->ticket->queue?->name,
            'service_name' => $this->ticket->service?->name,
            'service_color' => $this->ticket->service?->color,
            'status' => $this->ticket->status->value,
            'waiting_count' => $this->ticket->branch?->activeWaitingCount(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
