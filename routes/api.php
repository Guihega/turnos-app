<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
| All routes require Sanctum auth + tenant scope.
| Branch-specific routes also require branch access.
*/

Route::prefix('v1')->middleware(['auth:sanctum', 'tenant.scope'])->group(function () {

    // ── Ticket Operations ──
    Route::prefix('tickets')->group(function () {
        Route::post('/', [TicketController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::post('/call-next', [TicketController::class, 'callNext']);
        Route::get('/{id}', [TicketController::class, 'show']);
        Route::post('/{id}/start', [TicketController::class, 'startServing']);
        Route::post('/{id}/complete', [TicketController::class, 'complete']);
        Route::post('/{id}/transfer', [TicketController::class, 'transfer']);
        Route::post('/{id}/cancel', [TicketController::class, 'cancel']);
    });

    // ── Branch-scoped routes ──
    Route::prefix('branches/{branchId}')->middleware('branch.access')->group(function () {
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/active', [TicketController::class, 'active']);

        Route::prefix('dashboard')->group(function () {
            Route::get('/overview', [DashboardController::class, 'overview']);
            Route::get('/realtime', [DashboardController::class, 'realtime']);
            Route::get('/hourly', [DashboardController::class, 'hourlyDistribution']);
            Route::get('/services', [DashboardController::class, 'serviceBreakdown']);
            Route::get('/operators', [DashboardController::class, 'operatorPerformance']);
            Route::get('/queues', [DashboardController::class, 'queueComparison']);
            Route::get('/trend', [DashboardController::class, 'trend']);
        });
    });

    Route::get('/dashboard/branches', [DashboardController::class, 'branchComparison']);
});

/*
|--------------------------------------------------------------------------
| Public Routes (kiosk / customer-facing)
|--------------------------------------------------------------------------
| These routes are the most vulnerable to abuse.
| Defense layers:
|   1. Per-IP rate limit (3/min)
|   2. Per-branch hourly limit (60/hour across ALL IPs)
|   3. Cross-entity validation in the controller
|   4. Branch-level limits (max_daily_tickets, max_concurrent_waiting)
*/
Route::prefix('v1/public')->group(function () {

    // Customer self-service: issue ticket
    // Uses stricter rate limiter defined in AppServiceProvider
    Route::post('/branches/{branchId}/tickets', function (\Illuminate\Http\Request $request, string $branchId) {
        $request->validate([
            'service_id'     => 'required|exists:services,id',
            'queue_id'       => 'required|exists:queues,id',
            'customer_name'  => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $branch = \App\Models\Branch::where('id', $branchId)
            ->where('is_active', true)
            ->firstOrFail();

        // Validate branch is open
        if (!$branch->isOpen()) {
            return response()->json(['message' => 'La sucursal está cerrada.'], 422);
        }

        // Cross-entity: queue belongs to branch
        $queue = \App\Models\Queue::where('id', $request->input('queue_id'))
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->first();

        if (!$queue) {
            return response()->json(['message' => 'Cola no válida.'], 422);
        }

        // Cross-entity: service linked to queue
        if (!$queue->services()->where('services.id', $request->input('service_id'))->exists()) {
            return response()->json(['message' => 'Servicio no disponible.'], 422);
        }

        // Max concurrent waiting
        if ($branch->activeWaitingCount() >= $branch->max_concurrent_waiting) {
            return response()->json(['message' => 'Demasiados turnos en espera.'], 422);
        }

        $action = app(\App\Actions\IssueTicketAction::class);

        try {
            $data = new \App\Actions\IssueTicketData(
                branchId: $branch->id,
                queueId: $queue->id,
                serviceId: $request->input('service_id'),
                customerName: $request->input('customer_name'),
                customerPhone: $request->input('customer_phone'),
            );

            $ticket = $action->execute($data);

            return response()->json([
                'id' => $ticket->id,
                'display_number' => $ticket->display_number,
                'status' => 'waiting',
                'position' => $ticket->positionInQueue(),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    })->middleware('throttle:api-public-issue');

    // Check ticket status (read-only, less strict)
    Route::get('/tickets/{id}/status', function (string $id) {
        $ticket = \App\Models\Ticket::select([
            'id', 'display_number', 'status', 'queue_id',
            'issued_at', 'called_at', 'counter_id',
        ])->with(['queue:id,name', 'counter:id,name,number'])->find($id);

        abort_if(!$ticket, 404);

        return response()->json([
            'display_number' => $ticket->display_number,
            'status' => $ticket->status->label(),
            'queue' => $ticket->queue?->name,
            'counter' => $ticket->counter?->number,
            'position' => $ticket->isActive() ? $ticket->positionInQueue() : null,
            'estimated_wait' => $ticket->isActive() ? $ticket->estimatedWaitMinutes() : null,
        ]);
    })->middleware('throttle:30,1');

    // Display board (waiting room screens)
    Route::get('/branches/{branchId}/display', function (string $branchId) {
        // F-08: Cache display data
        return response()->json(\Illuminate\Support\Facades\Cache::remember(
            "api:display:{$branchId}",
            5,
            function () use ($branchId) {
                $tickets = \App\Models\Ticket::with(['queue:id,name,prefix', 'counter:id,name,number'])
                    ->where('branch_id', $branchId)
                    ->whereIn('status', ['called', 'in_progress'])
                    ->orderByDesc('called_at')
                    ->limit(10)
                    ->get(['id', 'display_number', 'queue_id', 'counter_id', 'status', 'called_at']);

                $waiting = \App\Models\Ticket::where('branch_id', $branchId)
                    ->where('status', 'waiting')
                    ->count();

                return [
                    'now_serving' => $tickets->map(fn ($t) => [
                        'number' => $t->display_number,
                        'counter' => $t->counter?->number,
                        'queue' => $t->queue?->name,
                        'called_at' => $t->called_at?->toIso8601String(),
                    ]),
                    'waiting_count' => $waiting,
                ];
            }
        ));
    })->middleware('throttle:60,1');
});
