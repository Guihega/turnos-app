<?php

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

// System status: full report every 6 hours, alerts-only every hour
Schedule::command('system:status')
    ->everySixHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/system-status.log'));

Schedule::command('system:status --alert-only')
    ->hourly()
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
