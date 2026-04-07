<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\MetricsSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DailyMetricsSnapshot extends Command
{
    protected $signature = 'turnos:daily-metrics
                            {--date= : Date to snapshot (default: yesterday)}';

    protected $description = 'Generate daily metrics snapshots for all active branches';

    public function handle(): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))
            : now()->subDay();

        $dateStr = $date->toDateString();
        $branches = Branch::where('is_active', true)->get();

        if ($branches->isEmpty()) {
            $this->warn('No hay sucursales activas.');
            return self::SUCCESS;
        }

        $created = 0;

        foreach ($branches as $branch) {
            $stats = DB::table('tickets')
                ->where('branch_id', $branch->id)
                ->whereDate('created_at', $dateStr)
                ->selectRaw("
                    COUNT(*) as tickets_issued,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as tickets_served,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as tickets_cancelled,
                    COUNT(CASE WHEN status = 'no_show' THEN 1 END) as tickets_no_show,
                    COUNT(CASE WHEN status = 'transferred' THEN 1 END) as tickets_transferred,
                    COALESCE(ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL)), 0)::int as avg_wait_time,
                    COALESCE(MAX(wait_time_seconds), 0)::int as max_wait_time,
                    COALESCE(MIN(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL), 0)::int as min_wait_time,
                    COALESCE(ROUND(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL)), 0)::int as avg_service_time,
                    COALESCE(MAX(service_time_seconds), 0)::int as max_service_time,
                    COALESCE(MIN(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL), 0)::int as min_service_time,
                    ROUND(AVG(rating) FILTER (WHERE rating IS NOT NULL), 2) as avg_rating,
                    COUNT(rating) as ratings_count
                ")
                ->first();

            // Calculate percentiles for wait time
            $waitTimes = DB::table('tickets')
                ->where('branch_id', $branch->id)
                ->whereDate('created_at', $dateStr)
                ->whereNotNull('wait_time_seconds')
                ->pluck('wait_time_seconds')
                ->sort()
                ->values();

            $p50 = $this->percentile($waitTimes, 50);
            $p90 = $this->percentile($waitTimes, 90);
            $p95 = $this->percentile($waitTimes, 95);

            // Count active operators
            $activeOperators = DB::table('tickets')
                ->where('branch_id', $branch->id)
                ->whereDate('created_at', $dateStr)
                ->whereNotNull('served_by')
                ->distinct('served_by')
                ->count('served_by');

            // Upsert the snapshot
            MetricsSnapshot::updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'queue_id' => null,
                    'service_id' => null,
                    'date' => $dateStr,
                    'hour' => null,
                    'granularity' => 'daily',
                ],
                [
                    'tickets_issued' => $stats->tickets_issued,
                    'tickets_served' => $stats->tickets_served,
                    'tickets_cancelled' => $stats->tickets_cancelled,
                    'tickets_no_show' => $stats->tickets_no_show,
                    'tickets_transferred' => $stats->tickets_transferred,
                    'avg_wait_time' => $stats->avg_wait_time,
                    'max_wait_time' => $stats->max_wait_time,
                    'min_wait_time' => $stats->min_wait_time,
                    'p50_wait_time' => $p50,
                    'p90_wait_time' => $p90,
                    'p95_wait_time' => $p95,
                    'avg_service_time' => $stats->avg_service_time,
                    'max_service_time' => $stats->max_service_time,
                    'min_service_time' => $stats->min_service_time,
                    'active_operators' => $activeOperators,
                    'avg_rating' => $stats->avg_rating,
                    'ratings_count' => $stats->ratings_count,
                ]
            );

            $created++;
            $this->line("  ✓ {$branch->name}: {$stats->tickets_issued} turnos, {$stats->tickets_served} completados");
        }

        $this->info("✓ Snapshots generados para {$created} sucursales ({$dateStr}).");

        return self::SUCCESS;
    }

    private function percentile($sorted, int $pct): int
    {
        if ($sorted->isEmpty()) return 0;
        $index = max(0, ceil(($pct / 100) * $sorted->count()) - 1);
        return (int) ($sorted->values()[$index] ?? 0);
    }
}
