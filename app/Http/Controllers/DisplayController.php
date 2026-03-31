<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DisplayController extends Controller
{
    /**
     * Selector de pantalla (con auth).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $branches = Branch::where('tenant_id', $user->tenant_id)->active()->get();

        return Inertia::render('Display/Index', [
            'branches' => $branches->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
            ]),
        ]);
    }

    /**
     * Pantalla de sala de espera para una sucursal (con auth).
     */
    public function show(Request $request, Branch $branch): Response
    {
        return Inertia::render('Display/Screen', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ],
            'initialData' => $this->getDisplayData($branch),
        ]);
    }

    /**
     * Pantalla pública (sin auth) — para TVs de sala de espera.
     */
    public function public(Request $request, Branch $branch): Response
    {
        return Inertia::render('Display/Screen', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ],
            'initialData' => $this->getDisplayData($branch),
            'isPublic' => true,
        ]);
    }

    /**
     * Datos para la pantalla de display (también usado como API).
     */
    private function getDisplayData(Branch $branch): array
    {
        // Turnos llamados y en atención (mostrar en pantalla grande)
        $serving = Ticket::with(['queue:id,name,prefix', 'counter:id,name,number', 'service:id,name,color'])
            ->where('branch_id', $branch->id)
            ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_PROGRESS])
            ->whereDate('created_at', today())
            ->orderByDesc('called_at')
            ->limit(8)
            ->get()
            ->map(fn($t) => [
                'display_number' => $t->display_number,
                'counter_number' => $t->counter?->number,
                'counter_name' => $t->counter?->name,
                'queue_name' => $t->queue?->name,
                'service_name' => $t->service?->name,
                'service_color' => $t->service?->color,
                'status' => $t->status->value,
                'called_at' => $t->called_at?->toIso8601String(),
            ]);

        // Últimos completados (historial reciente)
        $recent = Ticket::with(['counter:id,number'])
            ->where('branch_id', $branch->id)
            ->where('status', TicketStatus::COMPLETED)
            ->whereDate('created_at', today())
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'display_number' => $t->display_number,
                'counter_number' => $t->counter?->number,
            ]);

        // Conteo de espera
        $waitingCount = Ticket::where('branch_id', $branch->id)
            ->where('status', TicketStatus::WAITING)
            ->whereDate('created_at', today())
            ->count();

        // Tiempo estimado de espera
        $avgWait = (int) Ticket::where('branch_id', $branch->id)
            ->where('status', TicketStatus::COMPLETED)
            ->whereDate('created_at', today())
            ->whereNotNull('wait_time_seconds')
            ->avg('wait_time_seconds');

        return [
            'serving' => $serving,
            'recent' => $recent,
            'waitingCount' => $waitingCount,
            'avgWaitMinutes' => (int) round($avgWait / 60),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
