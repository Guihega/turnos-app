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
        Route::post('/', [TicketController::class, 'store']);
        Route::post('/call-next', [TicketController::class, 'callNext']);
        Route::get('/{id}', [TicketController::class, 'show']);
        Route::post('/{id}/start', [TicketController::class, 'startServing']);
        Route::post('/{id}/complete', [TicketController::class, 'complete']);
        Route::post('/{id}/transfer', [TicketController::class, 'transfer']);
        Route::post('/{id}/cancel', [TicketController::class, 'cancel']);
    });

    // ── Branch-scoped routes ──
    Route::prefix('branches/{branchId}')->middleware('branch.access')->group(function () {

        // Tickets listing
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/active', [TicketController::class, 'active']);

        // Dashboard & Metrics
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

    // ── Tenant-level (multi-branch) ──
    Route::get('/dashboard/branches', [DashboardController::class, 'branchComparison']);
});

/*
|--------------------------------------------------------------------------
| Public Routes (kiosk / customer-facing)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/public')->group(function () {

    // Customer self-service: issue ticket
    Route::post('/branches/{branchId}/tickets', [TicketController::class, 'store'])
        ->middleware('throttle:10,1'); // 10 requests per minute

    // Check ticket status
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
        $tickets = \App\Models\Ticket::with(['queue:id,name,prefix', 'counter:id,name,number'])
            ->where('branch_id', $branchId)
            ->whereIn('status', ['called', 'in_progress'])
            ->orderByDesc('called_at')
            ->limit(10)
            ->get(['id', 'display_number', 'queue_id', 'counter_id', 'status', 'called_at']);

        $waiting = \App\Models\Ticket::where('branch_id', $branchId)
            ->where('status', 'waiting')
            ->count();

        return response()->json([
            'now_serving' => $tickets->map(fn ($t) => [
                'number' => $t->display_number,
                'counter' => $t->counter?->number,
                'queue' => $t->queue?->name,
                'called_at' => $t->called_at?->toIso8601String(),
            ]),
            'waiting_count' => $waiting,
        ]);
    })->middleware('throttle:60,1');
});
