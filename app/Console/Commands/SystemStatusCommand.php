<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class SystemStatusCommand extends Command
{
    protected $signature = 'system:status
        {--alert-only : Only send notification if there are warnings/errors}
        {--daily-summary : Send a condensed daily summary with 24h alert count}';

    protected $description = 'Check system health and send status to Telegram';

    /**
     * Expected number of supervisor processes:
     * olinora-reverb, olinora-worker_00, olinora-worker_01
     */
    private const EXPECTED_SUPERVISOR_PROCESSES = 3;

    /**
     * Cache key for tracking alert count over 24h.
     */
    private const ALERT_COUNT_KEY = 'system:alert_count_24h';
    private const LAST_ALERT_KEY = 'system:last_alert_details';

    public function handle(): int
    {
        $checks = [];
        $warnings = [];

        // ── 1. Disk Usage ──
        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');
        $diskUsedPct = round((1 - $diskFree / $diskTotal) * 100, 1);
        $checks['disco'] = "{$diskUsedPct}%";
        if ($diskUsedPct > 90) {
            $warnings[] = ['level' => 'critical', 'msg' => "🔴 Disco al {$diskUsedPct}%"];
        } elseif ($diskUsedPct > 85) {
            $warnings[] = ['level' => 'warning', 'msg' => "⚠️ Disco al {$diskUsedPct}%"];
        }

        // ── 2. Memory ──
        $memInfo = $this->getMemoryInfo();
        $checks['ram'] = $memInfo['used_pct'] . '%';
        $checks['swap'] = $memInfo['swap_used'];
        if ($memInfo['used_pct'] > 95) {
            $warnings[] = ['level' => 'critical', 'msg' => "🔴 RAM al {$memInfo['used_pct']}%"];
        } elseif ($memInfo['used_pct'] > 90) {
            $warnings[] = ['level' => 'warning', 'msg' => "⚠️ RAM al {$memInfo['used_pct']}%"];
        }

        // ── 3. PostgreSQL ──
        try {
            $pgStart = microtime(true);
            DB::select('SELECT 1');
            $pgMs = round((microtime(true) - $pgStart) * 1000, 1);
            $checks['postgresql'] = "{$pgMs}ms";
            if ($pgMs > 1000) {
                $warnings[] = ['level' => 'critical', 'msg' => "🔴 PostgreSQL muy lento: {$pgMs}ms"];
            } elseif ($pgMs > 200) {
                $warnings[] = ['level' => 'warning', 'msg' => "⚠️ PostgreSQL lento: {$pgMs}ms"];
            }

            $conns = DB::select("SELECT count(*) as cnt FROM pg_stat_activity WHERE state = 'active'");
            $checks['pg_connections'] = $conns[0]->cnt;
        } catch (\Throwable $e) {
            $checks['postgresql'] = '❌ DOWN';
            $warnings[] = ['level' => 'critical', 'msg' => "🔴 PostgreSQL DOWN: " . $e->getMessage()];
        }

        // ── 4. Redis ──
        try {
            $redisStart = microtime(true);
            Redis::ping();
            $redisMs = round((microtime(true) - $redisStart) * 1000, 1);
            $checks['redis'] = "{$redisMs}ms";

            $redisInfo = Redis::info();
            $redisMemory = $redisInfo['used_memory_human'] ?? '?';
            $checks['redis_memory'] = $redisMemory;
            $checks['redis_keys'] = $redisInfo['db0'] ?? '0 keys';
        } catch (\Throwable $e) {
            $checks['redis'] = '❌ DOWN';
            $warnings[] = ['level' => 'critical', 'msg' => "🔴 Redis DOWN: " . $e->getMessage()];
        }

        // ── 5. Queue Health ──
        try {
            $queueSize = Redis::llen('queues:default') ?? 0;
            $checks['queue_pending'] = $queueSize;
            if ($queueSize > 500) {
                $warnings[] = ['level' => 'critical', 'msg' => "🔴 Cola con {$queueSize} jobs pendientes"];
            } elseif ($queueSize > 100) {
                $warnings[] = ['level' => 'warning', 'msg' => "⚠️ Cola con {$queueSize} jobs pendientes"];
            }
        } catch (\Throwable) {
            $checks['queue_pending'] = '?';
        }

        // ── 6. Today's Ticket Stats ──
        try {
            $stats = DB::table('tickets')
                ->whereDate('created_at', today())
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
                ")
                ->first();

            $checks['tickets_hoy'] = "{$stats->total} (✓{$stats->completed} ◷{$stats->waiting} ✗{$stats->cancelled})";
        } catch (\Throwable) {
            $checks['tickets_hoy'] = '?';
        }

        // ── 7. Supervisor Processes ──
        $supervisorChecked = $this->checkSupervisor();
        $checks['supervisor'] = $supervisorChecked['display'];
        if ($supervisorChecked['warning']) {
            $level = str_contains($supervisorChecked['warning'], '🔴') ? 'critical' : 'warning';
            $warnings[] = ['level' => $level, 'msg' => $supervisorChecked['warning']];
        }

        // ── Determine action based on mode ──
        $hasWarnings = count($warnings) > 0;
        $hasCritical = collect($warnings)->contains('level', 'critical');

        // Track alerts in cache for daily summary
        if ($hasWarnings) {
            $this->trackAlert($warnings);
        }

        if ($this->option('daily-summary')) {
            $this->sendDailySummary($checks, $warnings);
            return self::SUCCESS;
        }

        if ($this->option('alert-only') && !$hasWarnings) {
            $this->info('All checks passed, no alert sent.');
            return self::SUCCESS;
        }

        // Full report (manual run) or alert-only with warnings
        $this->sendFullReport($checks, $warnings, $hasWarnings);

        // Console output
        $this->info($hasWarnings ? 'Status sent with warnings' : 'Status sent OK');
        foreach ($checks as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        return $hasWarnings ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Track alert occurrences in Redis for the daily summary.
     * Uses a rolling 24h window.
     */
    private function trackAlert(array $warnings): void
    {
        try {
            $count = (int) Cache::get(self::ALERT_COUNT_KEY, 0);
            Cache::put(self::ALERT_COUNT_KEY, $count + 1, now()->addHours(24));

            // Store last alert details for daily summary context
            $lastAlert = [
                'time' => now()->format('H:i'),
                'warnings' => array_map(fn($w) => $w['msg'], $warnings),
            ];
            Cache::put(self::LAST_ALERT_KEY, $lastAlert, now()->addHours(24));
        } catch (\Throwable) {
            // Cache failure shouldn't break monitoring
        }
    }

    /**
     * Send condensed daily summary — one message per day to confirm monitoring is active.
     */
    private function sendDailySummary(array $checks, array $warnings): void
    {
        $alertCount = 0;
        $lastAlert = null;

        try {
            $alertCount = (int) Cache::get(self::ALERT_COUNT_KEY, 0);
            $lastAlert = Cache::get(self::LAST_ALERT_KEY);

            // Reset counters after daily summary
            Cache::forget(self::ALERT_COUNT_KEY);
            Cache::forget(self::LAST_ALERT_KEY);
        } catch (\Throwable) {
            // Continue even if cache read fails
        }

        $hasWarnings = count($warnings) > 0;
        $emoji = $hasWarnings ? '🟡' : '✅';

        $msg = "{$emoji} *Olinora Daily* — " . now()->format('d/M H:i') . "\n\n";

        // Condensed one-line metrics
        $msg .= "Disco: `{$checks['disco']}` · RAM: `{$checks['ram']}` · Swap: `{$checks['swap']}`\n";
        $msg .= "PG: `{$checks['postgresql']}` · Redis: `{$checks['redis']}`\n";
        $msg .= "Supervisor: `{$checks['supervisor']}`\n";
        $msg .= "Tickets hoy: `{$checks['tickets_hoy']}`\n";

        // 24h alert summary
        $msg .= "\n";
        if ($alertCount === 0) {
            $msg .= "🛡 Sin alertas en las últimas 24h";
        } else {
            $msg .= "⚠️ {$alertCount} alerta(s) en las últimas 24h";
            if ($lastAlert) {
                $msg .= "\nÚltima: " . implode(', ', $lastAlert['warnings']) . " ({$lastAlert['time']})";
            }
        }

        // Current warnings if any
        if ($hasWarnings) {
            $msg .= "\n\n*Alertas activas:*\n";
            $msg .= implode("\n", array_map(fn($w) => $w['msg'], $warnings));
        }

        $this->sendTelegram($msg);
        $this->info('Daily summary sent');
    }

    /**
     * Send full status report — used for manual runs and alert-only with warnings.
     */
    private function sendFullReport(array $checks, array $warnings, bool $hasWarnings): void
    {
        $emoji = $hasWarnings ? '🟡' : '🟢';

        // If there are critical issues, use red emoji
        if (collect($warnings)->contains('level', 'critical')) {
            $emoji = '🔴';
        }

        $msg = "{$emoji} *Olinora Status* — " . now()->format('d/M H:i') . "\n\n";

        foreach ($checks as $key => $value) {
            $label = str_replace('_', ' ', ucfirst($key));
            $msg .= "• {$label}: `{$value}`\n";
        }

        if ($hasWarnings) {
            $msg .= "\n*Alertas:*\n";
            $msg .= implode("\n", array_map(fn($w) => $w['msg'], $warnings));
        }

        $this->sendTelegram($msg);
    }

    /**
     * Check supervisor processes using sudo (required for www-data user).
     * Tries sudo first, falls back to direct call, handles permission errors.
     */
    private function checkSupervisor(): array
    {
        $output = shell_exec('sudo /usr/bin/supervisorctl status 2>&1') ?? '';

        if (str_contains($output, 'Permission denied') || str_contains($output, 'sudo:') || empty(trim($output))) {
            $output = shell_exec('/usr/bin/supervisorctl status 2>&1') ?? '';
        }

        if (empty(trim($output)) || str_contains($output, 'Permission denied') || str_contains($output, 'error')) {
            return [
                'display' => '⚠️ no se pudo verificar',
                'warning' => '⚠️ Supervisor: sin acceso para verificar estado',
            ];
        }

        $runningCount = substr_count($output, 'RUNNING');
        $hasFatal = str_contains($output, 'FATAL');
        $hasStopped = str_contains($output, 'STOPPED');
        $expected = self::EXPECTED_SUPERVISOR_PROCESSES;

        $display = "{$runningCount}/{$expected} procesos RUNNING";

        $warning = null;
        if ($hasFatal || $hasStopped) {
            $warning = "🔴 Supervisor: procesos FATAL/STOPPED detectados";
        } elseif ($runningCount < $expected) {
            $warning = "⚠️ Supervisor: {$runningCount}/{$expected} procesos (esperados {$expected})";
        }

        return [
            'display' => $display,
            'warning' => $warning,
        ];
    }

    private function getMemoryInfo(): array
    {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        preg_match('/SwapTotal:\s+(\d+)/', $meminfo, $swapTotal);
        preg_match('/SwapFree:\s+(\d+)/', $meminfo, $swapFree);

        $totalKb = (int)($total[1] ?? 0);
        $availableKb = (int)($available[1] ?? 0);
        $usedPct = $totalKb > 0 ? round((1 - $availableKb / $totalKb) * 100, 1) : 0;

        $swapTotalKb = (int)($swapTotal[1] ?? 0);
        $swapFreeKb = (int)($swapFree[1] ?? 0);
        $swapUsedMb = round(($swapTotalKb - $swapFreeKb) / 1024);

        return [
            'used_pct' => $usedPct,
            'swap_used' => "{$swapUsedMb}MB",
        ];
    }

    private function sendTelegram(string $message): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            $this->warn('Telegram not configured');
            return;
        }

        try {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            $this->error('Telegram send failed: ' . $e->getMessage());
        }
    }
}
