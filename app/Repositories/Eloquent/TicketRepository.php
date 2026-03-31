<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TicketRepository implements TicketRepositoryInterface
{
    public function __construct(
        private readonly Ticket $model,
    ) {}

    public function findById(string $id): ?Ticket
    {
        return $this->model
            ->with(['queue', 'service', 'counter', 'servedBy'])
            ->find($id);
    }

    public function findByDisplayNumber(string $branchId, string $displayNumber): ?Ticket
    {
        return $this->model
            ->where('branch_id', $branchId)
            ->where('display_number', $displayNumber)
            ->whereDate('created_at', today())
            ->first();
    }

    public function getActiveForBranch(string $branchId): Collection
    {
        return $this->model
            ->with(['queue', 'service', 'counter', 'servedBy'])
            ->forBranch($branchId)
            ->active()
            ->byPriority()
            ->get();
    }

    public function getWaitingForQueue(string $queueId): Collection
    {
        return $this->model
            ->with(['service'])
            ->where('queue_id', $queueId)
            ->waiting()
            ->byPriority()
            ->get();
    }

    public function getNextInQueue(string $queueId): ?Ticket
    {
        return $this->model
            ->where('queue_id', $queueId)
            ->where('status', TicketStatus::WAITING)
            ->orderByDesc('priority_score')
            ->orderBy('issued_at')
            ->first();
    }

    public function getForBranchPaginated(
        string $branchId,
        array $filters = [],
        int $perPage = 25
    ): LengthAwarePaginator {
        $query = $this->model
            ->with(['queue', 'service', 'servedBy'])
            ->forBranch($branchId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['queue_id'])) {
            $query->where('queue_id', $filters['queue_id']);
        }

        if (isset($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['served_by'])) {
            $query->where('served_by', $filters['served_by']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('display_number', 'ILIKE', "%{$search}%")
                    ->orWhere('customer_name', 'ILIKE', "%{$search}%")
                    ->orWhere('customer_phone', 'ILIKE', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function getDailySequence(string $branchId): int
    {
        $key = "branch:{$branchId}:daily_seq:" . today()->format('Y-m-d');

        // Use Redis atomic increment for concurrency safety
        return (int) Cache::lock("{$key}:lock", 5)->block(3, function () use ($branchId, $key) {
            $current = Cache::get($key, 0);
            $next = $current + 1;
            Cache::put($key, $next, now()->endOfDay());
            return $next;
        });
    }

    public function getTodayStats(string $branchId): array
    {
        $cacheKey = "branch:{$branchId}:today_stats";

        return Cache::remember($cacheKey, 30, function () use ($branchId) {
            $today = today();

            return DB::table('tickets')
                ->where('branch_id', $branchId)
                ->whereDate('created_at', $today)
                ->selectRaw("
                    COUNT(*) as total_issued,
                    COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting,
                    COUNT(CASE WHEN status = 'called' THEN 1 END) as called,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
                    COALESCE(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL), 0) as avg_wait_time,
                    COALESCE(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL), 0) as avg_service_time,
                    COALESCE(MAX(wait_time_seconds), 0) as max_wait_time,
                    COALESCE(AVG(rating) FILTER (WHERE rating IS NOT NULL), 0) as avg_rating,
                    COUNT(rating) as ratings_count
                ")
                ->first()
                ?->toArray() ?? [];
        });
    }

    public function getAverageWaitTime(string $branchId, ?string $queueId = null): int
    {
        $query = $this->model
            ->forBranch($branchId)
            ->today()
            ->whereNotNull('wait_time_seconds');

        if ($queueId) {
            $query->where('queue_id', $queueId);
        }

        return (int) $query->avg('wait_time_seconds');
    }

    public function create(array $data): Ticket
    {
        return $this->model->create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);
        return $ticket->fresh();
    }

    public function getOperatorActiveTicket(string $userId): ?Ticket
    {
        return $this->model
            ->where('served_by', $userId)
            ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_PROGRESS])
            ->first();
    }

    public function getStatusCounts(string $branchId): Collection
    {
        return $this->model
            ->forBranch($branchId)
            ->today()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');
    }
}
