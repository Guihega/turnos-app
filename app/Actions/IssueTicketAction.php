<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketIssued;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class IssueTicketAction
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepo,
    ) {}

    public function execute(IssueTicketData $data): Ticket
    {
        return DB::transaction(function () use ($data) {
            $branch = Branch::findOrFail($data->branchId);
            $queue = Queue::findOrFail($data->queueId);
            $service = Service::findOrFail($data->serviceId);

            // Validations
            $this->validateBranchCanIssue($branch);
            $this->validateQueueCapacity($queue);

            // Generate ticket number
            $sequence = $this->ticketRepo->getDailySequence($branch->id);
            $ticketNumber = sprintf('%s-%03d', $queue->prefix, $sequence);
            $displayNumber = sprintf('%s-%s', $branch->code, $ticketNumber);

            $priority = $data->priority ?? TicketPriority::NORMAL;

            $ticket = $this->ticketRepo->create([
                'branch_id' => $branch->id,
                'queue_id' => $queue->id,
                'service_id' => $service->id,
                'created_by' => $data->createdBy,
                'appointment_id' => $data->appointmentId,
                'ticket_number' => $ticketNumber,
                'daily_sequence' => $sequence,
                'display_number' => $displayNumber,
                'customer_name' => $data->customerName,
                'customer_phone' => $data->customerPhone,
                'customer_email' => $data->customerEmail,
                'customer_id_number' => $data->customerIdNumber,
                'status' => TicketStatus::WAITING,
                'priority' => $priority,
                'priority_score' => $priority->weight(),
                'issued_at' => now(),
                'metadata' => $data->metadata,
            ]);

            // Record initial event
            $ticket->events()->create([
                'user_id' => $data->createdBy,
                'event_type' => 'ticket_issued',
                'to_status' => TicketStatus::WAITING->value,
                'occurred_at' => now(),
                'payload' => [
                    'queue' => $queue->name,
                    'service' => $service->name,
                    'priority' => $priority->value,
                ],
            ]);

            TicketIssued::dispatch($ticket);

            return $ticket->load(['queue', 'service', 'branch']);
        });
    }

    private function validateBranchCanIssue(Branch $branch): void
    {
        if (!$branch->is_active) {
            throw new RuntimeException('La sucursal no está activa.');
        }

        if (!$branch->isOpen()) {
            throw new RuntimeException('La sucursal está cerrada en este momento.');
        }

        if ($branch->todayTicketCount() >= $branch->max_daily_tickets) {
            throw new RuntimeException('Se alcanzó el límite diario de turnos para esta sucursal.');
        }
    }

    private function validateQueueCapacity(Queue $queue): void
    {
        if (!$queue->is_active) {
            throw new RuntimeException('Esta cola no está activa.');
        }

        if ($queue->isFull()) {
            throw new RuntimeException('La cola está llena. Intente más tarde.');
        }
    }
}
