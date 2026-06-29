<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\Gateway;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerGatewayRef>
 */
class CustomerGatewayRefFactory extends Factory
{
    protected $model = CustomerGatewayRef::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'gateway' => Gateway::Stripe,
            'gateway_customer_id' => 'cus_'.Str::lower(Str::random(14)),
            'metadata' => null,
        ];
    }

    public function forStripe(): self
    {
        return $this->state(fn () => [
            'gateway' => Gateway::Stripe,
            'gateway_customer_id' => 'cus_'.Str::lower(Str::random(14)),
        ]);
    }

    public function forMercadoPago(): self
    {
        return $this->state(fn () => [
            'gateway' => Gateway::MercadoPago,
            'gateway_customer_id' => (string) fake()->numberBetween(100000000, 999999999),
        ]);
    }

    public function forManual(): self
    {
        return $this->state(fn () => [
            'gateway' => Gateway::Manual,
            'gateway_customer_id' => 'manual_'.fake()->uuid(),
        ]);
    }
}
