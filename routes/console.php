<?php

use App\Jobs\Billing\PublishOutboxEventsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Olinora Scheduled Jobs
|--------------------------------------------------------------------------
*/

// ── Ticket Management ──
Schedule::command('turnos:auto-close')
    ->everyFiveMinutes()
    ->between('06:00', '22:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/auto-close.log'));

Schedule::command('turnos:daily-metrics')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/daily-metrics.log'));

Schedule::command('turnos:cleanup-tickets --days=90')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cleanup.log'));

// ── Health & Monitoring ──
Schedule::command('health:check')
    ->everyFiveMinutes()
    ->runInBackground()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/health-check.log'));

// Silent monitoring every hour — only notifies if problems detected
Schedule::command('system:status --alert-only')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/system-status.log'));

// Daily summary at 8am — confirms monitoring is active + 24h alert recap
Schedule::command('system:status --daily-summary')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/system-status.log'));

// ── Weekly Report (Monday 8am) ──
Schedule::command('report:weekly')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/weekly-report.log'));

// ── Log Cleanup (daily at 4am) ──
Schedule::command('logs:clean --days=7')
    ->dailyAt('04:00')
    ->withoutOverlapping();

// ── Billing Outbox Publisher ──
// Drains billing_outbox_events every 30s.
// Single-instance via uniqueId on the job + withoutOverlapping here (belt-and-braces).
// See ADR-010 (transactional outbox), ADR-013 (operational defaults).
Schedule::job(new PublishOutboxEventsJob)
    ->everyThirtySeconds()
    ->withoutOverlapping(60)
    ->onOneServer()
    ->name('billing:publish-outbox-events');
