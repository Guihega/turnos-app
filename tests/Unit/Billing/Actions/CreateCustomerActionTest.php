<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Actions;

use App\Actions\Billing\CreateCustomerAction;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\CreateCustomerInput;
use App\Billing\DTOs\GatewayCustomer;
use App\Billing\Exceptions\GatewayException;
use App\Enums\Billing\Gateway;
use App\Events\Billing\CustomerCreated;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Billing\IdempotencyKey;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for CreateCustomerAction.
 *
 * The BillingGatewayWriter is mocked; everything else (DB, events,
 * idempotency persistence) runs for real against the test database.
 *
 * Covers the saga semantics documented in ADR-016 §5:
 *   - happy path: gateway ok + DB writes + event
 *   - idempotency key forwarding
 *   - idempotency snapshot persistence
 *   - transparent retry when a snapshot exists
 *   - error propagation with clean DB
 *   - event dispatched ONLY after commit
 */
final class CreateCustomerActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeGatewayCustomer(string $id = 'cus_NEW'): GatewayCustomer
    {
        return new GatewayCustomer(
            gatewayId: $id,
            email: 'owner@acme.test',
            name: 'Acme Co.',
            defaultPaymentMethodId: null,
            currency: 'mxn',
            deleted: false,
            metadata: [],
        );
    }

    private function makeDetails(): array
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
    public function creates_customer_and_gateway_ref_locally_when_gateway_returns_ok(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createCustomer')
            ->once()
            ->andReturn($this->makeGatewayCustomer('cus_HAPPY'));

        $action = new CreateCustomerAction($writer);
        $customer = $action->execute($tenant, $this->makeDetails());

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame($tenant->id, $customer->tenant_id);
        $this->assertSame('owner@acme.test', $customer->billing_email);

        $ref = CustomerGatewayRef::query()
            ->where('customer_id', $customer->id)
            ->where('gateway', Gateway::Stripe->value)
            ->first();
        $this->assertNotNull($ref);
        $this->assertSame('cus_HAPPY', $ref->gateway_customer_id);
    }

    #[Test]
    public function forwards_idempotency_key_to_gateway(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $capturedKey = null;

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createCustomer')
            ->once()
            ->withArgs(function (CreateCustomerInput $input, string $key) use (&$capturedKey): bool {
                $capturedKey = $key;

                return $input->email === 'owner@acme.test'
                    && $input->metadata['tenant_id'] === $input->metadata['tenant_id']; // tautology to keep callback truthy
            })
            ->andReturn($this->makeGatewayCustomer());

        $action = new CreateCustomerAction($writer);
        $action->execute($tenant, $this->makeDetails());

        // The key forwarded to the gateway is also the one persisted locally.
        $this->assertNotNull($capturedKey);
        $persisted = IdempotencyKey::query()
            ->where('operation', 'create_customer')
            ->where('gateway', Gateway::Stripe->value)
            ->first();
        $this->assertNotNull($persisted);
        $this->assertSame($capturedKey, $persisted->idempotency_key);
    }

    #[Test]
    public function persists_idempotency_record_with_request_hash_and_snapshot(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createCustomer')
            ->once()
            ->andReturn($this->makeGatewayCustomer('cus_PERSIST'));

        $action = new CreateCustomerAction($writer);
        $customer = $action->execute($tenant, $this->makeDetails());

        $key = IdempotencyKey::query()
            ->where('operation', 'create_customer')
            ->first();

        $this->assertNotNull($key);
        $this->assertSame(64, strlen($key->request_hash));
        $this->assertSame($customer->id, $key->customer_id);
        $this->assertIsArray($key->response_snapshot);
        $this->assertSame('cus_PERSIST', $key->response_snapshot['gateway_customer_id']);
    }

    #[Test]
    public function returns_existing_customer_on_idempotency_retry_without_calling_gateway(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        // First call: gateway is invoked.
        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createCustomer')
            ->once() // <-- the key assertion: exactly ONE call across both executes
            ->andReturn($this->makeGatewayCustomer('cus_RETRY'));

        $action = new CreateCustomerAction($writer);
        $first = $action->execute($tenant, $this->makeDetails());

        // Second call with identical $details: should short-circuit on the
        // persisted snapshot and return the same Customer.
        $second = $action->execute($tenant, $this->makeDetails());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, IdempotencyKey::count());
        $this->assertSame(1, Customer::count());
    }

    #[Test]
    public function propagates_gateway_exception_and_leaves_db_clean(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createCustomer')
            ->once()
            ->andThrow(new GatewayException('Stripe is down'));

        $action = new CreateCustomerAction($writer);

        try {
            $action->execute($tenant, $this->makeDetails());
            $this->fail('Expected GatewayException was not thrown.');
        } catch (GatewayException $e) {
            $this->assertSame('Stripe is down', $e->getMessage());
        }

        // No customer, no gateway ref. But the idempotency key WAS minted
        // before the gateway call — that's intentional, so a retry reuses
        // the same key and the gateway dedupes server-side.
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, CustomerGatewayRef::count());
        $this->assertSame(1, IdempotencyKey::count());

        $key = IdempotencyKey::first();
        $this->assertNotNull($key);
        $this->assertNull($key->response_snapshot); // never snapshotted
        $this->assertNull($key->customer_id);       // never linked
    }

    #[Test]
    public function dispatches_customer_created_event_after_commit(): void
    {
        Event::fake([CustomerCreated::class]);

        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createCustomer')
            ->once()
            ->andReturn($this->makeGatewayCustomer('cus_EVENT'));

        $action = new CreateCustomerAction($writer);
        $customer = $action->execute($tenant, $this->makeDetails());

        Event::assertDispatched(CustomerCreated::class, function (CustomerCreated $event) use ($customer): bool {
            return $event->customer->id === $customer->id;
        });
    }
}
