<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\CounterController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\TicketActionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes (no auth)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Welcome');
});

// Kiosco público (rate limited)
Route::prefix('kiosco/{branch}')->group(function () {
    Route::get('/', [KioskController::class, 'index'])
        ->middleware('throttle:kiosk-view')
        ->name('kiosk.public');
    Route::post('/turno', [KioskController::class, 'store'])
        ->middleware('throttle:kiosk-issue')
        ->name('kiosk.store');
    Route::get('/turno/{ticket}', [KioskController::class, 'status'])
        ->middleware('throttle:kiosk-view')
        ->name('kiosk.status');
});

// Pantalla pública de display (TVs de sala de espera, rate limited)
Route::get('/pantalla-publica/{branch}', [DisplayController::class, 'public'])
    ->middleware('throttle:display-public')
    ->name('display.public');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (tenant-scoped)
|--------------------------------------------------------------------------
| All authenticated routes enforce tenant isolation via middleware.
*/

Route::middleware(['auth', 'verified', 'tenant.scope'])->group(function () {

    // ── Dashboard ──
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Perfil ──
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Operador (requiere permiso tickets.call) ──
    Route::middleware('role:operator')->group(function () {
        Route::get('/atencion', [OperatorController::class, 'index'])->name('operator.index');
        Route::post('/atencion/llamar', [OperatorController::class, 'callNext'])->name('operator.call');
        Route::post('/atencion/iniciar/{ticket}', [OperatorController::class, 'startServing'])->name('operator.start');
        Route::post('/atencion/completar/{ticket}', [OperatorController::class, 'complete'])->name('operator.complete');
        Route::post('/atencion/cancelar/{ticket}', [OperatorController::class, 'cancel'])->name('operator.cancel');
        Route::post('/atencion/transferir/{ticket}', [OperatorController::class, 'transfer'])->name('operator.transfer');
        Route::post('/atencion/no-show/{ticket}', [OperatorController::class, 'noShow'])->name('operator.noshow');
        Route::post('/atencion/rellamar/{ticket}', [OperatorController::class, 'recall'])->name('operator.recall');
    });

    // ── Pantalla de Sala de Espera (auth) ──
    Route::get('/pantalla', [DisplayController::class, 'index'])->name('display.index');
    Route::get('/pantalla/{branch}', [DisplayController::class, 'show'])->name('display.show');

    // ── Tickets (emisión rápida + detalle) ──
    Route::middleware('role:staff')->prefix('tickets')->name('tickets.')->group(function () {
        Route::post('/emitir', [TicketActionController::class, 'issue'])->name('issue');
        Route::get('/{ticket}', [TicketActionController::class, 'show'])->name('show');
    });

    // ── Administración (requiere rol admin) ──
    Route::middleware('role:admin')->prefix('administracion')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');

        Route::resource('sucursales', BranchController::class)->parameters(['sucursales' => 'branch']);
        Route::resource('servicios', ServiceController::class)->parameters(['servicios' => 'service']);
        Route::resource('colas', QueueController::class)->parameters(['colas' => 'queue']);
        Route::resource('ventanillas', CounterController::class)->parameters(['ventanillas' => 'counter']);
        Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user']);

        Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reportes/exportar', [ReportController::class, 'export'])->name('reports.export');

        // Métricas API (JSON)
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/metrics/realtime/{branch}', [DashboardController::class, 'realtime'])->name('metrics.realtime');
            Route::get('/metrics/hourly/{branch}', [DashboardController::class, 'hourly'])->name('metrics.hourly');
            Route::get('/metrics/services/{branch}', [DashboardController::class, 'services'])->name('metrics.services');
            Route::get('/metrics/operators/{branch}', [DashboardController::class, 'operators'])->name('metrics.operators');
            Route::get('/metrics/trend/{branch}', [DashboardController::class, 'trend'])->name('metrics.trend');
            Route::get('/metrics/branches', [DashboardController::class, 'branchComparison'])->name('metrics.branches');
        });
    });
});

require __DIR__ . '/auth.php';
