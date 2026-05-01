<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TicketRepositoryInterface
{
    public function findById(string $id): ?Ticket;

    public function findByDisplayNumber(string $branchId, string $displayNumber): ?Ticket;

    public function getActiveForBranch(string $branchId): Collection;

    public function getWaitingForQueue(string $queueId): Collection;

    public function getNextInQueue(string $queueId): ?Ticket;

    public function getForBranchPaginated(
        string $branchId,
        array $filters = [],
        int $perPage = 25
    ): LengthAwarePaginator;

    public function getDailySequence(string $branchId): int;

    public function getTodayStats(string $branchId): array;

    public function getAverageWaitTime(string $branchId, ?string $queueId = null): int;

    public function create(array $data): Ticket;

    public function update(Ticket $ticket, array $data): Ticket;

    public function getOperatorActiveTicket(string $userId): ?Ticket;

    public function getStatusCounts(string $branchId): Collection;
}
