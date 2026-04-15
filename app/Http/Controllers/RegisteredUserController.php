<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Standard registration is DISABLED.
 *
 * All new user registration goes through /onboarding which creates
 * the tenant, admin user, and first branch in a single transaction.
 *
 * Users within a tenant are created by the tenant admin via Admin > Users.
 */
class RegisteredUserController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect()->route('onboarding');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('onboarding');
    }
}
