<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Customer;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Default state: a fresh pilot subscription. No price (free), no
     * Stripe link, no trial end set. Tests that need a paid sub should
     * use ->active() / ->trialing() / etc.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'plan_id' => Plan::factory(),
            'price_id' => null,
            'status' => SubscriptionStatus::Pilot,
            'stripe_subscription_id' => null,
            'trial_ends_at' => null,
            'current_period_start' => null,
            'current_period_end' => null,
            'cancel_at' => null,
            'canceled_at' => null,
            'paused_at' => null,
            'metadata' => null,
        ];
    }

    public function pilot(): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Pilot,
            'trial_ends_at' => now()->addDays(90),
        ]);
    }

    public function trialing(): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function active(): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
        ]);
    }

    public function pastDue(): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::PastDue,
            'current_period_start' => now()->subDays(35),
            'current_period_end' => now()->subDays(5),
        ]);
    }

    public function paused(): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Paused,
            'paused_at' => now(),
        ]);
    }

    public function suspended(): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Suspended,
        ]);
    }

    public function canceled(): self
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
        ]);
    }

    public function withStripeId(?string $id = null): self
    {
        return $this->state(fn () => [
            'stripe_subscription_id' => $id ?? 'sub_'.fake()->bothify('??????????????'),
        ]);
    }
}
