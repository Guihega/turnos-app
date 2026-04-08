<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares tenant branding/settings with all Inertia responses.
 *
 * Register in HandleInertiaRequests::share() or as middleware.
 *
 * OPTION A — Add to HandleInertiaRequests.php share() method:
 *
 *   'tenantBranding' => fn () => $this->tenantBranding($request),
 *
 *   private function tenantBranding(Request $request): ?array
 *   {
 *       $tenant = $request->user()?->tenant;
 *       return $tenant?->getBrandingForFrontend();
 *   }
 *
 * OPTION B — Use this middleware on specific route groups.
 */
class ShareTenantBranding
{
    public function handle(Request $request, Closure $next): Response
    {
        // For authenticated users, share their tenant's branding
        if ($user = $request->user()) {
            $tenant = $user->tenant;
            if ($tenant) {
                inertia()->share('tenantBranding', $tenant->getBrandingForFrontend());
            }
        }

        // For public routes (Screen, Kiosk), the controller should pass branding explicitly
        // via Inertia::render props since there's no authenticated user.

        return $next($request);
    }
}
