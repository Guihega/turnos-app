<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\MetricsSnapshot;
use App\Models\Ticket;
use App\Repositories\Contracts\MetricsRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MetricsRepository implements MetricsRepositoryInterface
{
    public function getDashboardMetrics(string $branchId, string $period = 'today'): array
    {
        $cacheKey = "dashboard:{$branchId}:{$period}";
        $ttl = $period === 'today' ? 30 : 300;

        return Cache::remember($cacheKey, $ttl, function () use ($branchId, $period) {
            [$from, $to] = $this->resolvePeriod($period);

            $current = $this->getPeriodStats($branchId, $from, $to);
            $previousFrom = $from->copy()->subDays($from->diffInDays($to) + 1);
            $previous = $this->getPeriodStats($branchId, $previousFrom, $from->copy()->subDay());

            return [
                'current' => $current,
                'previous' => $previous,
                'changes' => $this->calculateChanges($current, $previous),
            ];
        });
    }

    private function getPeriodStats(string $branchId, $from, $to): array
    {
        $result = DB::table('tickets')
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
                COUNT(CASE WHEN status IN ('waiting','called','in_progress') THEN 1 END) as active,
                ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL)) as avg_wait,
                ROUND(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL)) as avg_service,
                ROUND(AVG(rating) FILTER (WHERE rating IS NOT NULL), 2) as avg_rating,
                COUNT(rating) as total_ratings,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY wait_time_seconds)
                    FILTER (WHERE wait_time_seconds IS NOT NULL) as p50_wait,
                PERCENTILE_CONT(0.9) WITHIN GROUP (ORDER BY wait_time_seconds)
                    FILTER (WHERE wait_time_seconds IS NOT NULL) as p90_wait
            ")
            ->first();

        return (array) $result;
    }

    private function calculateChanges(array $current, array $previous): array
    {
        $changes = [];
        $metrics = ['total_tickets', 'completed', 'avg_wait', 'avg_service', 'avg_rating'];

        foreach ($metrics as $metric) {
            $curr = $current[$metric] ?? 0;
            $prev = $previous[$metric] ?? 0;

            if ($prev > 0) {
                $changes[$metric] = round((($curr - $prev) / $prev) * 100, 1);
            } else {
                $changes[$metric] = $curr > 0 ? 100.0 : 0.0;
            }
        }

        return $changes;
    }

    public function getHourlyDistribution(string $branchId, string $date): Collection
    {
        return DB::table('tickets')
            ->where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->selectRaw("
                EXTRACT(HOUR FROM created_at) as hour,
                COUNT(*) as issued,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL)) as avg_wait
            ")
            ->groupByRaw('EXTRACT(HOUR FROM created_at)')
            ->orderBy('hour')
            ->get();
    }

    public function getServiceBreakdown(string $branchId, string $dateFrom, string $dateTo): Collection
    {
        return DB::table('tickets')
            ->join('services', 'tickets.service_id', '=', 'services.id')
            ->where('tickets.branch_id', $branchId)
            ->whereBetween('tickets.created_at', [$dateFrom, $dateTo])
            ->selectRaw("
                services.id, services.name, services.color,
                COUNT(*) as total,
                COUNT(CASE WHEN tickets.status = 'completed' THEN 1 END) as completed,
                ROUND(AVG(tickets.wait_time_seconds) FILTER (WHERE tickets.wait_time_seconds IS NOT NULL)) as avg_wait,
                ROUND(AVG(tickets.service_time_seconds) FILTER (WHERE tickets.service_time_seconds IS NOT NULL)) as avg_service,
                ROUND(AVG(tickets.rating) FILTER (WHERE tickets.rating IS NOT NULL), 2) as avg_rating
            ")
            ->groupBy('services.id', 'services.name', 'services.color')
            ->orderByDesc('total')
            ->get();
    }

    public function getOperatorPerformance(string $branchId, string $dateFrom, string $dateTo): Collection
    {
        return DB::table('tickets')
            ->join('users', 'tickets.served_by', '=', 'users.id')
            ->where('tickets.branch_id', $branchId)
            ->whereNotNull('tickets.served_by')
            ->whereBetween('tickets.created_at', [$dateFrom, $dateTo])
            ->selectRaw("
                users.id, users.name, users.avatar_url,
                COUNT(*) as tickets_served,
                ROUND(AVG(tickets.service_time_seconds) FILTER (WHERE tickets.service_time_seconds IS NOT NULL)) as avg_service_time,
                ROUND(AVG(tickets.rating) FILTER (WHERE tickets.rating IS NOT NULL), 2) as avg_rating,
                COUNT(tickets.rating) as ratings_count
            ")
            ->groupBy('users.id', 'users.name', 'users.avatar_url')
            ->orderByDesc('tickets_served')
            ->get();
    }

    public function getQueueComparison(string $branchId, string $dateFrom, string $dateTo): Collection
    {
        return DB::table('tickets')
            ->join('queues', 'tickets.queue_id', '=', 'queues.id')
            ->where('tickets.branch_id', $branchId)
            ->whereBetween('tickets.created_at', [$dateFrom, $dateTo])
            ->selectRaw("
                queues.id, queues.name, queues.prefix,
                COUNT(*) as total,
                ROUND(AVG(tickets.wait_time_seconds) FILTER (WHERE tickets.wait_time_seconds IS NOT NULL)) as avg_wait,
                ROUND(AVG(tickets.service_time_seconds) FILTER (WHERE tickets.service_time_seconds IS NOT NULL)) as avg_service,
                COUNT(CASE WHEN tickets.status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN tickets.status = 'no_show' THEN 1 END) as no_show
            ")
            ->groupBy('queues.id', 'queues.name', 'queues.prefix')
            ->get();
    }

    public function getTrend(string $branchId, string $metric, string $dateFrom, string $dateTo): Collection
    {
        $metricColumn = match ($metric) {
            'tickets' => 'COUNT(*)',
            'avg_wait' => "ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL))",
            'avg_service' => "ROUND(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL))",
            'avg_rating' => "ROUND(AVG(rating) FILTER (WHERE rating IS NOT NULL), 2)",
            'completed' => "COUNT(CASE WHEN status = 'completed' THEN 1 END)",
            default => 'COUNT(*)',
        };

        return DB::table('tickets')
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw("DATE(created_at) as date, {$metricColumn} as value")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();
    }

    public function getBranchComparison(string $tenantId, string $dateFrom, string $dateTo): Collection
    {
        return DB::table('tickets')
            ->join('branches', 'tickets.branch_id', '=', 'branches.id')
            ->where('branches.tenant_id', $tenantId)
            ->whereBetween('tickets.created_at', [$dateFrom, $dateTo])
            ->selectRaw("
                branches.id, branches.name, branches.code,
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN tickets.status = 'completed' THEN 1 END) as completed,
                ROUND(AVG(tickets.wait_time_seconds) FILTER (WHERE tickets.wait_time_seconds IS NOT NULL)) as avg_wait,
                ROUND(AVG(tickets.rating) FILTER (WHERE tickets.rating IS NOT NULL), 2) as avg_rating
            ")
            ->groupBy('branches.id', 'branches.name', 'branches.code')
            ->orderByDesc('total_tickets')
            ->get();
    }

    public function snapshotHourly(string $branchId): void
    {
        $now = now();
        $hour = (int) $now->format('H');
        $date = $now->toDateString();

        $stats = DB::table('tickets')
            ->where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->whereRaw("EXTRACT(HOUR FROM created_at) = ?", [$hour])
            ->selectRaw("
                COUNT(*) as issued,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as served,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
                COALESCE(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL), 0) as avg_wait,
                COALESCE(MAX(wait_time_seconds), 0) as max_wait,
                COALESCE(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL), 0) as avg_svc,
                COALESCE(AVG(rating) FILTER (WHERE rating IS NOT NULL), 0) as avg_rating,
                COUNT(rating) as ratings_count
            ")
            ->first();

        MetricsSnapshot::updateOrCreate(
            [
                'branch_id' => $branchId,
                'queue_id' => null,
                'service_id' => null,
                'date' => $date,
                'hour' => $hour,
                'granularity' => 'hourly',
            ],
            [
                'tickets_issued' => $stats->issued ?? 0,
                'tickets_served' => $stats->served ?? 0,
                'tickets_cancelled' => $stats->cancelled ?? 0,
                'tickets_no_show' => $stats->no_show ?? 0,
                'avg_wait_time' => (int) ($stats->avg_wait ?? 0),
                'max_wait_time' => (int) ($stats->max_wait ?? 0),
                'avg_service_time' => (int) ($stats->avg_svc ?? 0),
                'avg_rating' => $stats->avg_rating,
                'ratings_count' => $stats->ratings_count ?? 0,
            ]
        );
    }

    public function aggregateDaily(string $branchId, string $date): void
    {
        $stats = DB::table('tickets')
            ->where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->selectRaw("
                COUNT(*) as issued,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as served,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
                COUNT(CASE WHEN status = 'transferred' THEN 1 END) as transferred,
                COALESCE(ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL)), 0) as avg_wait,
                COALESCE(MAX(wait_time_seconds), 0) as max_wait,
                COALESCE(MIN(wait_time_seconds) FILTER (WHERE wait_time_seconds > 0), 0) as min_wait,
                COALESCE(ROUND(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL)), 0) as avg_svc,
                COALESCE(MAX(service_time_seconds), 0) as max_svc,
                COALESCE(MIN(service_time_seconds) FILTER (WHERE service_time_seconds > 0), 0) as min_svc,
                COALESCE(ROUND(AVG(rating) FILTER (WHERE rating IS NOT NULL), 2), 0) as avg_rating,
                COUNT(rating) as ratings_count
            ")
            ->first();

        MetricsSnapshot::updateOrCreate(
            [
                'branch_id' => $branchId,
                'queue_id' => null,
                'service_id' => null,
                'date' => $date,
                'hour' => null,
                'granularity' => 'daily',
            ],
            [
                'tickets_issued' => $stats->issued ?? 0,
                'tickets_served' => $stats->served ?? 0,
                'tickets_cancelled' => $stats->cancelled ?? 0,
                'tickets_no_show' => $stats->no_show ?? 0,
                'tickets_transferred' => $stats->transferred ?? 0,
                'avg_wait_time' => (int) ($stats->avg_wait ?? 0),
                'max_wait_time' => (int) ($stats->max_wait ?? 0),
                'min_wait_time' => (int) ($stats->min_wait ?? 0),
                'avg_service_time' => (int) ($stats->avg_svc ?? 0),
                'max_service_time' => (int) ($stats->max_svc ?? 0),
                'min_service_time' => (int) ($stats->min_svc ?? 0),
                'avg_rating' => $stats->avg_rating,
                'ratings_count' => $stats->ratings_count ?? 0,
            ]
        );
    }

    private function resolvePeriod(string $period): array
    {
        return match ($period) {
            'today' => [today()->startOfDay(), now()],
            'yesterday' => [today()->subDay()->startOfDay(), today()->subDay()->endOfDay()],
            'week' => [today()->startOfWeek(), now()],
            'month' => [today()->startOfMonth(), now()],
            'last_month' => [today()->subMonth()->startOfMonth(), today()->subMonth()->endOfMonth()],
            default => [today()->startOfDay(), now()],
        };
    }
}
