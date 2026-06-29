<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Actions;

use App\Actions\Billing\CreateSubscriptionAction;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\CreateSubscriptionInput;
use App\Billing\DTOs\GatewaySubscription;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\Gateway;
use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\SubscriptionCreated;
use App\Exceptions\Billing\CustomerNotRegisteredInGatewayException;
use App\Exceptions\Billing\PriceMissingGatewayMappingException;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Billing\IdempotencyKey;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionStateTransition;
use App\Models\Tenant;
use App\Services\Billing\PriceResolver;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for CreateSubscriptionAction.
 *
 * BillingGatewayWriter is mocked. PriceResolver is the real service
 * (queries the test DB). Factories build the catalog and customer
 * graph (Tenant → Customer → CustomerGatewayRef + Plan → Price).
 *
 * Covers ADR-016 §5 saga semantics for subscription creation:
 *   - happy path: gateway + Subscription + initial state row + snapshot
 *   - trial period from config (14 days default)
 *   - PriceResolver lookup honored
 *   - guard: customer with no gateway ref
 *   - guard: price with no Stripe mapping
 *   - idempotency forwarding + retry short-circuit
 *   - initial state row uses reason='created'
 *   - SubscriptionCreated dispatched after commit
 */
final class CreateSubscriptionActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Builds the full graph needed by CreateSubscriptionAction:
     * Tenant → Customer (MX/MXN) → Stripe gateway ref,
     * Plan → Price (MXN/monthly, gateway_refs.stripe set).
     *
     * @return array{tenant: Tenant, customer: Customer, plan: Plan, price: Price}
     */
    private function buildCatalog(?string $stripePriceId = 'price_PRO_MXN'): array
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        /** @var Customer $customer */
        $customer = Customer::factory()
            ->inMexico()
            ->create(['tenant_id' => $tenant->id]);

        CustomerGatewayRef::create([
            'customer_id' => $customer->id,
            'gateway' => Gateway::Stripe,
            'gateway_customer_id' => 'cus_TEST_CUSTOMER',
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

        return compact('tenant', 'customer', 'plan', 'price');
    }

    private function makeGatewaySubscription(string $id = 'sub_NEW'): GatewaySubscription
    {
        return new GatewaySubscription(
            gatewayId: $id,
            gatewayCustomerId: 'cus_TEST_CUSTOMER',
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
        );
    }

    #[Test]
    public function creates_subscription_with_trialing_status_and_trial_ends_at_14_days(): void
    {
        $g = $this->buildCatalog();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSubscription')
            ->once()
            ->andReturn($this->makeGatewaySubscription('sub_HAPPY'));

        $action = new CreateSubscriptionAction($writer, app(PriceResolver::class));
        $subscription = $action->execute($g['customer'], $g['plan'], BillingInterval::Month);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame('sub_HAPPY', $subscription->stripe_subscription_id);
        $this->assertSame($g['customer']->id, $subscription->customer_id);
        $this->assertSame($g['plan']->id, $subscription->plan_id);
        $this->assertSame($g['price']->id, $subscription->price_id);
        $this->assertNotNull($subscription->trial_ends_at);
        // 14 days from now, with a one-minute tolerance for test execution time.
        $this->assertEqualsWithDelta(
            now()->addDays(14)->timestamp,
            $subscription->trial_ends_at->timestamp,
            60.0,
        );
    }

    #[Test]
    public function resolves_price_via_customer_currency_and_interval(): void
    {
        $g = $this->buildCatalog();

        // Add a second, non-matching Price (yearly) on the same plan to
        // prove the resolver picks the monthly one.
        Price::factory()
            ->yearly()
            ->inMxn()
            ->create([
                'plan_id' => $g['plan']->id,
                'country' => null,
                'gateway_refs' => ['stripe' => 'price_YEARLY_MXN'],
            ]);

        $capturedPriceId = null;

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSubscription')
            ->once()
            ->withArgs(function (CreateSubscriptionInput $input, string $key) use (&$capturedPriceId): bool {
                $capturedPriceId = $input->gatewayPriceId;

                return true;
            })
            ->andReturn($this->makeGatewaySubscription());

        $action = new CreateSubscriptionAction($writer, app(PriceResolver::class));
        $action->execute($g['customer'], $g['plan'], BillingInterval::Month);

        $this->assertSame('price_PRO_MXN', $capturedPriceId);
    }

    #[Test]
    public function throws_when_customer_has_no_gateway_ref_for_stripe(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var Customer $customer */
        $customer = Customer::factory()
            ->inMexico()
            ->create(['tenant_id' => $tenant->id]);
        // Intentionally NO CustomerGatewayRef for Stripe.

        /** @var Plan $plan */
        $plan = Plan::factory()->create();
        Price::factory()
            ->monthly()
            ->inMxn()
            ->create([
                'plan_id' => $plan->id,
                'country' => null,
                'gateway_refs' => ['stripe' => 'price_X'],
            ]);

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldNotReceive('createSubscription');

        $action = new CreateSubscriptionAction($writer, app(PriceResolver::class));

        $this->expectException(CustomerNotRegisteredInGatewayException::class);
        $action->execute($customer, $plan, BillingInterval::Month);
    }

    #[Test]
    public function throws_when_price_has_no_stripe_mapping(): void
    {
        // gateway_refs = null (no 'stripe' key)
        $g = $this->buildCatalog(stripePriceId: null);

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldNotReceive('createSubscription');

        $action = new CreateSubscriptionAction($writer, app(PriceResolver::class));

        $this->expectException(PriceMissingGatewayMappingException::class);
        $action->execute($g['customer'], $g['plan'], BillingInterval::Month);
    }

    #[Test]
    public function forwards_idempotency_key_and_metadata_to_gateway(): void
    {
        $g = $this->buildCatalog();

        $capturedKey = null;
        $capturedMetadata = null;

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSubscription')
            ->once()
            ->withArgs(function (CreateSubscriptionInput $input, string $key) use (&$capturedKey, &$capturedMetadata): bool {
                $capturedKey = $key;
                $capturedMetadata = $input->metadata;

                return true;
            })
            ->andReturn($this->makeGatewaySubscription());

        $action = new CreateSubscriptionAction($writer, app(PriceResolver::class));
        $action->execute($g['customer'], $g['plan'], BillingInterval::Month);

        $this->assertNotNull($capturedKey);
        $this->assertSame($g['customer']->tenant_id, $capturedMetadata['tenant_id']);
        $this->assertSame($g['customer']->id, $capturedMetadata['app_customer_id']);

        $persisted = IdempotencyKey::query()
            ->where('operation', 'create_subscription')
            ->first();
        $this->assertNotNull($persisted);
        $this->assertSame($capturedKey, $persisted->idempotency_key);
        $this->assertSame($g['customer']->id, $persisted->customer_id);
    }

    #[Test]
    public function inserts_initial_state_transition_row_with_reason_created(): void
    {
        $g = $this->buildCatalog();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSubscription')
            ->once()
            ->andReturn($this->makeGatewaySubscription());

        $action = new CreateSubscriptionAction($writer, app(PriceResolver::class));
        $subscription = $action->execute($g['customer'], $g['plan'], BillingInterval::Month);

        $rows = SubscriptionStateTransition::query()
            ->where('subscription_id', $subscription->id)
            ->get();

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertNotNull($row);
        $this->assertSame(SubscriptionStatus::Trialing->value, $row->from_status);
        $this->assertSame(SubscriptionStatus::Trialing->value, $row->to_status);
        $this->assertSame('created', $row->reason);
        $this->assertIsArray($row->context);
        $this->assertSame($g['plan']->code, $row->context['plan_code']);
        $this->assertSame(14, $row->context['trial_days']);
    }

    #[Test]
    public function returns_existing_subscription_on_idempotency_retry(): void
    {
        $g = $this->buildCatalog();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSubscription')
            ->once() // <-- key assertion: gateway called ONCE across both executes
            ->andReturn($this->makeGatewaySubscription('sub_RETRY'));

        $action = new CreateSubscriptionAction($writer, app(PriceResolver::class));
        $first = $action->execute($g['customer'], $g['plan'], BillingInterval::Month);
        $second = $action->execute($g['customer'], $g['plan'], BillingInterval::Month);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Subscription::count());
        $this->assertSame(1, SubscriptionStateTransition::count());
        $this->assertSame(1, IdempotencyKey::where('operation', 'create_subscription')->count());
    }

    #[Test]
    public function dispatches_subscription_created_event_after_commit(): void
    {
        Event::fake([SubscriptionCreated::class]);

        $g = $this->buildCatalog();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSubscription')
            ->once()
            ->andReturn($this->makeGatewaySubscription('sub_EVENT'));

        $action = new CreateSubscriptionAction($writer, app(PriceResolver::class));
        $subscription = $action->execute($g['customer'], $g['plan'], BillingInterval::Month);

        Event::assertDispatched(SubscriptionCreated::class, function (SubscriptionCreated $event) use ($subscription): bool {
            return $event->subscription->id === $subscription->id;
        });
    }
}
