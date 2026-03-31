<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketTransferred;
use App\Models\Counter;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TransferTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepo,
    ) {}

    public function execute(
        string $ticketId,
        string $targetQueueId,
        string $operatorId,
        ?string $reason = null,
    ): Ticket {
        return DB::transaction(function () use ($ticketId, $targetQueueId, $operatorId, $reason) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticketId);

            if (!in_array($ticket->status, [TicketStatus::CALLED, TicketStatus::IN_PROGRESS])) {
                throw new RuntimeException('Solo se pueden transferir turnos llamados o en atención.');
            }

            // Mark original as transferred
            $ticket->transitionTo(TicketStatus::TRANSFERRED, $operatorId);

            // Free counter
            if ($ticket->counter_id) {
                Counter::where('id', $ticket->counter_id)->update([
                    'current_ticket_id' => null,
                    'status' => 'open',
                ]);
            }

            // Create new ticket in target queue with higher priority
            $sequence = $this->ticketRepo->getDailySequence($ticket->branch_id);
            $queue = \App\Models\Queue::findOrFail($targetQueueId);
            $ticketNumber = sprintf('%s-%03d', $queue->prefix, $sequence);

            $newTicket = $this->ticketRepo->create([
                'branch_id' => $ticket->branch_id,
                'queue_id' => $targetQueueId,
                'service_id' => $ticket->service_id,
                'ticket_number' => $ticketNumber,
                'daily_sequence' => $sequence,
                'display_number' => sprintf('%s-%s', $ticket->branch->code, $ticketNumber),
                'customer_name' => $ticket->customer_name,
                'customer_phone' => $ticket->customer_phone,
                'customer_email' => $ticket->customer_email,
                'customer_id_number' => $ticket->customer_id_number,
                'status' => TicketStatus::WAITING,
                'priority' => TicketPriority::HIGH,
                'priority_score' => TicketPriority::HIGH->weight(),
                'issued_at' => now(),
                'transferred_from_id' => $ticket->id,
                'transfer_count' => $ticket->transfer_count + 1,
                'notes' => $reason,
                'metadata' => array_merge($ticket->metadata ?? [], [
                    'transferred_from' => $ticket->display_number,
                    'transfer_reason' => $reason,
                ]),
            ]);

            TicketTransferred::dispatch($newTicket, $ticket);

            return $newTicket;
        });
    }
}
