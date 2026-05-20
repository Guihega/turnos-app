<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\Entitlement;
use App\Models\Billing\Feature;
use App\Models\Billing\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default produces a quota entitlement with a positive numeric value
 * sourced from the plan. Use forBoolean(), forQuota(), forString(),
 * unlimited(), monthlyReset(), and grant() states to compose specifics.
 *
 * @extends Factory<Entitlement>
 */
class EntitlementFactory extends Factory
{
    protected $model = Entitlement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'feature_id' => Feature::factory()->quota(),
            'value_numeric' => fake()->numberBetween(1, 1000),
            'value_boolean' => null,
            'value_string' => null,
            'reset_period' => null,
            'source' => Entitlement::SOURCE_PLAN,
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

    public function forString(string $value): self
    {
        return $this->state(fn () => [
            'feature_id' => Feature::factory()->stringValue(),
            'value_numeric' => null,
            'value_boolean' => null,
            'value_string' => $value,
        ]);
    }

    public function unlimited(): self
    {
        return $this->state(fn () => [
            'feature_id' => Feature::factory()->quota(),
            'value_numeric' => -1,
            'value_boolean' => null,
            'value_string' => null,
        ]);
    }

    public function monthlyReset(): self
    {
        return $this->state(fn () => ['reset_period' => 'monthly']);
    }

    public function grant(): self
    {
        return $this->state(fn () => ['source' => Entitlement::SOURCE_GRANT]);
    }
}
