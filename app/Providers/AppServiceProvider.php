<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Model::preventLazyLoading(!$this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());

        Gate::policy(\App\Models\Ticket::class, \App\Policies\TicketPolicy::class);

        // ══════════════════════════════════════════════════════════════
        // Rate Limiters — Defense in Depth against mass ticket generation
        // ══════════════════════════════════════════════════════════════

        // ── Layer 1: Per-IP rate limit for kiosk ticket issuance ──
        // 5/min per IP (reduced from 15 — a real kiosk tablet only needs ~2-3/min)
        RateLimiter::for('kiosk-issue', function ($request) {
            return [
                // Per IP: 5 tickets per minute
                Limit::perMinute(5)
                    ->by($request->ip())
                    ->response(function () {
                        return back()->withErrors(['branch' => 'Demasiados turnos emitidos. Espera un momento.']);
                    }),

                // Per IP + Branch: 3 tickets per minute per branch
                // Prevents one IP from flooding a specific branch
                Limit::perMinute(3)
                    ->by($request->ip() . '|' . $request->route('branch'))
                    ->response(function () {
                        return back()->withErrors(['branch' => 'Demasiados turnos para esta sucursal. Espera un momento.']);
                    }),
            ];
        });

        // ── Layer 2: Per-branch global rate limit (all IPs combined) ──
        // Max 60 tickets/hour per branch — configurable via branch settings
        // This is the CRITICAL defense: even with rotating IPs, a branch
        // can't receive more than this limit per hour
        RateLimiter::for('kiosk-branch-hourly', function ($request) {
            $branchId = $request->route('branch');
            return Limit::perHour(60)
                ->by('branch-hourly:' . $branchId)
                ->response(function () {
                    return back()->withErrors(['branch' => 'Se alcanzó el límite de turnos por hora para esta sucursal.']);
                });
        });

        // Kiosk page views: 30/min per IP (reduced from 60)
        RateLimiter::for('kiosk-view', function ($request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Public display: 60/min per IP (TV screens)
        RateLimiter::for('display-public', function ($request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // API metrics: 30/min per user
        RateLimiter::for('api-metrics', function ($request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // API public ticket issuance: stricter than kiosk (3/min per IP)
        RateLimiter::for('api-public-issue', function ($request) {
            return [
                Limit::perMinute(3)->by($request->ip()),
                Limit::perHour(60)->by('api-branch-hourly:' . $request->route('branchId')),
            ];
        });
    }
}
