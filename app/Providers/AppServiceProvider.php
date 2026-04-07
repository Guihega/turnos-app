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

        // ── Rate Limiters ──

        // Kiosk ticket issuance: 5 per minute per IP
        RateLimiter::for('kiosk-issue', function ($request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return back()->withErrors(['branch' => 'Demasiados turnos emitidos. Espera un momento.']);
                });
        });

        // Kiosk page views: 30 per minute per IP
        RateLimiter::for('kiosk-view', function ($request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Public display: 60 per minute per IP (auto-refreshes every 5s)
        RateLimiter::for('display-public', function ($request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
