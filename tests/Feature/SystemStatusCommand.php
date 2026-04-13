<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class SystemStatusCommand extends Command
{
    protected $signature = 'system:status {--alert-only : Only send if there are warnings}';
    protected $description = 'Check system health and send status to Telegram';

    /**
     * Expected number of supervisor processes:
     * olinora-reverb, olinora-worker_00, olinora-worker_01
     */
    private const EXPECTED_SUPERVISOR_PROCESSES = 3;

    public function handle(): int
    {
        $checks = [];
        $warnings = [];

        // ── 1. Disk Usage ──
        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');
        $diskUsedPct = round((1 - $diskFree / $diskTotal) * 100, 1);
        $checks['disco'] = "{$diskUsedPct}%";
        if ($diskUsedPct > 85) $warnings[] = "⚠️ Disco al {$diskUsedPct}%";

        // ── 2. Memory ──
        $memInfo = $this->getMemoryInfo();
        $checks['ram'] = $memInfo['used_pct'] . '%';
        $checks['swap'] = $memInfo['swap_used'];
        if ($memInfo['used_pct'] > 90) $warnings[] = "⚠️ RAM al {$memInfo['used_pct']}%";

        // ── 3. PostgreSQL ──
        try {
            $pgStart = microtime(true);
            DB::select('SELECT 1');
            $pgMs = round((microtime(true) - $pgStart) * 1000, 1);
            $checks['postgresql'] = "{$pgMs}ms";
            if ($pgMs > 100) $warnings[] = "⚠️ PostgreSQL lento: {$pgMs}ms";

            // Active connections
            $conns = DB::select("SELECT count(*) as cnt FROM pg_stat_activity WHERE state = 'active'");
            $checks['pg_connections'] = $conns[0]->cnt;
        } catch (\Throwable $e) {
            $checks['postgresql'] = '❌ DOWN';
            $warnings[] = "🔴 PostgreSQL DOWN: " . $e->getMessage();
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
            $warnings[] = "🔴 Redis DOWN: " . $e->getMessage();
        }

        // ── 5. Queue Health ──
        try {
            $queueSize = Redis::llen('queues:default') ?? 0;
            $checks['queue_pending'] = $queueSize;
            if ($queueSize > 100) $warnings[] = "⚠️ Cola con {$queueSize} jobs pendientes";
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
            $warnings[] = $supervisorChecked['warning'];
        }

        // ── Build message ──
        $hasWarnings = count($warnings) > 0;

        if ($this->option('alert-only') && !$hasWarnings) {
            $this->info('All checks passed, no alert sent.');
            return self::SUCCESS;
        }

        $emoji = $hasWarnings ? '🟡' : '🟢';
        $msg = "{$emoji} *Olinora Status* — " . now()->format('d/M H:i') . "\n\n";

        foreach ($checks as $key => $value) {
            $label = str_replace('_', ' ', ucfirst($key));
            $msg .= "• {$label}: `{$value}`\n";
        }

        if ($hasWarnings) {
            $msg .= "\n*Alertas:*\n" . implode("\n", $warnings);
        }

        // Send to Telegram
        $this->sendTelegram($msg);

        // Console output
        $this->info($hasWarnings ? 'Status sent with warnings' : 'Status sent OK');
        foreach ($checks as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        return $hasWarnings ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Check supervisor processes using sudo (required for www-data user).
     * Tries sudo first, falls back to direct call, handles permission errors.
     */
    private function checkSupervisor(): array
    {
        // Try sudo first (needed when running as www-data via scheduler)
        $output = shell_exec('sudo /usr/bin/supervisorctl status 2>&1') ?? '';

        // If sudo fails (no sudoers rule), try direct call (works when running as root)
        if (str_contains($output, 'Permission denied') || str_contains($output, 'sudo:') || empty(trim($output))) {
            $output = shell_exec('/usr/bin/supervisorctl status 2>&1') ?? '';
        }

        // If still no output or error, report it
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
