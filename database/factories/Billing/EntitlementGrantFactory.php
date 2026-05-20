<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\EntitlementGrant;
use App\Models\Billing\Feature;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default produces an active perpetual grant (no expiry, not revoked)
 * with a positive numeric value. Use the expiring(), expired(),
 * revoked() and perpetual() states to vary the lifecycle.
 *
 * @extends Factory<EntitlementGrant>
 */
class EntitlementGrantFactory extends Factory
{
    protected $model = EntitlementGrant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'feature_id' => Feature::factory()->quota(),
            'value_numeric' => fake()->numberBetween(1, 1000),
            'value_boolean' => null,
            'value_string' => null,
            'granted_by' => User::factory(),
            'reason' => fake()->sentence(),
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function forBoolean(bool $value = true): self
    {
        return $this->state(fn () => [
            'feature_id' => Feature::factory()->boolean(),
            'value_numeric' => null,
            'value_boolean' => $value,
            'value_string' => null,
        ]);
    }

    public function forQuota(int $value): self
    {
        return $this->state(fn () => [
            'feature_id' => Feature::factory()->quota(),
            'value_numeric' => $value,
            'value_boolean' => null,
            'value_string' => null,
        ]);
    }

    public function expiring(int $daysFromNow = 30): self
    {
        return $this->state(fn () => [
            'expires_at' => now()->addDays($daysFromNow),
            'revoked_at' => null,
        ]);
    }

    public function expired(int $daysAgo = 1): self
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDays($daysAgo),
            'revoked_at' => null,
        ]);
    }

    public function revoked(): self
    {
        return $this->state(fn () => [
            'expires_at' => null,
            'revoked_at' => now(),
        ]);
    }

    public function perpetual(): self
    {
        return $this->state(fn () => [
            'expires_at' => null,
            'revoked_at' => null,
        ]);
    }
}
