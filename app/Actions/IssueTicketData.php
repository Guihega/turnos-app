<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketPriority;

final readonly class IssueTicketData
{
    public function __construct(
        public string $branchId,
        public string $queueId,
        public string $serviceId,
        public ?string $createdBy = null,
        public ?string $appointmentId = null,
        public ?string $customerName = null,
        public ?string $customerPhone = null,
        public ?string $customerEmail = null,
        public ?string $customerIdNumber = null,
        public ?TicketPriority $priority = null,
        public ?array $metadata = null,
    ) {}

    public static function fromRequest(array $validated, ?string $createdBy = null): self
    {
        return new self(
            branchId: $validated['branch_id'],
            queueId: $validated['queue_id'],
            serviceId: $validated['service_id'],
            createdBy: $createdBy,
            appointmentId: $validated['appointment_id'] ?? null,
            customerName: $validated['customer_name'] ?? null,
            customerPhone: $validated['customer_phone'] ?? null,
            customerEmail: $validated['customer_email'] ?? null,
            customerIdNumber: $validated['customer_id_number'] ?? null,
            priority: isset($validated['priority']) ? TicketPriority::from($validated['priority']) : null,
            metadata: $validated['metadata'] ?? null,
        );
    }
}
