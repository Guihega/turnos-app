<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Service;
use App\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dashboard principal — resumen general.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $branches = Branch::where('tenant_id', $tenantId)->active()->get();
        $branchId = $request->input('branch_id', $branches->first()?->id);
        $branch = $branches->firstWhere('id', $branchId) ?? $branches->first();

        $today = today();

        // Stats del día
        $todayStats = $branch ? $this->getTodayStats($branch->id) : [];

        // Tickets activos en tiempo real
        $activeTickets = $branch ? Ticket::with(['queue:id,name,prefix', 'service:id,name,color', 'counter:id,name,number', 'servedBy:id,name'])
            ->where('branch_id', $branch->id)
            ->active()
            ->orderByDesc('priority_score')
            ->orderBy('issued_at')
            ->limit(20)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'display_number' => $t->display_number,
                'customer_name' => $t->customer_name,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'status_color' => $t->status->color(),
                'priority' => $t->priority->value,
                'priority_label' => $t->priority->label(),
                'queue_name' => $t->queue?->name,
                'queue_prefix' => $t->queue?->prefix,
                'service_name' => $t->service?->name,
                'service_color' => $t->service?->color,
                'counter_number' => $t->counter?->number,
                'operator_name' => $t->servedBy?->name,
                'wait_seconds' => $t->issued_at ? now()->diffInSeconds($t->issued_at) : 0,
                'service_seconds' => $t->started_at ? now()->diffInSeconds($t->started_at) : 0,
                'issued_at' => $t->issued_at?->toIso8601String(),
            ]) : collect();

        // Colas con conteos
        $queues = $branch ? Queue::where('branch_id', $branch->id)
            ->active()
            ->withCount([
                'tickets as waiting_count' => fn($q) => $q->where('status', TicketStatus::WAITING)->whereDate('created_at', $today),
                'tickets as in_progress_count' => fn($q) => $q->where('status', TicketStatus::IN_PROGRESS)->whereDate('created_at', $today),
                'tickets as completed_count' => fn($q) => $q->where('status', TicketStatus::COMPLETED)->whereDate('created_at', $today),
            ])
            ->get()
            ->map(fn($q) => [
                'id' => $q->id,
                'name' => $q->name,
                'prefix' => $q->prefix,
                'waiting' => $q->waiting_count,
                'in_progress' => $q->in_progress_count,
                'completed' => $q->completed_count,
            ]) : collect();

        return Inertia::render('Dashboard', [
            'branches' => $branches->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
            ]),
            'currentBranch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ] : null,
            'todayStats' => $todayStats,
            'activeTickets' => $activeTickets,
            'queues' => $queues,
        ]);
    }

    /**
     * Dashboard de administración.
     */
    public function admin(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $branches = Branch::where('tenant_id', $tenantId)->active()->get();
        $branchId = $request->input('branch_id', $branches->first()?->id);

        $todayStats = $branchId ? $this->getTodayStats($branchId) : [];

        // Operadores activos
        $operators = User::where('tenant_id', $tenantId)
            ->whereIn('role', ['operator', 'branch_manager'])
            ->active()
            ->withCount([
                'servedTickets as today_served' => fn($q) => $q->where('status', TicketStatus::COMPLETED)->whereDate('created_at', today()),
            ])
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role->label(),
                'today_served' => $u->today_served,
            ]);

        // Servicios con stats
        $services = Service::where('tenant_id', $tenantId)->active()->get();

        // Comparación de sucursales
        $branchStats = $this->getBranchComparison($tenantId);

        return Inertia::render('Admin/Dashboard', [
            'branches' => $branches->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'code' => $b->code]),
            'currentBranchId' => $branchId,
            'todayStats' => $todayStats,
            'operators' => $operators,
            'services' => $services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'color' => $s->color, 'code' => $s->code]),
            'branchStats' => $branchStats,
        ]);
    }

    /**
     * API: Métricas en tiempo real para polling.
     */
    public function realtime(Request $request, Branch $branch): JsonResponse
    {
        return response()->json($this->getTodayStats($branch->id));
    }

    /**
     * API: Distribución horaria.
     */
    public function hourly(Request $request, Branch $branch): JsonResponse
    {
        $date = $request->input('date', today()->toDateString());

        $data = DB::table('tickets')
            ->where('branch_id', $branch->id)
            ->whereDate('created_at', $date)
            ->selectRaw("
                EXTRACT(HOUR FROM created_at)::int as hour,
                COUNT(*) as issued,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL))::int as avg_wait
            ")
            ->groupByRaw('EXTRACT(HOUR FROM created_at)')
            ->orderBy('hour')
            ->get();

        return response()->json(['data' => $data]);
    }

    /**
     * API: Desglose por servicios.
     */
    public function services(Request $request, Branch $branch): JsonResponse
    {
        $data = DB::table('tickets')
            ->join('services', 'tickets.service_id', '=', 'services.id')
            ->where('tickets.branch_id', $branch->id)
            ->whereDate('tickets.created_at', today())
            ->selectRaw("
                services.name, services.color,
                COUNT(*) as total,
                COUNT(CASE WHEN tickets.status = 'completed' THEN 1 END) as completed,
                ROUND(AVG(tickets.wait_time_seconds) FILTER (WHERE tickets.wait_time_seconds IS NOT NULL))::int as avg_wait,
                ROUND(AVG(tickets.service_time_seconds) FILTER (WHERE tickets.service_time_seconds IS NOT NULL))::int as avg_service
            ")
            ->groupBy('services.name', 'services.color')
            ->orderByDesc('total')
            ->get();

        return response()->json(['data' => $data]);
    }

    /**
     * API: Rendimiento de operadores.
     */
    public function operators(Request $request, Branch $branch): JsonResponse
    {
        $data = DB::table('tickets')
            ->join('users', 'tickets.served_by', '=', 'users.id')
            ->where('tickets.branch_id', $branch->id)
            ->whereNotNull('tickets.served_by')
            ->whereDate('tickets.created_at', today())
            ->selectRaw("
                users.name,
                COUNT(*) as served,
                ROUND(AVG(tickets.service_time_seconds) FILTER (WHERE tickets.service_time_seconds IS NOT NULL))::int as avg_service,
                ROUND(AVG(tickets.rating) FILTER (WHERE tickets.rating IS NOT NULL), 1) as avg_rating
            ")
            ->groupBy('users.name')
            ->orderByDesc('served')
            ->get();

        return response()->json(['data' => $data]);
    }

    /**
     * API: Tendencia de los últimos N días.
     */
    public function trend(Request $request, Branch $branch): JsonResponse
    {
        $days = $request->integer('days', 14);

        $data = DB::table('tickets')
            ->where('branch_id', $branch->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as tickets,
                ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL))::int as avg_wait
            ")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $data]);
    }

    /**
     * API: Comparación de sucursales.
     */
    public function branchComparison(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return response()->json(['data' => $this->getBranchComparison($tenantId)]);
    }

    // ── Helpers privados ──

    private function getTodayStats(string $branchId): array
    {
        $result = DB::table('tickets')
            ->where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->selectRaw("
                COUNT(*) as total_issued,
                COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting,
                COUNT(CASE WHEN status = 'called' THEN 1 END) as called,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
                COALESCE(ROUND(AVG(wait_time_seconds) FILTER (WHERE wait_time_seconds IS NOT NULL)), 0)::int as avg_wait,
                COALESCE(ROUND(AVG(service_time_seconds) FILTER (WHERE service_time_seconds IS NOT NULL)), 0)::int as avg_service,
                COALESCE(MAX(wait_time_seconds), 0)::int as max_wait,
                ROUND(AVG(rating) FILTER (WHERE rating IS NOT NULL), 1) as avg_rating,
                COUNT(rating) as total_ratings
            ")
            ->first();

        return $result ? (array) $result : [];
    }

    private function getBranchComparison(string $tenantId): array
    {
        return DB::table('tickets')
            ->join('branches', 'tickets.branch_id', '=', 'branches.id')
            ->where('branches.tenant_id', $tenantId)
            ->whereDate('tickets.created_at', today())
            ->selectRaw("
                branches.id, branches.name, branches.code,
                COUNT(*) as total,
                COUNT(CASE WHEN tickets.status = 'completed' THEN 1 END) as completed,
                COALESCE(ROUND(AVG(tickets.wait_time_seconds) FILTER (WHERE tickets.wait_time_seconds IS NOT NULL)), 0)::int as avg_wait,
                ROUND(AVG(tickets.rating) FILTER (WHERE tickets.rating IS NOT NULL), 1) as avg_rating
            ")
            ->groupBy('branches.id', 'branches.name', 'branches.code')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }
}
