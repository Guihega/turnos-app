<?php

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

class OnboardingController extends Controller
{
    /**
     * Show the onboarding wizard.
     */
    public function create()
    {
        return Inertia::render('Onboarding/Register');
    }

    /**
     * Process the onboarding: create tenant, admin user, and first branch.
     *
     * All three are created in a single DB transaction so we never end up
     * with orphaned records if something fails mid-way.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Step 1 — User account
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],

            // Step 2 — Tenant / Company
            'company_name' => ['required', 'string', 'max:255'],
            'slug'         => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/',
                'unique:tenants,slug',
            ],

            // Step 3 — First branch
            'branch_name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9\-]+$/'],
            'branch_schedule' => ['nullable', 'array'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            // 1. Create the tenant
            $tenant = Tenant::create([
                'name'      => $validated['company_name'],
                'slug'      => $validated['slug'],
                'email'     => $validated['email'],
                'is_active' => true,
                'settings'  => [], // HasTenantSettings trait fills defaults
            ]);

            // 2. Create the admin user attached to this tenant
            $user = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'tenant_id' => $tenant->id,
                'role'      => UserRole::TENANT_ADMIN,
            ]);

            // 3. Create the first branch
            $branch = Branch::create([
                'tenant_id'              => $tenant->id,
                'name'                   => $validated['branch_name'],
                'code'                   => $validated['branch_code'],
                'slug'                   => Str::slug($validated['branch_name']),
                'is_active'              => true,
                'operating_hours'        => $validated['branch_schedule'] ?? $this->defaultSchedule(),
                'max_daily_tickets'      => 200,
                'max_concurrent_waiting' => 30,
            ]);

            // 4. Attach user to the branch
            $user->branches()->attach($branch->id);

            return compact('tenant', 'user', 'branch');
        });

        // Fire Registered event so Laravel sends verification email
        event(new Registered($result['user']));

        // Log the user in
        auth()->login($result['user']);

        // Redirect to email verification notice
        return redirect()->route('verification.notice');
    }

    /**
     * Check if a slug is available (for real-time validation in the wizard).
     */
    public function checkSlug(Request $request)
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/'],
        ]);

        $available = !Tenant::where('slug', $request->slug)->exists();

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
            'monday'    => $weekday,
            'tuesday'   => $weekday,
            'wednesday' => $weekday,
            'thursday'  => $weekday,
            'friday'    => $weekday,
            'saturday'  => $saturday,
            'sunday'    => $sunday,
        ];
    }
}
