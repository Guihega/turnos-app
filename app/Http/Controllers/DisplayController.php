<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DisplayController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Super admin / tenant admin → all branches
        // Everyone else → only assigned branches
        $branches = ($user->isSuperAdmin() || $user->isTenantAdmin())
            ? Branch::where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->get()
            : Branch::where('is_active', true)
                ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
                ->get();

        // If user has only one branch, redirect directly to the screen
        if ($branches->count() === 1) {
            return Inertia::render('Display/Screen', [
                'branch' => [
                    'id' => $branches->first()->id,
                    'name' => $branches->first()->name,
                    'code' => $branches->first()->code,
                ],
                'initialData' => $this->getDisplayData($branches->first()),
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
        // Verify user has access to this branch
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
        ]);
    }

    public function public(Request $request, Branch $branch): Response
    {
        return Inertia::render('Display/Screen', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code],
            'initialData' => $this->getDisplayData($branch),
            'isPublic' => true,
        ]);
    }

    private function getDisplayData(Branch $branch): array
    {
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
            ->limit(5)
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
}