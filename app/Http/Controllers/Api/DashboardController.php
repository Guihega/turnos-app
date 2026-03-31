<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\MetricsRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MetricsRepositoryInterface $metricsRepo,
        private readonly TicketRepositoryInterface $ticketRepo,
    ) {}

    /**
     * Main dashboard overview for a branch.
     */
    public function overview(Request $request, string $branchId): JsonResponse
    {
        $period = $request->input('period', 'today');

        $metrics = $this->metricsRepo->getDashboardMetrics($branchId, $period);
        $statusCounts = $this->ticketRepo->getStatusCounts($branchId);

        return response()->json([
            'metrics' => $metrics,
            'status_counts' => $statusCounts,
        ]);
    }

    /**
     * Real-time stats (polled frequently).
     */
    public function realtime(string $branchId): JsonResponse
    {
        $todayStats = $this->ticketRepo->getTodayStats($branchId);
        $activeTickets = $this->ticketRepo->getActiveForBranch($branchId);

        $waitingByQueue = $activeTickets
            ->where('status.value', 'waiting')
            ->groupBy('queue_id')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'queue' => $group->first()->queue?->name,
                'avg_wait' => $this->ticketRepo->getAverageWaitTime(
                    $branchId,
                    $group->first()->queue_id,
                ),
            ]);

        return response()->json([
            'today' => $todayStats,
            'active_count' => $activeTickets->count(),
            'waiting_by_queue' => $waitingByQueue,
            'in_progress_count' => $activeTickets->where('status.value', 'in_progress')->count(),
        ]);
    }

    /**
     * Hourly distribution chart data.
     */
    public function hourlyDistribution(Request $request, string $branchId): JsonResponse
    {
        $date = $request->input('date', today()->toDateString());
        $data = $this->metricsRepo->getHourlyDistribution($branchId, $date);

        return response()->json(['data' => $data]);
    }

    /**
     * Service breakdown chart data.
     */
    public function serviceBreakdown(Request $request, string $branchId): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $from = $request->input('date_from', today()->toDateString());
        $to = $request->input('date_to', today()->toDateString());

        $data = $this->metricsRepo->getServiceBreakdown($branchId, $from, $to);

        return response()->json(['data' => $data]);
    }

    /**
     * Operator performance ranking.
     */
    public function operatorPerformance(Request $request, string $branchId): JsonResponse
    {
        $from = $request->input('date_from', today()->toDateString());
        $to = $request->input('date_to', today()->toDateString());

        $data = $this->metricsRepo->getOperatorPerformance($branchId, $from, $to);

        return response()->json(['data' => $data]);
    }

    /**
     * Queue comparison.
     */
    public function queueComparison(Request $request, string $branchId): JsonResponse
    {
        $from = $request->input('date_from', today()->toDateString());
        $to = $request->input('date_to', today()->toDateString());

        $data = $this->metricsRepo->getQueueComparison($branchId, $from, $to);

        return response()->json(['data' => $data]);
    }

    /**
     * Trend line for a specific metric.
     */
    public function trend(Request $request, string $branchId): JsonResponse
    {
        $request->validate([
            'metric' => 'required|in:tickets,avg_wait,avg_service,avg_rating,completed',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $data = $this->metricsRepo->getTrend(
            $branchId,
            $request->input('metric'),
            $request->input('date_from'),
            $request->input('date_to'),
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Multi-branch comparison (tenant-level).
     */
    public function branchComparison(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $from = $request->input('date_from', today()->subDays(7)->toDateString());
        $to = $request->input('date_to', today()->toDateString());

        $data = $this->metricsRepo->getBranchComparison($tenantId, $from, $to);

        return response()->json(['data' => $data]);
    }
}
