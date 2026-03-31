<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface MetricsRepositoryInterface
{
    public function getDashboardMetrics(string $branchId, string $period = 'today'): array;

    public function getHourlyDistribution(string $branchId, string $date): Collection;

    public function getServiceBreakdown(string $branchId, string $dateFrom, string $dateTo): Collection;

    public function getOperatorPerformance(string $branchId, string $dateFrom, string $dateTo): Collection;

    public function getQueueComparison(string $branchId, string $dateFrom, string $dateTo): Collection;

    public function getTrend(string $branchId, string $metric, string $dateFrom, string $dateTo): Collection;

    public function getBranchComparison(string $tenantId, string $dateFrom, string $dateTo): Collection;

    public function snapshotHourly(string $branchId): void;

    public function aggregateDaily(string $branchId, string $date): void;
}
