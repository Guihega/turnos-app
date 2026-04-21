<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce two-factor authentication for admin users.
 *
 * If a tenant_admin or super_admin has not activated 2FA,
 * they are redirected to the mandatory setup page and cannot
 * access any other authenticated route until 2FA is confirmed.
 *
 * Operators and staff are not affected — 2FA remains optional for them.
 */
class EnsureTwoFactorForAdmins
{
    /**
     * Routes that should be accessible even without 2FA setup.
     * These are the routes needed to complete the setup itself.
     */
    private const EXEMPT_ROUTES = [
        'two-factor.setup',
        'two-factor.enable',
        'two-factor.confirm',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only enforce for authenticated admin users
        if (! $user) {
            return $next($request);
        }

        // Only enforce for admin roles
        if (! $this->isAdmin($user)) {
            return $next($request);
        }

        // If 2FA is already confirmed, proceed normally
        if ($user->two_factor_confirmed_at) {
            return $next($request);
        }

        // Allow access to exempt routes (setup flow + logout)
        $currentRoute = $request->route()?->getName();
        if ($currentRoute && in_array($currentRoute, self::EXEMPT_ROUTES, true)) {
            return $next($request);
        }

        // Redirect admin to mandatory 2FA setup
        return redirect()->route('two-factor.setup');
    }

    private function isAdmin($user): bool
    {
        return $user->role === UserRole::TENANT_ADMIN
            || $user->role === UserRole::SUPER_ADMIN;
    }
}
