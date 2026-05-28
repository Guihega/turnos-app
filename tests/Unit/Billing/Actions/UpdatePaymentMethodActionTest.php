<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Actions;

use App\Actions\Billing\UpdatePaymentMethodAction;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\GatewayPaymentMethod;
use App\Enums\Billing\Gateway;
use App\Events\Billing\PaymentMethodAttached;
use App\Exceptions\Billing\CustomerNotRegisteredInGatewayException;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Billing\IdempotencyKey;
use App\Models\Billing\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for UpdatePaymentMethodAction.
 *
 * The BillingGatewayWriter is mocked; everything else (DB writes,
 * events, idempotency persistence) runs for real against the test DB.
 *
 * Covers:
 *   - gateway ref missing → exception, no writes
 *   - happy path: gateway attach + local upsert + event post-commit
 *   - setAsDefault=true clears is_default on sibling PMs
 *   - setAsDefault=false does NOT touch siblings
 *   - transparent retry returns existing local PM without gateway call
 *   - event payload carries wasSetAsDefault flag correctly
 */
final class UpdatePaymentMethodActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeGatewayPaymentMethod(
        string $id = 'pm_NEW',
        bool $isDefault = false,
    ): GatewayPaymentMethod {
        return new GatewayPaymentMethod(
            gatewayId: $id,
            gatewayCustomerId: 'cus_TEST_LINKED',
            type: 'card',
            brand: 'visa',
            last4: '4242',
            expMonth: 12,
            expYear: 2030,
            isDefault: $isDefault,
            metadata: [],
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

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldNotReceive('attachPaymentMethod');

        $action = new UpdatePaymentMethodAction($writer);

        $this->expectException(CustomerNotRegisteredInGatewayException::class);
        $action->execute($customer, 'pm_anything');
    }

    #[Test]
    public function attaches_payment_method_and_persists_local_row(): void
    {
        Event::fake([PaymentMethodAttached::class]);

        $customer = $this->makeCustomerWithStripeRef();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('attachPaymentMethod')
            ->once()
            ->withArgs(function (
                string $gatewayCustomerId,
                string $paymentMethodId,
                bool $setAsDefault,
                string $idempotencyKey,
            ): bool {
                return $gatewayCustomerId === 'cus_TEST_LINKED'
                    && $paymentMethodId === 'pm_HAPPY'
                    && $setAsDefault === true
                    && str_starts_with($idempotencyKey, '01');
            })
            ->andReturn($this->makeGatewayPaymentMethod('pm_HAPPY', isDefault: true));

        $action = new UpdatePaymentMethodAction($writer);
        $result = $action->execute($customer, 'pm_HAPPY');

        $this->assertInstanceOf(PaymentMethod::class, $result);
        $this->assertSame($customer->id, $result->customer_id);
        $this->assertSame('pm_HAPPY', $result->stripe_payment_method_id);
        $this->assertSame('visa', $result->brand);
        $this->assertSame('4242', $result->last4);
        $this->assertTrue($result->is_default);

        Event::assertDispatched(PaymentMethodAttached::class, function (PaymentMethodAttached $e) use ($result): bool {
            return $e->paymentMethod->id === $result->id
                && $e->wasSetAsDefault === true;
        });
    }

    #[Test]
    public function clears_is_default_on_sibling_pms_when_set_as_default_true(): void
    {
        $customer = $this->makeCustomerWithStripeRef();

        // Pre-existing default PM on the same customer.
        /** @var PaymentMethod $oldDefault */
        $oldDefault = PaymentMethod::factory()
            ->default()
            ->create([
                'customer_id' => $customer->id,
                'stripe_payment_method_id' => 'pm_OLD_DEFAULT',
            ]);

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('attachPaymentMethod')
            ->once()
            ->andReturn($this->makeGatewayPaymentMethod('pm_NEW_DEFAULT', isDefault: true));

        $action = new UpdatePaymentMethodAction($writer);
        $action->execute($customer, 'pm_NEW_DEFAULT', setAsDefault: true);

        $oldDefault->refresh();
        $this->assertFalse($oldDefault->is_default, 'Old default should have been cleared');

        $newDefault = PaymentMethod::query()
            ->where('stripe_payment_method_id', 'pm_NEW_DEFAULT')
            ->first();
        $this->assertNotNull($newDefault);
        $this->assertTrue($newDefault->is_default);
    }

    #[Test]
    public function does_not_touch_sibling_pms_when_set_as_default_false(): void
    {
        $customer = $this->makeCustomerWithStripeRef();

        /** @var PaymentMethod $existingDefault */
        $existingDefault = PaymentMethod::factory()
            ->default()
            ->create([
                'customer_id' => $customer->id,
                'stripe_payment_method_id' => 'pm_EXISTING_DEFAULT',
            ]);

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('attachPaymentMethod')
            ->once()
            ->andReturn($this->makeGatewayPaymentMethod('pm_NON_DEFAULT', isDefault: false));

        $action = new UpdatePaymentMethodAction($writer);
        $action->execute($customer, 'pm_NON_DEFAULT', setAsDefault: false);

        $existingDefault->refresh();
        $this->assertTrue($existingDefault->is_default, 'Existing default must remain default');

        $newPm = PaymentMethod::query()
            ->where('stripe_payment_method_id', 'pm_NON_DEFAULT')
            ->first();
        $this->assertNotNull($newPm);
        $this->assertFalse($newPm->is_default);
    }

    #[Test]
    public function returns_existing_local_pm_on_transparent_retry_without_gateway_call(): void
    {
        $customer = $this->makeCustomerWithStripeRef();

        // Pre-existing local PM, simulating a row from a prior successful attach.
        /** @var PaymentMethod $existing */
        $existing = PaymentMethod::factory()->create([
            'customer_id' => $customer->id,
            'stripe_payment_method_id' => 'pm_ALREADY_ATTACHED',
        ]);

        $this->seedSnapshotForCustomer($customer, 'pm_ALREADY_ATTACHED', $existing->id, setAsDefault: true);

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldNotReceive('attachPaymentMethod');

        $action = new UpdatePaymentMethodAction($writer);
        $result = $action->execute($customer, 'pm_ALREADY_ATTACHED', setAsDefault: true);

        $this->assertSame($existing->id, $result->id);

        // Only one PM row exists for this PM id.
        $count = PaymentMethod::query()
            ->where('stripe_payment_method_id', 'pm_ALREADY_ATTACHED')
            ->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function event_payload_carries_was_set_as_default_false_when_flag_was_false(): void
    {
        Event::fake([PaymentMethodAttached::class]);

        $customer = $this->makeCustomerWithStripeRef();

        /** @var BillingGatewayWriter&MockInterface $writer */
        $writer = Mockery::mock(BillingGatewayWriter::class);
        $writer->shouldReceive('attachPaymentMethod')
            ->once()
            ->andReturn($this->makeGatewayPaymentMethod('pm_NO_DEFAULT', isDefault: false));

        $action = new UpdatePaymentMethodAction($writer);
        $action->execute($customer, 'pm_NO_DEFAULT', setAsDefault: false);

        Event::assertDispatched(PaymentMethodAttached::class, function (PaymentMethodAttached $e): bool {
            return $e->wasSetAsDefault === false;
        });
    }

    private function seedSnapshotForCustomer(
        Customer $customer,
        string $paymentMethodId,
        string $localPmId,
        bool $setAsDefault,
    ): IdempotencyKey {
        $action = new UpdatePaymentMethodAction(Mockery::mock(BillingGatewayWriter::class));
        $hash = (new \ReflectionMethod($action, 'hashRequest'))->invoke(
            $action,
            [
                'customer_id' => $customer->id,
                'gateway_customer_id' => 'cus_TEST_LINKED',
                'payment_method_id' => $paymentMethodId,
                'set_as_default' => $setAsDefault,
            ],
        );

        /** @var IdempotencyKey $key */
        $key = IdempotencyKey::create([
            'customer_id' => $customer->id,
            'operation' => 'attach_payment_method',
            'gateway' => Gateway::Stripe->value,
            'idempotency_key' => '01HX_TEST_ATTACH_001',
            'request_hash' => $hash,
            'response_snapshot' => [
                'local_payment_method_id' => $localPmId,
                'gateway_payment_method_id' => $paymentMethodId,
                'brand' => 'visa',
                'last4' => '4242',
                'is_default' => $setAsDefault,
            ],
            'expires_at' => now()->addDays(7),
        ]);

        return $key;
    }
}
