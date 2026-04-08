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

        // Prevent lazy loading in development (catches N+1 queries)
        Model::preventLazyLoading(!$this->app->isProduction());

        // Prevent silently discarding attributes
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());

        // Register policies
        Gate::policy(\App\Models\Ticket::class, \App\Policies\TicketPolicy::class);

        // ── Rate Limiters (production-tuned) ──

        // Kiosk ticket issuance: 15/min per IP (peak hours, multiple people on same kiosk)
        RateLimiter::for('kiosk-issue', function ($request) {
            return Limit::perMinute(15)
                ->by($request->ip())
                ->response(function () {
                    return back()->withErrors(['branch' => 'Demasiados turnos emitidos. Espera un momento.']);
                });
        });

        // Kiosk page views: 60/min per IP (status page auto-refreshes)
        RateLimiter::for('kiosk-view', function ($request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Public display: 120/min per IP (TV screens refresh frequently)
        RateLimiter::for('display-public', function ($request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // API metrics: 30/min per user (admin dashboard auto-refresh)
        RateLimiter::for('api-metrics', function ($request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}