<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Actions;

use App\Actions\Billing\CreateSetupIntentAction;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\GatewaySetupIntent;
use App\Enums\Billing\Gateway;
use App\Exceptions\Billing\CustomerNotRegisteredInGatewayException;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Billing\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for CreateSetupIntentAction.
 *
 * The BillingGatewayWriter is mocked; DB, idempotency persistence,
 * and the trait logic run for real against the test database.
 */
final class CreateSetupIntentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeGatewaySetupIntent(string $id = 'seti_NEW'): GatewaySetupIntent
    {
        return new GatewaySetupIntent(
            gatewayId: $id,
            clientSecret: $id.'_secret_xyz',
            status: 'requires_payment_method',
        );
    }

    private function makeCustomerWithStripeRef(): Customer
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create();
        CustomerGatewayRef::create([
            'customer_id' => $customer->id,
            'gateway' => Gateway::Stripe->value,
            'gateway_customer_id' => 'cus_TEST_LINKED',
            'metadata' => null,
        ]);

        return $customer->fresh() ?? $customer;
    }

    #[Test]
    public function throws_when_customer_has_no_gateway_ref(): void
    {
        /** @var Customer $customer */
        $customer = Customer::factory()->create();
        // Intentionally no CustomerGatewayRef.

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldNotReceive('createSetupIntent');

        $action = new CreateSetupIntentAction($writer);

        $this->expectException(CustomerNotRegisteredInGatewayException::class);
        $action->execute($customer);
    }

    #[Test]
    public function creates_setup_intent_via_gateway_on_first_call(): void
    {
        $customer = $this->makeCustomerWithStripeRef();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSetupIntent')
            ->once()
            ->withArgs(function (string $gatewayCustomerId, string $idempotencyKey): bool {
                return $gatewayCustomerId === 'cus_TEST_LINKED'
                    && str_starts_with($idempotencyKey, '01');
            })
            ->andReturn($this->makeGatewaySetupIntent('seti_HAPPY'));

        $action = new CreateSetupIntentAction($writer);
        $result = $action->execute($customer);

        $this->assertInstanceOf(GatewaySetupIntent::class, $result);
        $this->assertSame('seti_HAPPY', $result->gatewayId);
        $this->assertSame('seti_HAPPY_secret_xyz', $result->clientSecret);

        // Idempotency key was persisted with response snapshot.
        $key = IdempotencyKey::query()
            ->where('operation', 'create_setup_intent')
            ->where('customer_id', $customer->id)
            ->first();
        $this->assertNotNull($key);
        $this->assertNotNull($key->response_snapshot);
        $this->assertSame('seti_HAPPY', $key->response_snapshot['gateway_id']);
    }

    #[Test]
    public function returns_cached_dto_from_snapshot_on_transparent_retry(): void
    {
        $customer = $this->makeCustomerWithStripeRef();

        // Pre-seed an idempotency key with a snapshot — simulates a
        // previous successful call within the TTL window.
        $key = $this->seedSnapshotForCustomer($customer);

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        // Crucially, the gateway MUST NOT be called on retry.
        $writer->shouldNotReceive('createSetupIntent');

        $action = new CreateSetupIntentAction($writer);
        $result = $action->execute($customer);

        $this->assertInstanceOf(GatewaySetupIntent::class, $result);
        $this->assertSame('seti_CACHED', $result->gatewayId);
        $this->assertSame('seti_CACHED_secret_zzz', $result->clientSecret);
        $this->assertSame('requires_payment_method', $result->status);

        // No new key was created; the existing one was reused.
        $count = IdempotencyKey::query()
            ->where('operation', 'create_setup_intent')
            ->where('customer_id', $customer->id)
            ->count();
        $this->assertSame(1, $count);
        $this->assertSame($key->id, IdempotencyKey::query()
            ->where('operation', 'create_setup_intent')
            ->where('customer_id', $customer->id)
            ->first()->id);
    }

    #[Test]
    public function persists_idempotency_key_with_customer_link(): void
    {
        $customer = $this->makeCustomerWithStripeRef();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('createSetupIntent')
            ->once()
            ->andReturn($this->makeGatewaySetupIntent('seti_LINKED'));

        $action = new CreateSetupIntentAction($writer);
        $action->execute($customer);

        $key = IdempotencyKey::query()
            ->where('operation', 'create_setup_intent')
            ->first();
        $this->assertNotNull($key);
        $this->assertSame($customer->id, $key->customer_id);
        $this->assertSame(Gateway::Stripe->value, $key->gateway);
    }

    private function seedSnapshotForCustomer(Customer $customer): IdempotencyKey
    {
        $action = new CreateSetupIntentAction(Mockery::mock(BillingGatewayWriter::class));
        $hash = (new \ReflectionMethod($action, 'hashRequest'))->invoke(
            $action,
            [
                'customer_id' => $customer->id,
                'gateway_customer_id' => 'cus_TEST_LINKED',
            ],
        );

        /** @var IdempotencyKey $key */
        $key = IdempotencyKey::create([
            'customer_id' => $customer->id,
            'operation' => 'create_setup_intent',
            'gateway' => Gateway::Stripe->value,
            'idempotency_key' => '01HX_TEST_SETI_001',
            'request_hash' => $hash,
            'response_snapshot' => [
                'gateway_id' => 'seti_CACHED',
                'client_secret' => 'seti_CACHED_secret_zzz',
                'status' => 'requires_payment_method',
            ],
            'expires_at' => now()->addDays(7),
        ]);

        return $key;
    }
}
