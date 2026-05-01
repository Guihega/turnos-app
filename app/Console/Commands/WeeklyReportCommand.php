<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WeeklyReportCommand extends Command
{
    protected $signature = 'report:weekly';

    protected $description = 'Generate and send weekly KPI report via Telegram';

    public function handle(): int
    {
        $from = now()->subWeek()->startOfWeek();
        $to = now()->subWeek()->endOfWeek();
        $period = $from->format('d/M').' — '.$to->format('d/M Y');

        // ── Global stats ──
        $global = DB::table('tickets')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
                COALESCE(ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL) / 60, 1), 0) as avg_wait_min,
                COALESCE(ROUND(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL) / 60, 1), 0) as avg_service_min,
                ROUND(AVG(rating) FILTER (WHERE rating IS NOT NULL), 1) as avg_rating,
                COUNT(rating) as total_ratings
            ")
            ->first();

        // ── Per-branch breakdown ──
        $branches = DB::table('tickets')
            ->join('branches', 'tickets.branch_id', '=', 'branches.id')
            ->whereBetween('tickets.created_at', [$from, $to])
            ->selectRaw("
                branches.name,
                COUNT(*) as total,
                COUNT(CASE WHEN tickets.status = 'completed' THEN 1 END) as completed,
                COALESCE(ROUND(AVG(tickets.wait_time_seconds) FILTER (WHERE tickets.wait_time_seconds IS NOT NULL) / 60, 1), 0) as avg_wait,
                ROUND(AVG(tickets.rating) FILTER (WHERE tickets.rating IS NOT NULL), 1) as rating
            ")
            ->groupBy('branches.name')
            ->orderByDesc('total')
            ->get();

        // ── Top operators ──
        $operators = DB::table('tickets')
            ->join('users', 'tickets.served_by', '=', 'users.id')
            ->whereBetween('tickets.created_at', [$from, $to])
            ->where('tickets.status', 'completed')
            ->selectRaw('
                users.name,
                COUNT(*) as served,
                COALESCE(ROUND(AVG(tickets.service_time_seconds) / 60, 1), 0) as avg_min,
                ROUND(AVG(tickets.rating) FILTER (WHERE tickets.rating IS NOT NULL), 1) as rating
            ')
            ->groupBy('users.name')
            ->orderByDesc('served')
            ->limit(5)
            ->get();

        // ── Peak day ──
        $peakDay = DB::table('tickets')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('total')
            ->first();

        // ── Previous week for comparison ──
        $prevFrom = $from->copy()->subWeek();
        $prevTo = $to->copy()->subWeek();
        $prevTotal = DB::table('tickets')
            ->whereBetween('created_at', [$prevFrom, $prevTo])
            ->count();

        $growth = $prevTotal > 0
            ? round(($global->total - $prevTotal) / $prevTotal * 100, 1)
            : 0;
        $growthEmoji = $growth > 0 ? '📈' : ($growth < 0 ? '📉' : '➡️');

        // ── Completion rate ──
        $completionRate = $global->total > 0
            ? round($global->completed / $global->total * 100, 1)
            : 0;

        // ── Build message ──
        $msg = "📊 *Reporte Semanal Olinora*\n";
        $msg .= "_{$period}_\n\n";

        $msg .= "*Resumen General*\n";
        $msg .= "• Turnos: `{$global->total}` {$growthEmoji} {$growth}% vs semana anterior\n";
        $msg .= "• Completados: `{$global->completed}` ({$completionRate}%)\n";
        $msg .= "• Cancelados: `{$global->cancelled}` · No show: `{$global->no_show}`\n";
        $msg .= "• Espera prom: `{$global->avg_wait_min} min`\n";
        $msg .= "• Servicio prom: `{$global->avg_service_min} min`\n";
        $msg .= '• Rating: `'.($global->avg_rating ?? '—')."/5` ({$global->total_ratings} eval)\n";

        if ($peakDay) {
            $peakDate = Carbon::parse($peakDay->date)->format('l d/M');
            $msg .= "• Día pico: `{$peakDate}` con `{$peakDay->total}` turnos\n";
        }

        // Branches
        if ($branches->isNotEmpty()) {
            $msg .= "\n*Por Sucursal*\n";
            foreach ($branches as $b) {
                $r = $b->rating ? " ★{$b->rating}" : '';
                $msg .= "• {$b->name}: `{$b->total}` (✓{$b->completed}) ~{$b->avg_wait}min{$r}\n";
            }
        }

        // Top operators
        if ($operators->isNotEmpty()) {
            $msg .= "\n*Top Operadores*\n";
            foreach ($operators as $i => $op) {
                $medal = match ($i) {
                    0 => '🥇', 1 => '🥈', 2 => '🥉', default => '•'
                };
                $r = $op->rating ? " ★{$op->rating}" : '';
                $msg .= "{$medal} {$op->name}: `{$op->served}` turnos, ~{$op->avg_min}min{$r}\n";
            }
        }

        $msg .= "\n_Generado: ".now()->format('d/M/Y H:i').'_';

        // Send
        $this->sendTelegram($msg);
        $this->info("Weekly report sent for {$period}");

        return self::SUCCESS;
    }

    private function sendTelegram(string $message): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! $token || ! $chatId) {
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
            $this->error('Telegram send failed: '.$e->getMessage());
        }
    }
}
