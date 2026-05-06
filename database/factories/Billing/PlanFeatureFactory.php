<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanFeature>
 */
class PlanFeatureFactory extends Factory
{
    protected $model = PlanFeature::class;

    /**
     * Default produces a quota with a positive numeric value. Use the
     * forBoolean(), forQuota(), forMetered() and forString() states to
     * align the row with the parent Feature's type.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'feature_id' => Feature::factory()->quota(),
            'value_numeric' => fake()->numberBetween(1, 1000),
            'value_boolean' => null,
            'value_string' => null,
            'reset_period' => null,
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

    public function forMetered(int $value): self
    {
        return $this->state(fn () => [
            'feature_id' => Feature::factory()->metered(),
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
}

// Backward note: when the test passes both plan_id and feature_id explicitly,
// the type-specific states should be applied AFTER ::for*(), e.g.:
//   PlanFeature::factory()->forQuota(100)->create([
//       'plan_id'    => $plan->id,
//       'feature_id' => $feature->id,
//   ]);
