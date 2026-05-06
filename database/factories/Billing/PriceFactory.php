<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    protected $model = Price::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'currency' => fake()->randomElement(['USD', 'MXN', 'COP', 'ARS', 'CLP', 'PEN']),
            'country' => null,
            'interval' => BillingInterval::Month,
            'interval_count' => 1,
            'amount_cents' => fake()->numberBetween(100, 1000000),
            'tax_behavior' => 'exclusive',
            'gateway_refs' => null,
            'is_active' => true,
        ];
    }

    public function monthly(): self
    {
        return $this->state(fn () => [
            'interval' => BillingInterval::Month,
            'interval_count' => 1,
        ]);
    }

    public function yearly(): self
    {
        return $this->state(fn () => [
            'interval' => BillingInterval::Year,
            'interval_count' => 1,
        ]);
    }

    public function inUsd(): self
    {
        return $this->state(fn () => ['currency' => 'USD']);
    }

    public function inMxn(): self
    {
        return $this->state(fn () => ['currency' => 'MXN']);
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
