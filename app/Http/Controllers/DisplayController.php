<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DisplayController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $branches = ($user->isSuperAdmin() || $user->isTenantAdmin())
            ? Branch::where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->get()
            : Branch::where('is_active', true)
                ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
                ->get();

        if ($branches->count() === 1) {
            return Inertia::render('Display/Screen', [
                'branch' => [
                    'id' => $branches->first()->id,
                    'name' => $branches->first()->name,
                    'code' => $branches->first()->code,
                ],
                'initialData' => $this->getDisplayData($branches->first()),
                'branding' => $user->tenant->getBrandingForFrontend(),
            ]);
        }

        return Inertia::render('Display/Index', [
            'branches' => $branches->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
            ]),
        ]);
    }

    public function show(Request $request, Branch $branch): Response
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin()) {
            $hasAccess = $user->branches()->where('branches.id', $branch->id)->exists();
            if (!$hasAccess) {
                abort(403, 'No tiene acceso a esta sucursal.');
            }
        }

        return Inertia::render('Display/Screen', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code],
            'initialData' => $this->getDisplayData($branch),
            'branding' => $user->tenant->getBrandingForFrontend(),
        ]);
    }

    public function public(Request $request, Branch $branch): Response
    {
        // F-08: Validate branch is active and belongs to an active tenant
        if (!$branch->is_active) {
            abort(404, 'Sucursal no disponible.');
        }

        $branch->loadMissing('tenant');
        if (!$branch->tenant || !$branch->tenant->is_active) {
            abort(404, 'Organización no disponible.');
        }

        return Inertia::render('Display/Screen', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code],
            'initialData' => $this->getDisplayData($branch),
            'isPublic' => true,
            'branding' => $branch->tenant->getBrandingForFrontend(),
        ]);
    }

    /**
     * F-08: Cached display data to reduce DB load from TV screen polling.
     * Cache TTL: 5 seconds — frequent enough for near-real-time display.
     */
    private function getDisplayData(Branch $branch): array
    {
        return Cache::remember(
            "display:data:{$branch->id}",
            5, // seconds
            function () use ($branch) {
                $serving = Ticket::with(['queue:id,name,prefix', 'counter:id,name,number', 'service:id,name,color'])
                    ->where('branch_id', $branch->id)
                    ->whereIn('status', ['called', 'in_progress'])
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
                        'status' => is_object($t->status) ? $t->status->value : $t->status,
                        'called_at' => $t->called_at?->toIso8601String(),
                    ]);

                $recent = Ticket::with(['counter:id,number'])
                    ->where('branch_id', $branch->id)
                    ->where('status', 'completed')
                    ->whereDate('created_at', today())
                    ->orderByDesc('completed_at')
                    ->limit(10)
                    ->get()
                    ->map(fn($t) => [
                        'display_number' => $t->display_number,
                        'counter_number' => $t->counter?->number,
                    ]);

                $waitingCount = Ticket::where('branch_id', $branch->id)
                    ->where('status', 'waiting')
                    ->count();

                $avgWait = (int) Ticket::where('branch_id', $branch->id)
                    ->where('status', 'completed')
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
        );
    }
}
