<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::OPERATOR,
            'is_active' => true,
        ];
    }

    /**
     * Auto-configure 2FA for admin roles after creation.
     * EnsureTwoFactorForAdmins middleware requires admins to have 2FA,
     * so the factory provisions it automatically.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            if (in_array($user->role, [UserRole::TENANT_ADMIN, UserRole::SUPER_ADMIN])
                && $user->two_factor_confirmed_at === null) {
                $user->updateQuietly([
                    'two_factor_secret' => Crypt::encryptString('FACTORYSECRET' . Str::random(19)),
                    'two_factor_confirmed_at' => now(),
                    'two_factor_recovery_codes' => Crypt::encryptString(json_encode([
                        'recovery-01', 'recovery-02', 'recovery-03', 'recovery-04',
                        'recovery-05', 'recovery-06', 'recovery-07', 'recovery-08',
                    ])),
                ]);
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::TENANT_ADMIN]);
    }

    public function operator(): static
    {
        return $this->state(fn () => ['role' => UserRole::OPERATOR]);
    }

    public function viewer(): static
    {
        return $this->state(fn () => ['role' => UserRole::VIEWER]);
    }
}
