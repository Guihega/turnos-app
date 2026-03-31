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

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'phpVersion' => PHP_VERSION,
    ]);
});

// ══════════════════════════════════════════
// Dashboard principal
// ══════════════════════════════════════════
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// ══════════════════════════════════════════
// Perfil de usuario
// ══════════════════════════════════════════
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ══════════════════════════════════════════
// Rutas autenticadas de TurnosPro
// ══════════════════════════════════════════
Route::middleware(['auth', 'verified'])->group(function () {

    // ── Vista del Operador (Atención de turnos) ──
    Route::get('/atencion', [OperatorController::class, 'index'])->name('operator.index');
    Route::post('/atencion/llamar', [OperatorController::class, 'callNext'])->name('operator.call');
    Route::post('/atencion/iniciar/{ticket}', [OperatorController::class, 'startServing'])->name('operator.start');
    Route::post('/atencion/completar/{ticket}', [OperatorController::class, 'complete'])->name('operator.complete');
    Route::post('/atencion/cancelar/{ticket}', [OperatorController::class, 'cancel'])->name('operator.cancel');
    Route::post('/atencion/transferir/{ticket}', [OperatorController::class, 'transfer'])->name('operator.transfer');
    Route::post('/atencion/no-show/{ticket}', [OperatorController::class, 'noShow'])->name('operator.noshow');
    Route::post('/atencion/rellamar/{ticket}', [OperatorController::class, 'recall'])->name('operator.recall');

    // ── Pantalla de Sala de Espera ──
    Route::get('/pantalla', [DisplayController::class, 'index'])->name('display.index');
    Route::get('/pantalla/{branch}', [DisplayController::class, 'show'])->name('display.show');

    // ── Administración ──
    Route::prefix('administracion')->name('admin.')->group(function () {

        // Dashboard admin
        Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');

        // CRUD Sucursales
        Route::resource('sucursales', BranchController::class)->parameters([
            'sucursales' => 'branch',
        ]);

        // CRUD Servicios
        Route::resource('servicios', ServiceController::class)->parameters([
            'servicios' => 'service',
        ]);

        // CRUD Colas
        Route::resource('colas', QueueController::class)->parameters([
            'colas' => 'queue',
        ]);

        // CRUD Ventanillas
        Route::resource('ventanillas', CounterController::class)->parameters([
            'ventanillas' => 'counter',
        ]);

        // CRUD Usuarios
        Route::resource('usuarios', UserController::class)->parameters([
            'usuarios' => 'user',
        ]);

        // Reportes
        Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reportes/exportar', [ReportController::class, 'export'])->name('reports.export');

        // Métricas API (JSON para gráficas)
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/metrics/realtime/{branch}', [DashboardController::class, 'realtime'])->name('metrics.realtime');
            Route::get('/metrics/hourly/{branch}', [DashboardController::class, 'hourly'])->name('metrics.hourly');
            Route::get('/metrics/services/{branch}', [DashboardController::class, 'services'])->name('metrics.services');
            Route::get('/metrics/operators/{branch}', [DashboardController::class, 'operators'])->name('metrics.operators');
            Route::get('/metrics/trend/{branch}', [DashboardController::class, 'trend'])->name('metrics.trend');
            Route::get('/metrics/branches', [DashboardController::class, 'branchComparison'])->name('metrics.branches');
        });
    });

    // ── Acciones rápidas sobre tickets (AJAX/Inertia) ──
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::post('/emitir', [TicketActionController::class, 'issue'])->name('issue');
        Route::get('/{ticket}', [TicketActionController::class, 'show'])->name('show');
    });
});

// ══════════════════════════════════════════
// Ruta Pública para el Kiosco
// ══════════════════════════════════════════
//Route::get('/kiosco/{branch}', [KioskController::class, 'index'])->name('kiosk.public');
Route::get('/kiosko/{branch}', [KioskController::class, 'index']);
Route::post('/kiosco/{branch}/turno', [KioskController::class, 'store'])->name('kiosk.store');
Route::get('/kiosco/{branch}/turno/{ticket}', [KioskController::class, 'status'])->name('kiosk.status');

// 2. Ruta de prueba rápida para confirmar el log

// ══════════════════════════════════════════
// Pantalla pública de display (sin auth)
// ══════════════════════════════════════════
Route::get('/pantalla-publica/{branch}', [DisplayController::class, 'public'])->name('display.public');

require __DIR__ . '/auth.php';