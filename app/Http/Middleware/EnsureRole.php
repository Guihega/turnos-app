<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to enforce role-based permissions.
 *
 * Usage in routes:
 *   ->middleware('role:tickets.call')        // requires specific permission
 *   ->middleware('role:admin')               // requires tenant_admin+ level
 *   ->middleware('role:operator')            // requires operator+ level
 */
class EnsureRole
{
    /**
     * Level shortcuts for common role groups.
     */
    private const LEVEL_SHORTCUTS = [
        'admin'    => 80, // tenant_admin+
        'manager'  => 60, // branch_manager+
        'operator' => 40, // operator+
        'staff'    => 30, // receptionist+
        'viewer'   => 10, // viewer+
    ];

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'No autenticado.');
        }

        // Check by level shortcut
        if (isset(self::LEVEL_SHORTCUTS[$permission])) {
            if ($user->role->level() < self::LEVEL_SHORTCUTS[$permission]) {
                abort(403, 'No tiene permisos suficientes para acceder a esta sección.');
            }
            return $next($request);
        }

        // Check by specific permission string
        if (!$user->role->hasPermission($permission)) {
            abort(403, "No tiene el permiso: {$permission}");
        }

        return $next($request);
    }
}
