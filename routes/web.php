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
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\Admin\DisplayAnnouncementController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\HealthController;
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

// Legal pages (public)
Route::get('/privacidad', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terminos', [LegalController::class, 'terms'])->name('legal.terms');

// Health check (public — used by CI/CD post-deploy verification)
Route::get('/health', HealthController::class)->name('health');

// Onboarding — Registro público de nuevos tenants
Route::middleware('guest')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'create'])
        ->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('onboarding.store');
    Route::get('/onboarding/check-slug', [OnboardingController::class, 'checkSlug'])
        ->middleware('throttle:30,1')
        ->name('onboarding.check-slug');
});

// Two-Factor Authentication Challenge (post-login, before full auth)
Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
    ->name('two-factor.challenge');
Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('two-factor.challenge.verify');

// OAuth — Social Login (guests: login + registro)
Route::middleware('guest')->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->name('social.callback');
});

// OAuth — Vincular/Desvincular desde Perfil (autenticadas)
Route::middleware('auth')->group(function () {
    Route::get('/auth/{provider}/link', [SocialAuthController::class, 'link'])
        ->name('social.link');
    Route::get('/auth/{provider}/link/callback', [SocialAuthController::class, 'linkCallback'])
        ->name('social.link.callback');
    Route::delete('/auth/{provider}/unlink', [SocialAuthController::class, 'unlink'])
        ->name('social.unlink');
});

// Kiosco público (rate limited)
Route::prefix('kiosco/{branch}')->group(function () {
    Route::get('/', [KioskController::class, 'index'])
        ->middleware('throttle:kiosk-view')
        ->name('kiosk.public');
    Route::post('/turno', [KioskController::class, 'store'])
        ->middleware(['throttle:kiosk-issue', 'throttle:kiosk-branch-hourly'])
        ->name('kiosk.store');
    Route::get('/turno/{ticket}', [KioskController::class, 'status'])
        ->middleware('throttle:kiosk-view')
        ->name('kiosk.status');
});

// Pantalla pública de display (TVs de sala de espera, rate limited)
Route::get('/pantalla-publica/{branch}', [DisplayController::class, 'public'])
    ->middleware('throttle:display-public')
    ->name('display.public');

// API del clima para pantalla pública (cache 30min)
Route::get('/api/weather/{branch}', [WeatherController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('api.weather');

// API de GeoNames — estados y ciudades por país (cache 30 días)
Route::middleware('throttle:60,1')->prefix('api/geo')->group(function () {
    Route::get('/states/{country}', [GeoController::class, 'states'])->name('api.geo.states');
    Route::get('/cities/{country}/{stateGeonameId}', [GeoController::class, 'cities'])->name('api.geo.cities');
    Route::get('/search/{country}', [GeoController::class, 'search'])->name('api.geo.search');
});

// Ruta corta para QR → redirige al kiosco (ej: olinora.com.mx/t/sede-centro)
Route::get('/t/{branch:slug}', function (App\Models\Branch $branch) {
    return redirect()->route('kiosk.public', $branch);
})->middleware('throttle:kiosk-view')->name('kiosk.shorturl');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (no tenant scope — general documentation)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/ayuda', [HelpCenterController::class, 'index'])->name('help.index');
});

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

    // ── Two-Factor Authentication Setup ──
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

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
        Route::post('/emitir', [TicketActionController::class, 'issue'])
            ->middleware('throttle:30,1')
            ->name('issue');
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

        // ── Analytics Dashboard ──
        Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');

        // ── QR Codes para sucursales ──
        Route::get('/codigos-qr', [DashboardController::class, 'qrCodes'])->name('qr.index');

        // ── Personalización del Tenant (White-Label) ──
        Route::get('/personalizacion', [TenantSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/personalizacion/marca', [TenantSettingsController::class, 'updateBranding'])->name('settings.branding');
        Route::put('/personalizacion/pantalla', [TenantSettingsController::class, 'updateDisplay'])->name('settings.display');
        Route::put('/personalizacion/kiosco', [TenantSettingsController::class, 'updateKiosk'])->name('settings.kiosk');
        Route::put('/personalizacion/turnos', [TenantSettingsController::class, 'updateTickets'])->name('settings.tickets');
        Route::put('/personalizacion/seguridad', [TenantSettingsController::class, 'updateSecurity'])->name('settings.security');
        Route::post('/personalizacion/logo', [TenantSettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
        Route::delete('/personalizacion/logo', [TenantSettingsController::class, 'removeLogo'])->name('settings.logo.remove');

        // ── Anuncios de Pantalla ──
        Route::get('/anuncios', [DisplayAnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/anuncios', [DisplayAnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('/anuncios/{announcement}', [DisplayAnnouncementController::class, 'update'])->name('announcements.update');
        Route::patch('/anuncios/{announcement}/toggle', [DisplayAnnouncementController::class, 'toggle'])->name('announcements.toggle');
        Route::delete('/anuncios/{announcement}', [DisplayAnnouncementController::class, 'destroy'])->name('announcements.destroy');

        // Métricas API (JSON)
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/metrics/realtime/{branch}', [DashboardController::class, 'realtime'])->name('metrics.realtime');
            Route::get('/metrics/hourly/{branch}', [DashboardController::class, 'hourly'])->name('metrics.hourly');
            Route::get('/metrics/services/{branch}', [DashboardController::class, 'services'])->name('metrics.services');
            Route::get('/metrics/operators/{branch}', [DashboardController::class, 'operators'])->name('metrics.operators');
            Route::get('/metrics/trend/{branch}', [DashboardController::class, 'trend'])->name('metrics.trend');
            Route::get('/metrics/branches', [DashboardController::class, 'branchComparison'])->name('metrics.branches');
            Route::get('/metrics/heatmap/{branch}', [DashboardController::class, 'heatmap'])->name('metrics.heatmap');
        });
    });
});

require __DIR__ . '/auth.php';
