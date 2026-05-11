<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Http;

use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\GatewayCustomer;
use App\Enums\Billing\Gateway;
use App\Models\Billing\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for POST /api/v1/billing/customers.
 *
 * Full HTTP → Controller → FormRequest → Action → DB chain.
 * Only the BillingGatewayWriter is mocked (no Stripe network call).
 */
final class CreateCustomerEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindGatewayMock(string $returnedId = 'cus_TEST'): void
    {
        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createCustomer')
            ->andReturn(new GatewayCustomer(
                gatewayId: $returnedId,
                email: 'owner@acme.test',
                name: 'Acme Co.',
                defaultPaymentMethodId: null,
                currency: 'mxn',
                deleted: false,
                metadata: [],
            ));
        $this->app->instance(BillingGatewayWriter::class, $writer);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'billing_email' => 'owner@acme.test',
            'billing_name' => 'Acme Co.',
            'country' => 'MX',
            'default_currency' => 'MXN',
            'tax_id' => null,
            'billing_address' => null,
        ];
    }

    #[Test]
    public function creates_customer_and_returns_201_with_resource_shape(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->bindGatewayMock('cus_HAPPY_HTTP');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/billing/customers', $this->validPayload());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'country', 'default_currency', 'billing_email', 'created_at'],
        ]);
        $response->assertJsonPath('data.billing_email', 'owner@acme.test');
        $response->assertJsonPath('data.country', 'MX');

        $this->assertDatabaseHas('billing_customers', [
            'tenant_id' => $tenant->id,
            'billing_email' => 'owner@acme.test',
        ]);
        $this->assertDatabaseHas('billing_customer_gateway_refs', [
            'gateway' => Gateway::Stripe->value,
            'gateway_customer_id' => 'cus_HAPPY_HTTP',
        ]);
    }

    #[Test]
    public function returns_422_when_billing_email_missing(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->bindGatewayMock();
        Sanctum::actingAs($user);

        $payload = $this->validPayload();
        unset($payload['billing_email']);

        $response = $this->postJson('/api/v1/billing/customers', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['billing_email']);
    }

    #[Test]
    public function returns_422_when_email_format_invalid(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->bindGatewayMock();
        Sanctum::actingAs($user);

        $payload = $this->validPayload();
        $payload['billing_email'] = 'not-an-email';

        $response = $this->postJson('/api/v1/billing/customers', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['billing_email']);
    }

    #[Test]
    public function returns_401_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/billing/customers', $this->validPayload());

        $response->assertStatus(401);
    }

    #[Test]
    public function tenant_isolation_user_a_creates_customer_for_their_own_tenant_only(): void
    {
        /** @var Tenant $tenantA */
        $tenantA = Tenant::factory()->create();
        /** @var Tenant $tenantB */
        $tenantB = Tenant::factory()->create();
        /** @var User $userA */
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        $this->bindGatewayMock('cus_TENANT_A');
        Sanctum::actingAs($userA);

        $this->postJson('/api/v1/billing/customers', $this->validPayload())
            ->assertStatus(201);

        $this->assertSame(1, Customer::where('tenant_id', $tenantA->id)->count());
        $this->assertSame(0, Customer::where('tenant_id', $tenantB->id)->count());
    }
}
