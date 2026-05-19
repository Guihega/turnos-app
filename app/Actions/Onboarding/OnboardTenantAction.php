<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * OnboardTenantAction: creates a tenant + admin user + first branch in
 * a single DB transaction.
 *
 * Extracted from OnboardingController::store() (PR-O) to allow reuse from
 * CheckoutController, which orchestrates this action plus billing
 * (CreateCustomerAction, CreateSubscriptionAction). Behavior must remain
 * identical for OnboardingController to pass its existing test suite
 * unchanged.
 *
 * Inputs are pre-validated by the caller's FormRequest. This action
 * does NOT validate; it executes. Throws if any underlying model
 * constraint fails (handled by the outer transaction).
 *
 * Optional $socialData parameter carries OAuth context when the signup
 * originates from social registration; the action attaches a SocialAccount
 * row and may mark email as verified.
 */
final class OnboardTenantAction
{
    /**
     * Execute the onboarding.
     *
     * @param  array<string, mixed>  $data  Pre-validated payload (see OnboardingController::store).
     * @param  array<string, mixed>|null  $socialData  OAuth context if applicable.
     * @return array{tenant: Tenant, user: User, branch: Branch}
     */
    public function execute(array $data, ?array $socialData = null): array
    {
        $country = $data['branch_country']
            ?? $data['company_country']
            ?? 'MX';

        $timezone = $data['branch_timezone']
            ?? $this->timezoneForCountry($country);

        $isSocialRegistration = $socialData !== null;

        return DB::transaction(function () use ($data, $timezone, $socialData, $isSocialRegistration) {
            // 1. Create the tenant
            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'slug' => $data['slug'],
                'email' => $data['email'],
                'phone' => $data['company_phone'] ?? null,
                'timezone' => $timezone,
                'is_active' => true,
                'settings' => [],
            ]);

            // 2. Create the admin user attached to this tenant
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => ! empty($data['password'])
                            ? Hash::make($data['password'])
                            : '',
                'tenant_id' => $tenant->id,
                'role' => UserRole::TENANT_ADMIN,
            ]);

            // 3. Vincular cuenta social si viene de OAuth
            if ($isSocialRegistration && $socialData !== null) {
                $user->socialAccounts()->create([
                    'provider' => $socialData['provider'],
                    'provider_id' => $socialData['provider_id'],
                    'provider_email' => $socialData['email'],
                    'provider_avatar' => $socialData['avatar'],
                    'provider_token' => $socialData['token'],
                ]);

                if ($user->email === $socialData['email'] && ! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
            }

            // 4. Create the first branch
            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => $data['branch_name'],
                'code' => $data['branch_code'],
                'slug' => Str::slug($data['branch_name']),
                'address' => $data['branch_address'] ?? null,
                'city' => $data['branch_city'] ?? null,
                'state' => $data['branch_state'] ?? null,
                'country' => $data['branch_country'] ?? $data['company_country'] ?? 'MX',
                'timezone' => $timezone,
                'latitude' => $data['branch_latitude'] ?? null,
                'longitude' => $data['branch_longitude'] ?? null,
                'is_active' => true,
                'operating_hours' => $data['branch_schedule'] ?? $this->defaultSchedule(),
                'max_daily_tickets' => 200,
                'max_concurrent_waiting' => 30,
            ]);

            // 5. Attach user to the branch
            $user->branches()->attach($branch->id);

            return [
                'tenant' => $tenant,
                'user' => $user,
                'branch' => $branch,
            ];
        });
    }

    /**
     * Default business hours schedule (Mon-Fri 9-18, Sat 9-14, Sun closed).
     *
     * @return array<string, array<string, string|bool|null>>
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
