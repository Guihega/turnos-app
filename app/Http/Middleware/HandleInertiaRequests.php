<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                /**
                 * Lazy-loaded user with social accounts.
                 *
                 * Before: every request eager-loaded socialAccounts (+1 query).
                 * After: socialAccounts is resolved only when a component actually
                 * reads `auth.user.social_accounts` (Inertia partial reloads or
                 * components that consume the prop). Most pages (Dashboard, Kiosk,
                 * TenantSettings, etc.) don't touch it, so the query is skipped.
                 *
                 * The closure returns the same shape the frontend already expects,
                 * so no React changes are required.
                 */
                'user' => function () use ($request) {
                    $user = $request->user();
                    if (! $user) {
                        return null;
                    }

                    // Only load socialAccounts on routes that actually need them.
                    // Everything else gets the user without the extra query.
                    if ($this->routeNeedsSocialAccounts($request)) {
                        $user->loadMissing('socialAccounts');
                    }

                    return $user;
                },
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'twoFactor' => fn () => $request->session()->get('twoFactor'),
            ],
            'tenantBranding' => function () use ($request) {
                $tenant = $request->user()?->tenant;

                return $tenant?->getBrandingForFrontend();
            },
        ];
    }

    /**
     * Whitelist of routes that display or manage linked social accounts.
     * Everything else skips the socialAccounts eager-load.
     */
    private function routeNeedsSocialAccounts(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return false;
        }

        // Routes where the UI shows or manages linked OAuth accounts
        $needsIt = [
            'profile.edit',       // Profile page — shows SocialAccountsSection
            'profile.update',     // Profile update — may touch linked accounts
            'profile.destroy',    // Account deletion — needs social accounts for cleanup
            'login',              // Login page — shows "Continue with Google" state
            'onboarding',         // Onboarding form (GET)
            'onboarding.store',   // Onboarding submission (POST)
        ];

        return in_array($routeName, $needsIt, true);
    }
}
