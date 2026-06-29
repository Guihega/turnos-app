<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'country' => 'MX',
            'default_currency' => 'MXN',
            'billing_email' => fake()->companyEmail(),
            'billing_name' => fake()->company(),
            'tax_id' => null,
            'billing_address' => null,
            'metadata' => null,
        ];
    }

    public function inMexico(): self
    {
        return $this->state(fn () => [
            'country' => 'MX',
            'default_currency' => 'MXN',
        ]);
    }

    public function inUnitedStates(): self
    {
        return $this->state(fn () => [
            'country' => 'US',
            'default_currency' => 'USD',
        ]);
    }

    public function inColombia(): self
    {
        return $this->state(fn () => [
            'country' => 'CO',
            'default_currency' => 'COP',
        ]);
    }

    public function withTaxId(string $taxId): self
    {
        return $this->state(fn () => ['tax_id' => $taxId]);
    }

    public function withBillingAddress(): self
    {
        return $this->state(fn () => [
            'billing_address' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->regexify('[A-Z]{2}'),
                'zip' => fake()->postcode(),
                'country' => fake()->countryCode(),
            ],
        ]);
    }
}
