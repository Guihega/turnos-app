<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Events\TicketCompleted;
use App\Models\Counter;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CompleteTicketAction
{
    public function execute(string $ticketId, string $operatorId, ?int $rating = null, ?string $notes = null): Ticket
    {
        return DB::transaction(function () use ($ticketId, $operatorId, $rating, $notes) {
            $ticket = Ticket::lockForUpdate()->findOrFail($ticketId);

            if ($ticket->served_by !== $operatorId) {
                throw new RuntimeException('Solo el operador asignado puede completar este turno.');
            }

            if (! $ticket->status->canTransitionTo(TicketStatus::COMPLETED)) {
                throw new RuntimeException("No se puede completar un turno en estado {$ticket->status->label()}.");
            }

            $ticket->transitionTo(TicketStatus::COMPLETED, $operatorId);

            if ($rating !== null) {
                $ticket->update(['rating' => $rating]);
            }

            if ($notes !== null) {
                $ticket->update(['notes' => $notes]);
            }

            // Free up the counter
            if ($ticket->counter_id) {
                Counter::where('id', $ticket->counter_id)->update([
                    'current_ticket_id' => null,
                    'status' => 'open',
                ]);
            }

            TicketCompleted::dispatch($ticket->fresh(['queue', 'service', 'servedBy', 'branch']));

            return $ticket;
        });
    }
}
