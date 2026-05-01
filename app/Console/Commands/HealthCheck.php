<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * HealthCheck — Validates critical services and alerts via Telegram if any fail.
 *
 * Checks: PostgreSQL, Redis, Reverb (WebSocket), disk space, queue health.
 *
 * Schedule in routes/console.php:
 *   $schedule->command('health:check')->everyFiveMinutes();
 *
 * Manual run:
 *   php artisan health:check
 */
class HealthCheck extends Command
{
    protected $signature = 'health:check
        {--alert : Send Telegram alert even if all checks pass (for testing)}';

    protected $description = 'Check critical services (DB, Redis, Reverb, disk, queue) and alert on failure';

    private array $results = [];

    private bool $hasFailures = false;

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║  Olinora — Health Check                  ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');

        $this->checkPostgreSQL();
        $this->checkRedis();
        $this->checkReverb();
        $this->checkDiskSpace();
        $this->checkQueueHealth();
        $this->checkStorageWritable();

        $this->info('');

        // Summary
        $passed = collect($this->results)->where('status', true)->count();
        $failed = collect($this->results)->where('status', false)->count();
        $total = count($this->results);

        if ($this->hasFailures) {
            $this->error("Result: {$passed}/{$total} passed — {$failed} FAILED");
            $this->sendTelegramAlert();

            return self::FAILURE;
        }

        $this->info("✓ All {$total} checks passed");

        if ($this->option('alert')) {
            app(TelegramAlertService::class)->sendAlert(
                'Health Check OK',
                "All {$total} checks passed.\n".$this->formatResultsSummary(),
                'info'
            );
            $this->info('→ Test alert sent to Telegram');
        }

        return self::SUCCESS;
    }

    private function checkPostgreSQL(): void
    {
        $name = 'PostgreSQL';
        try {
            $result = DB::selectOne('SELECT 1 as ok, version() as version');
            $version = explode(' ', $result->version)[1] ?? 'unknown';
            $this->checkPassed($name, "Connected (v{$version})");
        } catch (\Throwable $e) {
            $this->checkFailed($name, "Connection failed: {$e->getMessage()}");
        }
    }

    private function checkRedis(): void
    {
        $name = 'Redis';
        try {
            $pong = Redis::ping();
            $info = Redis::info('memory');
            $usedMb = round(($info['used_memory'] ?? 0) / 1024 / 1024, 1);
            $this->checkPassed($name, "Connected — {$usedMb}MB used");
        } catch (\Throwable $e) {
            $this->checkFailed($name, "Connection failed: {$e->getMessage()}");
        }
    }

    private function checkReverb(): void
    {
        $name = 'Reverb (WebSocket)';
        try {
            $host = config('reverb.servers.reverb.host', '127.0.0.1');
            $port = config('reverb.servers.reverb.port', 8080);

            $socket = @fsockopen($host, (int) $port, $errno, $errstr, 3);
            if ($socket) {
                fclose($socket);
                $this->checkPassed($name, "Listening on {$host}:{$port}");
            } else {
                $this->checkFailed($name, "Not reachable at {$host}:{$port} — {$errstr}");
            }
        } catch (\Throwable $e) {
            $this->checkFailed($name, "Check failed: {$e->getMessage()}");
        }
    }

    private function checkDiskSpace(): void
    {
        $name = 'Disk Space';
        try {
            $path = base_path();
            $free = disk_free_space($path);
            $total = disk_total_space($path);
            $usedPct = round((1 - $free / $total) * 100, 1);
            $freeGb = round($free / 1024 / 1024 / 1024, 2);

            if ($usedPct > 90) {
                $this->checkFailed($name, "CRITICAL — {$usedPct}% used, {$freeGb}GB free");
            } elseif ($usedPct > 80) {
                $this->checkWarning($name, "WARNING — {$usedPct}% used, {$freeGb}GB free");
            } else {
                $this->checkPassed($name, "{$usedPct}% used, {$freeGb}GB free");
            }
        } catch (\Throwable $e) {
            $this->checkFailed($name, "Check failed: {$e->getMessage()}");
        }
    }

    private function checkQueueHealth(): void
    {
        $name = 'Queue Workers';
        try {
            $failedCount = DB::table('failed_jobs')->count();
            $recentFailed = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();

            if ($recentFailed > 10) {
                $this->checkFailed($name, "CRITICAL — {$recentFailed} failures in the last hour ({$failedCount} total)");
            } elseif ($recentFailed > 0) {
                $this->checkWarning($name, "{$recentFailed} failures in the last hour ({$failedCount} total)");
            } else {
                $this->checkPassed($name, "Healthy — {$failedCount} total failed jobs");
            }
        } catch (\Throwable $e) {
            $this->checkFailed($name, "Check failed: {$e->getMessage()}");
        }
    }

    private function checkStorageWritable(): void
    {
        $name = 'Storage Writable';
        try {
            $testFile = storage_path('app/.health_check_'.time());
            file_put_contents($testFile, 'ok');
            $content = file_get_contents($testFile);
            unlink($testFile);

            if ($content === 'ok') {
                $this->checkPassed($name, 'Read/write OK');
            } else {
                $this->checkFailed($name, 'Write succeeded but read failed');
            }
        } catch (\Throwable $e) {
            $this->checkFailed($name, "Not writable: {$e->getMessage()}");
        }
    }

    // ── Output helpers ──

    private function checkPassed(string $name, string $detail): void
    {
        $this->results[] = ['name' => $name, 'status' => true, 'detail' => $detail, 'level' => 'ok'];
        $this->info("  ✓ {$name}: {$detail}");
    }

    private function checkWarning(string $name, string $detail): void
    {
        $this->results[] = ['name' => $name, 'status' => false, 'detail' => $detail, 'level' => 'warning'];
        $this->hasFailures = true;
        $this->checkWarning("  ⚠ {$name}: {$detail}");
    }

    private function checkFailed(string $name, string $detail): void
    {
        $this->results[] = ['name' => $name, 'status' => false, 'detail' => $detail, 'level' => 'critical'];
        $this->hasFailures = true;
        $this->error("  ✕ {$name}: {$detail}");
    }

    // ── Telegram alert ──

    private function sendTelegramAlert(): void
    {
        try {
            $failures = collect($this->results)->where('status', false);
            $body = $this->formatResultsSummary();

            app(TelegramAlertService::class)->sendAlert(
                "Health Check FAILED ({$failures->count()} issues)",
                $body,
                'critical'
            );

            $this->info('→ Alert sent to Telegram');
        } catch (\Throwable $e) {
            $this->error("→ Failed to send Telegram alert: {$e->getMessage()}");
        }
    }

    private function formatResultsSummary(): string
    {
        $lines = [];
        foreach ($this->results as $r) {
            $icon = $r['status'] ? '✅' : ($r['level'] === 'critical' ? '🔴' : '🟡');
            $lines[] = "{$icon} {$r['name']}: {$r['detail']}";
        }

        return implode("\n", $lines);
    }
}
