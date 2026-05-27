<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\OnboardPilotAction;
use App\Actions\Onboarding\OnboardTenantAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\LaravelCipherSweet\Rules\EncryptedUniqueRule;

class OnboardingController extends Controller
{
    /**
     * Show the onboarding wizard.
     */
    public function create()
    {
        // Obtener datos de registro social si vienen de OAuth
        $socialData = session('social_registration');

        return Inertia::render('Onboarding/Register', [
            'socialData' => $socialData ? [
                'provider' => $socialData['provider'],
                'name' => $socialData['name'],
                'email' => $socialData['email'],
                'avatar' => $socialData['avatar'],
            ] : null,
        ]);
    }

    /**
     * Process the onboarding: validate input, delegate to
     * OnboardTenantAction for tenant/user/branch creation, then handle
     * HTTP-layer post-actions (registered event, auto-login, redirect).
     *
     * Action extracted in PR-O to enable reuse from CheckoutController.
     * Behavior here is unchanged; OnboardingTest must still pass.
     */
    public function store(Request $request, OnboardTenantAction $onboardTenant, OnboardPilotAction $onboardPilot)
    {
        // Si viene de registro social, password es opcional
        $socialData = session('social_registration');
        $isSocialRegistration = $socialData !== null;

        $validated = $request->validate([
            // Step 1 — User account
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new EncryptedUniqueRule(User::class, 'email_index')],
            'password' => [$isSocialRegistration ? 'nullable' : 'required', 'confirmed', Password::defaults()],

            // Step 2 — Tenant / Company
            'company_name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/',
                'unique:tenants,slug',
            ],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_country' => ['nullable', 'string', 'max:2'],

            // Step 3 — First branch
            'branch_name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9\-]+$/'],
            'branch_address' => ['nullable', 'string', 'max:500'],
            'branch_city' => ['nullable', 'string', 'max:100'],
            'branch_state' => ['nullable', 'string', 'max:100'],
            'branch_country' => ['nullable', 'string', 'max:2'],
            'branch_timezone' => ['nullable', 'string', 'max:50'],
            'branch_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'branch_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'branch_schedule' => ['nullable', 'array'],
        ]);

        $result = $onboardTenant->execute($validated, $socialData);

        // Provision pilot billing for the freshly created tenant. Best-effort:
        // a billing failure must not abort the user's onboarding. The
        // billing:backfill-existing-tenants command is the safety net for any
        // tenant left without billing here, and OnboardPilotAction is idempotent.
        try {
            $onboardPilot->execute($result['tenant']);
        } catch (\Throwable $e) {
            Log::error('[Onboarding] Pilot billing provisioning failed', [
                'tenant_id' => $result['tenant']->id,
                'exception' => $e,
            ]);
        }

        // Limpiar datos de social registration de la sesión
        session()->forget('social_registration');

        // Fire Registered event so Laravel sends verification email
        event(new Registered($result['user']));

        // Log the user in
        auth()->login($result['user']);

        // Redirect to dashboard — email verification is handled by middleware
        return redirect()->route('dashboard');
    }

    /**
     * Check if a slug is available (for real-time validation in the wizard).
     */
    public function checkSlug(Request $request)
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/'],
        ]);

        $available = ! Tenant::where('slug', $request->slug)->exists();

        return response()->json(['available' => $available]);
    }
}
