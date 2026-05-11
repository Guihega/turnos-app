<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Http;

use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\GatewaySubscription;
use App\Enums\Billing\Gateway;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use App\Models\Tenant;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for POST /api/v1/billing/subscriptions.
 *
 * Full HTTP → Controller → FormRequest → Action → DB chain.
 * Only the BillingGatewayWriter is mocked.
 */
final class CreateSubscriptionEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindGatewayMock(string $returnedId = 'sub_TEST'): void
    {
        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSubscription')
            ->andReturn(new GatewaySubscription(
                gatewayId: $returnedId,
                gatewayCustomerId: 'cus_TEST',
                status: 'trialing',
                rawStatus: 'trialing',
                items: [],
                currentPeriodStart: new DateTimeImmutable('2026-05-11'),
                currentPeriodEnd: new DateTimeImmutable('2026-06-11'),
                trialEnd: new DateTimeImmutable('2026-05-25'),
                cancelAt: null,
                canceledAt: null,
                cancelAtPeriodEnd: false,
                metadata: [],
            ));
        $this->app->instance(BillingGatewayWriter::class, $writer);
    }

    /**
     * @return array{tenant: Tenant, user: User, customer: Customer, plan: Plan, price: Price}
     */
    private function buildGraph(?string $stripePriceId = 'price_PRO_MXN'): array
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        /** @var Customer $customer */
        $customer = Customer::factory()
            ->inMexico()
            ->create(['tenant_id' => $tenant->id]);

        CustomerGatewayRef::create([
            'customer_id' => $customer->id,
            'gateway' => Gateway::Stripe,
            'gateway_customer_id' => 'cus_TEST',
            'metadata' => null,
        ]);

        /** @var Plan $plan */
        $plan = Plan::factory()->create();
        /** @var Price $price */
        $price = Price::factory()
            ->monthly()
            ->inMxn()
            ->create([
                'plan_id' => $plan->id,
                'country' => null,
                'gateway_refs' => $stripePriceId !== null
                    ? ['stripe' => $stripePriceId]
                    : null,
            ]);

        return compact('tenant', 'user', 'customer', 'plan', 'price');
    }

    #[Test]
    public function creates_subscription_and_returns_201_with_trialing_status(): void
    {
        $g = $this->buildGraph();

        $this->bindGatewayMock('sub_HAPPY_HTTP');
        Sanctum::actingAs($g['user']);

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $g['plan']->id,
            'interval' => 'month',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'status', 'plan_id', 'price_id', 'trial_ends_at'],
        ]);
        $response->assertJsonPath('data.status', SubscriptionStatus::Trialing->value);
        $response->assertJsonPath('data.plan_id', $g['plan']->id);
        $response->assertJsonPath('data.price_id', $g['price']->id);

        $this->assertDatabaseHas('billing_subscriptions', [
            'customer_id' => $g['customer']->id,
            'plan_id' => $g['plan']->id,
            'status' => SubscriptionStatus::Trialing->value,
            'stripe_subscription_id' => 'sub_HAPPY_HTTP',
        ]);
    }

    #[Test]
    public function returns_422_when_plan_id_does_not_exist(): void
    {
        $g = $this->buildGraph();

        $this->bindGatewayMock();
        Sanctum::actingAs($g['user']);

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => '01HXXXXXXXXXXXXXXXXXXXXXXX',
            'interval' => 'month',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['plan_id']);
    }

    #[Test]
    public function returns_422_when_interval_is_invalid(): void
    {
        $g = $this->buildGraph();

        $this->bindGatewayMock();
        Sanctum::actingAs($g['user']);

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $g['plan']->id,
            'interval' => 'forever',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['interval']);
    }

    #[Test]
    public function returns_401_without_authentication(): void
    {
        $g = $this->buildGraph();

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $g['plan']->id,
            'interval' => 'month',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function returns_403_when_tenant_has_no_customer_yet(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        /** @var Plan $plan */
        $plan = Plan::factory()->create();

        $this->bindGatewayMock();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $plan->id,
            'interval' => 'month',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function returns_422_when_price_resolver_finds_no_match(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        /** @var Customer $customer */
        $customer = Customer::factory()
            ->inMexico()
            ->create(['tenant_id' => $tenant->id]);
        CustomerGatewayRef::create([
            'customer_id' => $customer->id,
            'gateway' => Gateway::Stripe,
            'gateway_customer_id' => 'cus_X',
            'metadata' => null,
        ]);

        /** @var Plan $plan */
        $plan = Plan::factory()->create();
        Price::factory()
            ->monthly()
            ->inUsd()
            ->create([
                'plan_id' => $plan->id,
                'country' => null,
                'gateway_refs' => ['stripe' => 'price_USD'],
            ]);

        $this->bindGatewayMock();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $plan->id,
            'interval' => 'month',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'price_not_found');
    }

    #[Test]
    public function returns_422_when_price_has_no_stripe_mapping(): void
    {
        $g = $this->buildGraph(stripePriceId: null);

        $this->bindGatewayMock();
        Sanctum::actingAs($g['user']);

        $response = $this->postJson('/api/v1/billing/subscriptions', [
            'plan_id' => $g['plan']->id,
            'interval' => 'month',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'price_gateway_mapping_missing');
    }
}
