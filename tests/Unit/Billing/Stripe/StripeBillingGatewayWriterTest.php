<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Stripe;

use App\Billing\DTOs\CreateCustomerInput;
use App\Billing\DTOs\CreateSubscriptionInput;
use App\Billing\DTOs\GatewayCustomer;
use App\Billing\DTOs\GatewaySubscription;
use App\Billing\Exceptions\GatewayIdempotencyConflictException;
use App\Billing\Stripe\StripeBillingGateway;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stripe\Customer;
use Stripe\Exception\IdempotencyException;
use Stripe\Service\CustomerService;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Subscription;

/**
 * Unit tests for the write-side of StripeBillingGateway (PR-E).
 *
 * Per ADR-016 the write contract MUST:
 *
 *   1. Forward the idempotency key to Stripe as an option, not in
 *      the request body.
 *   2. Build payloads that match the gateway's native shape
 *      (e.g. `items: [{price: ...}]` for subscription create).
 *   3. Skip optional fields when their input is null/0 rather than
 *      sending nulls (Stripe rejects unknown null fields).
 *   4. Translate IdempotencyException into
 *      GatewayIdempotencyConflictException so the Action layer can
 *      log+abort instead of retrying with the same key.
 *
 * These tests run with the Stripe SDK fully mocked. No network,
 * no DB, no Laravel container.
 */
final class StripeBillingGatewayWriterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeConfig(): Repository
    {
        return new Repository([
            'billing' => [
                'gateways' => [
                    'stripe' => [
                        'secret_key' => 'sk_test_dummy',
                        'api_version' => '2024-11-20.acacia',
                        'webhook_secret' => 'whsec_test_dummy',
                    ],
                ],
            ],
        ]);
    }

    private function makeStripeCustomer(): Customer
    {
        return Customer::constructFrom([
            'id' => 'cus_NEW_TEST',
            'email' => 'owner@acme.test',
            'name' => 'Acme Co.',
            'currency' => 'mxn',
            'invoice_settings' => StripeObject::constructFrom([
                'default_payment_method' => null,
            ]),
            'metadata' => StripeObject::constructFrom([
                'tenant_id' => '01HXYZ_TENANT',
                'app_customer_id' => '01HXYZ_CUSTOMER',
            ]),
        ]);
    }

    private function makeStripeSubscription(): Subscription
    {
        return Subscription::constructFrom([
            'id' => 'sub_NEW_TEST',
            'customer' => 'cus_NEW_TEST',
            'status' => 'trialing',
            'current_period_start' => 1700000000,
            'current_period_end' => 1701209600,
            'trial_start' => 1700000000,
            'trial_end' => 1701209600,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'metadata' => StripeObject::constructFrom([]),
        ]);
    }

    #[Test]
    public function create_customer_sends_email_name_and_metadata(): void
    {
        $input = new CreateCustomerInput(
            email: 'owner@acme.test',
            name: 'Acme Co.',
            country: 'MX',
            taxId: null,
            metadata: [
                'tenant_id' => '01HXYZ_TENANT',
                'app_customer_id' => '01HXYZ_CUSTOMER',
            ],
        );

        /** @var CustomerService&MockInterface $service */
        $service = Mockery::mock(CustomerService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload, array $options): bool {
                return $payload['email'] === 'owner@acme.test'
                    && $payload['name'] === 'Acme Co.'
                    && $payload['metadata']['tenant_id'] === '01HXYZ_TENANT'
                    && $options['idempotency_key'] === 'idem_KEY_123';
            })
            ->andReturn($this->makeStripeCustomer());

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->customers = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $result = $gateway->createCustomer($input, 'idem_KEY_123');

        $this->assertInstanceOf(GatewayCustomer::class, $result);
        $this->assertSame('cus_NEW_TEST', $result->gatewayId);
    }

    #[Test]
    public function create_customer_omits_name_when_null(): void
    {
        $input = new CreateCustomerInput(
            email: 'noname@acme.test',
            name: null,
            country: 'MX',
            taxId: null,
            metadata: ['tenant_id' => 'T1'],
        );

        /** @var CustomerService&MockInterface $service */
        $service = Mockery::mock(CustomerService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload, array $options): bool {
                return ! array_key_exists('name', $payload)
                    && $payload['email'] === 'noname@acme.test';
            })
            ->andReturn($this->makeStripeCustomer());

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->customers = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $result = $gateway->createCustomer($input, 'idem_K');

        $this->assertInstanceOf(GatewayCustomer::class, $result);
    }

    #[Test]
    public function create_customer_translates_idempotency_exception(): void
    {
        $input = new CreateCustomerInput(
            email: 'x@y.z',
            name: null,
            country: 'MX',
            taxId: null,
            metadata: [],
        );

        $idempotencyException = IdempotencyException::factory(
            message: 'Keys for idempotent requests can only be used with the same parameters they were first used with.',
            httpStatus: 400,
        );

        /** @var CustomerService&MockInterface $service */
        $service = Mockery::mock(CustomerService::class);
        $service->shouldReceive('create')->andThrow($idempotencyException);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->customers = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $this->expectException(GatewayIdempotencyConflictException::class);
        $gateway->createCustomer($input, 'idem_REUSED');
    }

    #[Test]
    public function create_subscription_sends_customer_items_and_payment_behavior(): void
    {
        $input = new CreateSubscriptionInput(
            gatewayCustomerId: 'cus_NEW_TEST',
            gatewayPriceId: 'price_PRO_MXN',
            trialDays: 14,
            metadata: [
                'tenant_id' => '01HXYZ_TENANT',
                'app_subscription_id' => '01HXYZ_SUB',
            ],
        );

        /** @var SubscriptionService&MockInterface $service */
        $service = Mockery::mock(SubscriptionService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload, array $options): bool {
                return $payload['customer'] === 'cus_NEW_TEST'
                    && $payload['items'][0]['price'] === 'price_PRO_MXN'
                    && $payload['payment_behavior'] === 'default_incomplete'
                    && $payload['trial_period_days'] === 14
                    && $payload['metadata']['tenant_id'] === '01HXYZ_TENANT'
                    && $options['idempotency_key'] === 'idem_SUB_1';
            })
            ->andReturn($this->makeStripeSubscription());

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->subscriptions = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $result = $gateway->createSubscription($input, 'idem_SUB_1');

        $this->assertInstanceOf(GatewaySubscription::class, $result);
        $this->assertSame('sub_NEW_TEST', $result->gatewayId);
    }

    #[Test]
    public function create_subscription_omits_trial_period_days_when_zero(): void
    {
        $input = new CreateSubscriptionInput(
            gatewayCustomerId: 'cus_X',
            gatewayPriceId: 'price_X',
            trialDays: 0,
            metadata: [],
        );

        /** @var SubscriptionService&MockInterface $service */
        $service = Mockery::mock(SubscriptionService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload, array $options): bool {
                return ! array_key_exists('trial_period_days', $payload)
                    && $payload['payment_behavior'] === 'default_incomplete';
            })
            ->andReturn($this->makeStripeSubscription());

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->subscriptions = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $result = $gateway->createSubscription($input, 'idem_NO_TRIAL');

        $this->assertInstanceOf(GatewaySubscription::class, $result);
    }

    #[Test]
    public function create_subscription_requests_payment_settings_save_on_subscription(): void
    {
        $input = new CreateSubscriptionInput(
            gatewayCustomerId: 'cus_X',
            gatewayPriceId: 'price_X',
            trialDays: 14,
            metadata: [],
        );

        /** @var SubscriptionService&MockInterface $service */
        $service = Mockery::mock(SubscriptionService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload, array $options): bool {
                return ($payload['payment_settings']['save_default_payment_method'] ?? null) === 'on_subscription';
            })
            ->andReturn($this->makeStripeSubscription());

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->subscriptions = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $result = $gateway->createSubscription($input, 'idem_X');

        $this->assertInstanceOf(GatewaySubscription::class, $result);
    }

    #[Test]
    public function create_subscription_expands_latest_invoice_payment_intent(): void
    {
        // Expanding latest_invoice.payment_intent lets the Action layer
        // read the PaymentIntent client_secret without an extra round trip,
        // which PR-G will need when emitting Stripe Checkout links.
        $input = new CreateSubscriptionInput(
            gatewayCustomerId: 'cus_X',
            gatewayPriceId: 'price_X',
            trialDays: 14,
            metadata: [],
        );

        /** @var SubscriptionService&MockInterface $service */
        $service = Mockery::mock(SubscriptionService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload, array $options): bool {
                return in_array('latest_invoice.payment_intent', $payload['expand'] ?? [], true);
            })
            ->andReturn($this->makeStripeSubscription());

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->subscriptions = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $result = $gateway->createSubscription($input, 'idem_X');

        $this->assertInstanceOf(GatewaySubscription::class, $result);
    }

    #[Test]
    public function create_subscription_translates_idempotency_exception(): void
    {
        $input = new CreateSubscriptionInput(
            gatewayCustomerId: 'cus_X',
            gatewayPriceId: 'price_X',
            trialDays: 14,
            metadata: [],
        );

        $idempotencyException = IdempotencyException::factory(
            message: 'Keys for idempotent requests can only be used with the same parameters they were first used with.',
            httpStatus: 400,
        );

        /** @var SubscriptionService&MockInterface $service */
        $service = Mockery::mock(SubscriptionService::class);
        $service->shouldReceive('create')->andThrow($idempotencyException);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->subscriptions = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $this->expectException(GatewayIdempotencyConflictException::class);
        $gateway->createSubscription($input, 'idem_REUSED');
    }
}
