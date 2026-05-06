<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'plan_'.Str::lower(Str::random(8));

        return [
            'code' => $code,
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_public' => true,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
            'metadata' => null,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function private(): self
    {
        return $this->state(fn () => ['is_public' => false]);
    }
}
