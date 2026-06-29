<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\Gateway;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_be_created_for_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertInstanceOf(Tenant::class, $tenant);

        $customer = Customer::factory()
            ->inMexico()
            ->for($tenant)
            ->create();
        $this->assertInstanceOf(Customer::class, $customer);

        $this->assertSame($tenant->id, $customer->tenant_id);
        $this->assertSame('MX', $customer->country);
        $this->assertSame('MXN', $customer->default_currency);
        $this->assertNotNull($customer->billing_email);
        $this->assertTrue($customer->tenant->is($tenant));
    }

    public function test_tenant_id_is_unique(): void
    {
        $tenant = Tenant::factory()->create();

        Customer::factory()->for($tenant)->create();

        $this->expectException(QueryException::class);
        Customer::factory()->for($tenant)->create();
    }

    public function test_it_can_have_multiple_gateway_refs(): void
    {
        $customer = Customer::factory()->create();
        $this->assertInstanceOf(Customer::class, $customer);

        CustomerGatewayRef::factory()
            ->for($customer)
            ->forStripe()
            ->create();

        CustomerGatewayRef::factory()
            ->for($customer)
            ->forMercadoPago()
            ->create();

        $customer->refresh();

        $this->assertCount(2, $customer->gatewayRefs);

        $gateways = $customer->gatewayRefs->pluck('gateway')->all();

        $this->assertContains(Gateway::Stripe, $gateways);
        $this->assertContains(Gateway::MercadoPago, $gateways);
    }

    public function test_it_supports_soft_deletes(): void
    {
        $customer = Customer::factory()->create();
        $this->assertInstanceOf(Customer::class, $customer);

        $id = $customer->id;

        $customer->delete();

        $this->assertNull(Customer::query()->find($id));
        $this->assertNotNull(Customer::withTrashed()->find($id));
    }
}
