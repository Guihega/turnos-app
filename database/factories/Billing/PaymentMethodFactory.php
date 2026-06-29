<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\PaymentMethodType;
use App\Models\Billing\Customer;
use App\Models\Billing\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => PaymentMethodType::Card,
            'stripe_payment_method_id' => 'pm_'.Str::random(24),
            'is_default' => false,
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'cardholder_name' => null,
            'metadata' => null,
        ];
    }

    public function default(): self
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function mastercard(): self
    {
        return $this->state(fn () => [
            'brand' => 'mastercard',
            'last4' => '4444',
        ]);
    }
}
