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
        $supervisorOutput = shell_exec('supervisorctl status 2>/dev/null') ?? '';
        $runningCount = substr_count($supervisorOutput, 'RUNNING');
        $checks['supervisor'] = "{$runningCount} procesos";
        if (str_contains($supervisorOutput, 'FATAL') || str_contains($supervisorOutput, 'STOPPED')) {
            $warnings[] = "🔴 Supervisor: procesos caídos";
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
