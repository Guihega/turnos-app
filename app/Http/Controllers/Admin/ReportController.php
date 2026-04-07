<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $branches = Branch::where('tenant_id', $tenantId)->active()->get();
        $branchId = $request->input('branch_id', $branches->first()?->id);
        $dateFrom = $request->input('date_from', today()->subDays(7)->toDateString());
        $dateTo = $request->input('date_to', today()->toDateString());

        // Summary stats
        $summary = $branchId ? DB::table('tickets')
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
                COALESCE(ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL)), 0)::int as avg_wait,
                COALESCE(ROUND(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL)), 0)::int as avg_service,
                ROUND(AVG(rating) FILTER (WHERE rating IS NOT NULL), 1) as avg_rating
            ")
            ->first() : null;

        // Daily breakdown
        $daily = $branchId ? DB::table('tickets')
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COALESCE(ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL)), 0)::int as avg_wait
            ")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get() : collect();

        // Top operators
        $operators = $branchId ? DB::table('tickets')
            ->join('users', 'tickets.served_by', '=', 'users.id')
            ->where('tickets.branch_id', $branchId)
            ->whereNotNull('tickets.served_by')
            ->whereBetween('tickets.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->selectRaw("
                users.name,
                COUNT(*) as served,
                ROUND(AVG(tickets.service_time_seconds) FILTER (WHERE tickets.service_time_seconds IS NOT NULL))::int as avg_time,
                ROUND(AVG(tickets.rating) FILTER (WHERE tickets.rating IS NOT NULL), 1) as rating
            ")
            ->groupBy('users.name')
            ->orderByDesc('served')
            ->get() : collect();

        // Recent tickets
        $tickets = $branchId ? Ticket::with(['queue:id,name', 'service:id,name,color', 'servedBy:id,name'])
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn($t) => [
                'id' => $t->id, 'display_number' => $t->display_number,
                'customer_name' => $t->customer_name, 'status' => $t->status->value,
                'status_label' => $t->status->label(), 'status_color' => $t->status->color(),
                'queue_name' => $t->queue?->name, 'service_name' => $t->service?->name,
                'operator_name' => $t->servedBy?->name, 'rating' => $t->rating,
                'wait_seconds' => $t->wait_time_seconds, 'service_seconds' => $t->service_time_seconds,
                'created_at' => $t->created_at->format('d/m H:i'),
            ]) : null;

        return Inertia::render('Admin/Reports/Index', [
            'branches' => $branches->map(fn($b) => ['id' => $b->id, 'name' => $b->name]),
            'currentBranchId' => $branchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'summary' => $summary ? (array) $summary : [],
            'daily' => $daily,
            'operators' => $operators,
            'tickets' => $tickets,
        ]);
    }

    /**
     * Export tickets as CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $tenantId = $request->user()->tenant_id;
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', today()->subDays(7)->toDateString());
        $dateTo = $request->input('date_to', today()->toDateString());

        // Validate branch belongs to tenant
        $branch = Branch::where('tenant_id', $tenantId)
            ->where('id', $branchId)
            ->firstOrFail();

        $filename = "turnos_{$branch->code}_{$dateFrom}_{$dateTo}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
        ];

        return new StreamedResponse(function () use ($branch, $branchId, $dateFrom, $dateTo) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // ── Header section ──
            fputcsv($handle, ['Reporte de Turnos — TurnosPro']);
            fputcsv($handle, ['Sucursal', $branch->name . ' (' . $branch->code . ')']);
            fputcsv($handle, ['Período', $dateFrom . ' al ' . $dateTo]);
            fputcsv($handle, ['Generado', now()->format('d/m/Y H:i:s')]);
            fputcsv($handle, []); // blank row

            // ── Summary ──
            $summary = DB::table('tickets')
                ->where('branch_id', $branchId)
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
                    COALESCE(ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL)), 0)::int as avg_wait,
                    COALESCE(ROUND(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL)), 0)::int as avg_service,
                    ROUND(AVG(rating) FILTER (WHERE rating IS NOT NULL), 1) as avg_rating
                ")
                ->first();

            fputcsv($handle, ['=== RESUMEN ===']);
            fputcsv($handle, ['Total turnos', $summary->total]);
            fputcsv($handle, ['Completados', $summary->completed]);
            fputcsv($handle, ['Cancelados', $summary->cancelled]);
            fputcsv($handle, ['No presentados', $summary->no_show]);
            fputcsv($handle, ['Espera promedio (seg)', $summary->avg_wait]);
            fputcsv($handle, ['Servicio promedio (seg)', $summary->avg_service]);
            fputcsv($handle, ['Rating promedio', $summary->avg_rating ?? 'N/A']);
            fputcsv($handle, []); // blank row

            // ── Operators ──
            $operators = DB::table('tickets')
                ->join('users', 'tickets.served_by', '=', 'users.id')
                ->where('tickets.branch_id', $branchId)
                ->whereNotNull('tickets.served_by')
                ->whereBetween('tickets.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->selectRaw("
                    users.name,
                    COUNT(*) as served,
                    ROUND(AVG(tickets.service_time_seconds) FILTER (WHERE tickets.service_time_seconds IS NOT NULL))::int as avg_time,
                    ROUND(AVG(tickets.rating) FILTER (WHERE tickets.rating IS NOT NULL), 1) as rating
                ")
                ->groupBy('users.name')
                ->orderByDesc('served')
                ->get();

            if ($operators->isNotEmpty()) {
                fputcsv($handle, ['=== OPERADORES ===']);
                fputcsv($handle, ['Operador', 'Atendidos', 'Servicio Prom. (seg)', 'Rating']);
                foreach ($operators as $op) {
                    fputcsv($handle, [$op->name, $op->served, $op->avg_time, $op->rating ?? 'N/A']);
                }
                fputcsv($handle, []); // blank row
            }

            // ── Ticket detail (streamed in chunks) ──
            fputcsv($handle, ['=== DETALLE DE TURNOS ===']);
            fputcsv($handle, [
                'Turno', 'Cliente', 'Teléfono', 'Email',
                'Servicio', 'Cola', 'Operador', 'Estado',
                'Prioridad', 'Emitido', 'Llamado', 'Iniciado', 'Completado',
                'Espera (seg)', 'Servicio (seg)', 'Total (seg)',
                'Rating', 'Notas',
            ]);

            Ticket::with(['queue:id,name', 'service:id,name', 'servedBy:id,name'])
                ->where('branch_id', $branchId)
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->orderByDesc('created_at')
                ->chunk(200, function ($tickets) use ($handle) {
                    foreach ($tickets as $t) {
                        fputcsv($handle, [
                            $t->display_number,
                            $t->customer_name ?? '',
                            $t->customer_phone ?? '',
                            $t->customer_email ?? '',
                            $t->service?->name ?? '',
                            $t->queue?->name ?? '',
                            $t->servedBy?->name ?? '',
                            $t->status->label(),
                            $t->priority->label(),
                            $t->issued_at?->format('d/m/Y H:i'),
                            $t->called_at?->format('d/m/Y H:i'),
                            $t->started_at?->format('d/m/Y H:i'),
                            $t->completed_at?->format('d/m/Y H:i'),
                            $t->wait_time_seconds,
                            $t->service_time_seconds,
                            $t->total_time_seconds,
                            $t->rating,
                            $t->notes ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}
