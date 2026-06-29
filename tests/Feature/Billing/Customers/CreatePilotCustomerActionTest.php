<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Customers;

use App\Actions\Billing\CreatePilotCustomerAction;
use App\Events\Billing\CustomerCreated;
use App\Models\Billing\Customer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Behavior tests for CreatePilotCustomerAction (PR-S3).
 *
 * The action creates a billing Customer for a tenant locally, without a
 * gateway: billing identity derived from the tenant, no CustomerGatewayRef,
 * country/currency at the schema defaults. Idempotent on the 1:1 tenant_id
 * unique constraint. Dispatches CustomerCreated.
 */
final class CreatePilotCustomerActionTest extends TestCase
{
    use RefreshDatabase;

    private function action(): CreatePilotCustomerAction
    {
        return app(CreatePilotCustomerAction::class);
    }

    #[Test]
    public function it_creates_a_local_customer_for_the_tenant(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $customer = $this->action()->execute($tenant);

        $this->assertSame($tenant->id, $customer->tenant_id);
        $this->assertSame($tenant->email, $customer->billing_email);
        $this->assertSame('MX', $customer->country);
        $this->assertSame('MXN', $customer->default_currency);
    }

    #[Test]
    public function it_does_not_create_a_gateway_ref(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $customer = $this->action()->execute($tenant);

        $this->assertDatabaseCount('billing_customer_gateway_refs', 0);
        $this->assertCount(0, $customer->gatewayRefs);
    }

    #[Test]
    public function it_falls_back_to_the_tenant_name_when_legal_name_is_absent(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create(['legal_name' => null]);

        $customer = $this->action()->execute($tenant);

        $this->assertSame($tenant->name, $customer->billing_name);
    }

    #[Test]
    public function it_prefers_the_legal_name_when_present(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create(['legal_name' => 'Acme Sociedad Anonima']);

        $customer = $this->action()->execute($tenant);

        $this->assertSame('Acme Sociedad Anonima', $customer->billing_name);
    }

    #[Test]
    public function running_twice_does_not_create_a_second_customer(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $first = $this->action()->execute($tenant);
        $second = $this->action()->execute($tenant);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            Customer::query()->where('tenant_id', $tenant->id)->count(),
        );
    }

    #[Test]
    public function it_dispatches_the_customer_created_event(): void
    {
        Event::fake();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $this->action()->execute($tenant);

        Event::assertDispatched(CustomerCreated::class);
    }
}
