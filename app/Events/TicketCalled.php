<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCalled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Ticket $ticket) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("branch.{$this->ticket->branch_id}"),
            new Channel("display.{$this->ticket->branch_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'display_number' => $this->ticket->display_number,
            'counter' => $this->ticket->counter?->number,
            'counter_name' => $this->ticket->counter?->name,
            'service' => $this->ticket->service?->name,
            'operator' => $this->ticket->servedBy?->name,
        ];
    }
}
