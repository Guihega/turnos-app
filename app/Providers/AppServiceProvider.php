<?php

namespace App\Providers;

use App\Models\Branch;
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
        // Rate Limiters — Tenant-configurable defense layers
        // Settings are read from tenant.settings.security via cache
        // Admins can adjust these in /administracion/personalizacion
        // ══════════════════════════════════════════════════════════════

        // ── Kiosk ticket issuance: per-IP + per-branch ──
        RateLimiter::for('kiosk-issue', function ($request) {
            $settings = $this->getBranchSecuritySettings($request->route('branch'));
            $perIpMin = $settings['max_tickets_per_ip_minute'] ?? 3;

            return [
                Limit::perMinute($perIpMin + 2)
                    ->by($request->ip())
                    ->response(function () {
                        return back()->withErrors(['branch' => 'Demasiados turnos emitidos. Espera un momento.']);
                    }),
                Limit::perMinute($perIpMin)
                    ->by($request->ip() . '|' . $request->route('branch'))
                    ->response(function () {
                        return back()->withErrors(['branch' => 'Demasiados turnos para esta sucursal. Espera un momento.']);
                    }),
            ];
        });

        // ── Branch hourly limit (all IPs combined) ──
        RateLimiter::for('kiosk-branch-hourly', function ($request) {
            $settings = $this->getBranchSecuritySettings($request->route('branch'));
            $maxPerHour = $settings['max_tickets_per_hour'] ?? 60;

            return Limit::perHour($maxPerHour)
                ->by('branch-hourly:' . $request->route('branch'))
                ->response(function () {
                    return back()->withErrors(['branch' => 'Se alcanzó el límite de turnos por hora. Intente más tarde.']);
                });
        });

        // ── Kiosk page views ──
        RateLimiter::for('kiosk-view', function ($request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // ── Public display ──
        RateLimiter::for('display-public', function ($request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // ── API metrics ──
        RateLimiter::for('api-metrics', function ($request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // ── API public ticket issuance ──
        RateLimiter::for('api-public-issue', function ($request) {
            $settings = $this->getBranchSecuritySettings($request->route('branchId'));
            $perIpMin = $settings['max_tickets_per_ip_minute'] ?? 3;
            $maxPerHour = $settings['max_tickets_per_hour'] ?? 60;

            return [
                Limit::perMinute($perIpMin)->by($request->ip()),
                Limit::perHour($maxPerHour)->by('api-branch-hourly:' . $request->route('branchId')),
            ];
        });
    }

    /**
     * Load security settings for a branch's tenant.
     * Cached 60s to avoid DB hits on every rate-limited request.
     */
    private function getBranchSecuritySettings(mixed $branchId): array
    {
        if (!$branchId) {
            return [];
        }

        return cache()->remember(
            "security_settings:{$branchId}",
            60,
            function () use ($branchId) {
                $branch = Branch::withoutGlobalScopes()->with('tenant')->find($branchId);
                if (!$branch || !$branch->tenant) {
                    return [];
                }
                return $branch->tenant->getEffectiveSettings()['security'] ?? [];
            }
        );
    }
}
