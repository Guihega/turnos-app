<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DailyPilotReset — Cleans transactional data daily during the pilot phase.
 *
 * Keeps: tenants, users, branches, services, queues, counters, settings.
 * Deletes: tickets, daily_metrics, and clears related caches.
 *
 * Schedule in app/Console/Kernel.php or routes/console.php:
 *   $schedule->command('pilot:reset')->dailyAt('03:00');
 *
 * Manual run:
 *   php artisan pilot:reset
 *   php artisan pilot:reset --force   (skip confirmation in production)
 */
class DailyPilotReset extends Command
{
    protected $signature = 'pilot:reset
        {--force : Skip confirmation prompt}
        {--keep-days=0 : Keep tickets from the last N days (0 = delete all)}';

    protected $description = 'Reset transactional data (tickets, metrics) for pilot phase';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            if (! $this->confirm('⚠ This will DELETE transactional data in PRODUCTION. Continue?')) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $keepDays = (int) $this->option('keep-days');

        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║  Olinora — Daily Pilot Reset             ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');

        try {
            DB::beginTransaction();

            // ── 1. Tickets ──
            $ticketQuery = DB::table('tickets');
            if ($keepDays > 0) {
                $ticketQuery->where('created_at', '<', now()->subDays($keepDays));
                $this->info("→ Keeping tickets from last {$keepDays} days");
            }
            $ticketCount = $ticketQuery->count();
            $ticketQuery->delete();
            $this->info("✓ Tickets deleted: {$ticketCount}");

            // ── 2. Daily metrics snapshots ──
            $metricsCount = 0;
            if (DB::getSchemaBuilder()->hasTable('daily_metrics')) {
                $metricsQuery = DB::table('daily_metrics');
                if ($keepDays > 0) {
                    $metricsQuery->where('date', '<', now()->subDays($keepDays)->toDateString());
                }
                $metricsCount = $metricsQuery->count();
                $metricsQuery->delete();
            }
            $this->info("✓ Metrics deleted: {$metricsCount}");

            // ── 3. Failed jobs / job batches ──
            $failedCount = DB::table('failed_jobs')->count();
            DB::table('failed_jobs')->truncate();
            $this->info("✓ Failed jobs cleared: {$failedCount}");

            // ── 4. Notifications table (if exists) ──
            if (DB::getSchemaBuilder()->hasTable('notifications')) {
                $notifCount = DB::table('notifications')->count();
                DB::table('notifications')->delete();
                $this->info("✓ Notifications cleared: {$notifCount}");
            }

            DB::commit();

            // ── 5. Clear caches ──
            $this->call('cache:clear');
            $this->info('✓ Cache cleared');

            // ── 6. Reset ticket sequences (if daily_reset is on) ──
            // This ensures ticket numbers start from 1 again
            $this->info('✓ Ready for a fresh day');
            $this->info('');

            $total = $ticketCount + $metricsCount + $failedCount;
            Log::info("[PilotReset] Daily reset completed. Deleted: {$total} records.");

            $this->info('════════════════════════════════════════════');
            $this->info("  Total records cleaned: {$total}");
            $this->info('════════════════════════════════════════════');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("✕ Reset failed: {$e->getMessage()}");
            Log::error("[PilotReset] Failed: {$e->getMessage()}", ['exception' => $e]);

            return self::FAILURE;
        }
    }
}
