<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
     * Process the onboarding: create tenant, admin user, and first branch.
     *
     * All three are created in a single DB transaction so we never end up
     * with orphaned records if something fails mid-way.
     */
    public function store(Request $request)
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

        $country = $validated['branch_country']
            ?? $validated['company_country']
            ?? 'MX';

        $timezone = $validated['branch_timezone']
            ?? $this->timezoneForCountry($country);

        $result = DB::transaction(function () use ($validated, $country, $timezone, $socialData, $isSocialRegistration) {
            // 1. Create the tenant
            $tenant = Tenant::create([
                'name' => $validated['company_name'],
                'slug' => $validated['slug'],
                'email' => $validated['email'],
                'phone' => $validated['company_phone'] ?? null,
                'timezone' => $timezone,
                'is_active' => true,
                'settings' => [],
            ]);

            // 2. Create the admin user attached to this tenant
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => ! empty($validated['password'])
                            ? Hash::make($validated['password'])
                            : '',
                'tenant_id' => $tenant->id,
                'role' => UserRole::TENANT_ADMIN,
            ]);

            // 3. Vincular cuenta social si viene de OAuth
            if ($isSocialRegistration && $socialData) {
                $user->socialAccounts()->create([
                    'provider' => $socialData['provider'],
                    'provider_id' => $socialData['provider_id'],
                    'provider_email' => $socialData['email'],
                    'provider_avatar' => $socialData['avatar'],
                    'provider_token' => $socialData['token'],
                ]);

                // Si el email coincide con el del provider, marcar como verificado
                if ($user->email === $socialData['email'] && ! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
            }

            // 4. Create the first branch
            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['branch_name'],
                'code' => $validated['branch_code'],
                'slug' => Str::slug($validated['branch_name']),
                'address' => $validated['branch_address'] ?? null,
                'city' => $validated['branch_city'] ?? null,
                'state' => $validated['branch_state'] ?? null,
                'country' => $validated['branch_country'] ?? $validated['company_country'] ?? 'MX',
                'timezone' => $timezone,
                'latitude' => $validated['branch_latitude'] ?? null,
                'longitude' => $validated['branch_longitude'] ?? null,
                'is_active' => true,
                'operating_hours' => $validated['branch_schedule'] ?? $this->defaultSchedule(),
                'max_daily_tickets' => 200,
                'max_concurrent_waiting' => 30,
            ]);

            // 5. Attach user to the branch
            $user->branches()->attach($branch->id);

            return compact('tenant', 'user', 'branch');
        });

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

    /**
     * Default business hours schedule (Mon-Fri 9-18, Sat 9-14, Sun closed).
     */
    private function defaultSchedule(): array
    {
        $weekday = ['open' => '09:00', 'close' => '18:00', 'is_open' => true];
        $saturday = ['open' => '09:00', 'close' => '14:00', 'is_open' => true];
        $sunday = ['open' => null, 'close' => null, 'is_open' => false];

        return [
            'monday' => $weekday,
            'tuesday' => $weekday,
            'wednesday' => $weekday,
            'thursday' => $weekday,
            'friday' => $weekday,
            'saturday' => $saturday,
            'sunday' => $sunday,
        ];
    }

    /**
     * Map country code to a sensible default timezone.
     */
    private function timezoneForCountry(string $country): string
    {
        $map = [
            'MX' => 'America/Mexico_City',
            'CO' => 'America/Bogota',
            'PE' => 'America/Lima',
            'CL' => 'America/Santiago',
            'AR' => 'America/Argentina/Buenos_Aires',
            'EC' => 'America/Guayaquil',
            'BO' => 'America/La_Paz',
            'PY' => 'America/Asuncion',
            'UY' => 'America/Montevideo',
            'VE' => 'America/Caracas',
            'BR' => 'America/Sao_Paulo',
            'GT' => 'America/Guatemala',
            'CR' => 'America/Costa_Rica',
            'PA' => 'America/Panama',
            'SV' => 'America/El_Salvador',
            'HN' => 'America/Tegucigalpa',
            'NI' => 'America/Managua',
            'DO' => 'America/Santo_Domingo',
            'US' => 'America/New_York',
            'ES' => 'Europe/Madrid',
        ];

        return $map[strtoupper($country)] ?? 'America/Mexico_City';
    }
}
