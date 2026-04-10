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

class TicketCalled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->ticket->branch_id}"),
            new Channel("kiosk.{$this->ticket->branch_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.called';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticket->id,
            'display_number' => $this->ticket->display_number,
            'counter_number' => $this->ticket->counter?->number,
            'counter_name' => $this->ticket->counter?->name,
            'queue_name' => $this->ticket->queue?->name,
            'service_name' => $this->ticket->service?->name,
            'service_color' => $this->ticket->service?->color,
            'operator_name' => $this->ticket->servedBy?->name,
            'status' => 'called',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
