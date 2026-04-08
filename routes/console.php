<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Tasks
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Olinora Scheduled Jobs
|--------------------------------------------------------------------------
|
| turnos:auto-close    — Runs every 5 min during business hours.
|                        Cancels stale waiting tickets (>2h) and marks
|                        called tickets as no-show (>15 min).
|
| turnos:daily-metrics — Runs at 00:30 daily. Generates a snapshot of
|                        yesterday's metrics per branch for reporting.
|
| turnos:cleanup-tickets — Runs weekly on Sunday at 03:00. Soft-deletes
|                          completed/cancelled tickets older than 90 days.
|
| health:check         — Runs every 5 min. Validates PostgreSQL, Redis,
|                        Reverb, disk space, queue. Alerts via Telegram
|                        if any check fails.
|
| pilot:reset          — Runs daily at 03:30. Cleans transactional data
|                        (tickets, metrics) for the pilot phase.
|                        REMOVE THIS after pilot ends.
|
*/

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
    ->weeklyOn(0, '03:00') // Sunday at 3am
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cleanup.log'));

// ── Health Check (every 5 min) ──
Schedule::command('health:check')
    ->everyFiveMinutes()
    ->runInBackground()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/health-check.log'));

// ── Pilot Reset (daily at 03:30 — REMOVE after pilot phase) ──
Schedule::command('pilot:reset --force')
    ->dailyAt('03:30')
    ->runInBackground()
    ->withoutOverlapping()
    ->before(function () {
        logger()->info('[Schedule] Starting daily pilot reset');
    })
    ->after(function () {
        logger()->info('[Schedule] Daily pilot reset completed');
    })
    ->appendOutputTo(storage_path('logs/pilot-reset.log'));