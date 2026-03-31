<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Events\TicketCalled;
use App\Models\Counter;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CallNextTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepo,
    ) {}

    /**
     * Call the next ticket from the specified queues or auto-detect from counter config.
     */
    public function execute(string $counterId, string $operatorId, ?string $queueId = null): Ticket
    {
        return DB::transaction(function () use ($counterId, $operatorId, $queueId) {
            // Ensure operator doesn't have an active ticket
            $activeTicket = $this->ticketRepo->getOperatorActiveTicket($operatorId);
            if ($activeTicket) {
                throw new RuntimeException(
                    "Ya tiene un turno activo: {$activeTicket->display_number}. Complételo antes de llamar otro."
                );
            }

            $counter = Counter::lockForUpdate()->findOrFail($counterId);

            if (!$counter->isAvailable() && $counter->current_operator_id === $operatorId) {
                // Counter is assigned to this operator but busy — might need to complete current
            }

            // Find next ticket
            $ticket = $queueId
                ? $this->ticketRepo->getNextInQueue($queueId)
                : $this->findNextFromCounterQueues($counter);

            if (!$ticket) {
                throw new RuntimeException('No hay turnos en espera.');
            }

            // Transition ticket
            $ticket->transitionTo(TicketStatus::CALLED, $operatorId);
            $ticket->update([
                'served_by' => $operatorId,
                'counter_id' => $counter->id,
            ]);

            // Update counter
            $counter->update([
                'current_ticket_id' => $ticket->id,
                'current_operator_id' => $operatorId,
                'status' => 'serving',
            ]);

            TicketCalled::dispatch($ticket->fresh(['queue', 'service', 'counter', 'servedBy']));

            return $ticket;
        });
    }

    private function findNextFromCounterQueues(Counter $counter): ?Ticket
    {
        $queueIds = $counter->serves_queues ?? [];

        if (empty($queueIds)) {
            // Counter serves all queues in the branch
            return Ticket::where('branch_id', $counter->branch_id)
                ->where('status', TicketStatus::WAITING)
                ->orderByDesc('priority_score')
                ->orderBy('issued_at')
                ->lockForUpdate()
                ->first();
        }

        return Ticket::whereIn('queue_id', $queueIds)
            ->where('status', TicketStatus::WAITING)
            ->orderByDesc('priority_score')
            ->orderBy('issued_at')
            ->lockForUpdate()
            ->first();
    }
}
