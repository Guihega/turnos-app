<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Stripe;

use App\Billing\DTOs\GatewayCustomer;
use App\Billing\DTOs\GatewayInvoice;
use App\Billing\DTOs\GatewayPaymentMethod;
use App\Billing\DTOs\GatewaySubscription;
use App\Billing\Exceptions\GatewayAuthenticationException;
use App\Billing\Exceptions\GatewayException;
use App\Billing\Exceptions\GatewayNotFoundException;
use App\Billing\Exceptions\GatewaySignatureException;
use App\Billing\Stripe\StripeBillingGateway;
use App\Enums\Billing\SubscriptionStatus;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stripe\Collection;
use Stripe\Customer;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Invoice;
use Stripe\PaymentMethod;
use Stripe\Service\CustomerService;
use Stripe\Service\InvoiceService;
use Stripe\Service\PaymentMethodService;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Subscription;

/**
 * Pure unit tests for the Stripe adapter. The Stripe SDK is mocked
 * with Mockery; no network, no DB, no Laravel container. We assert:
 *
 *   1. Each retrieve* method calls the right SDK service method,
 *   2. SDK objects are mapped to DTOs with the expected fields,
 *   3. SDK exceptions are translated to App\Billing\Exceptions\*,
 *   4. mapStripeStatus produces the right SubscriptionStatus values
 *      and falls back to null for unmodeled states.
 *
 * `@var X&MockInterface` annotations on Mockery::mock() calls let
 * PHPStan resolve both the SDK type (for argument compatibility with
 * StripeBillingGateway's constructor) and the Mockery surface (for
 * shouldReceive/andThrow chains).
 */
final class StripeBillingGatewayTest extends TestCase
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
                'stripe' => [
                    'secret_key' => 'sk_test_dummy',
                    'api_version' => '2024-11-20.acacia',
                    'webhook_secret' => 'whsec_test_dummy',
                ],
            ],
        ]);
    }

    #[Test]
    public function retrieve_customer_maps_a_stripe_customer_to_a_dto(): void
    {
        $stripeCustomer = Customer::constructFrom([
            'id' => 'cus_TEST123',
            'email' => 'foo@example.com',
            'name' => 'Foo Co.',
            'currency' => 'mxn',
            'invoice_settings' => StripeObject::constructFrom([
                'default_payment_method' => 'pm_TEST_DEFAULT',
            ]),
            'metadata' => StripeObject::constructFrom(['tier' => 'pro']),
        ]);

        /** @var CustomerService&MockInterface $service */
        $service = Mockery::mock(CustomerService::class);
        $service->shouldReceive('retrieve')
            ->once()
            ->with('cus_TEST123')
            ->andReturn($stripeCustomer);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->customers = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $dto = $gateway->retrieveCustomer('cus_TEST123');

        $this->assertInstanceOf(GatewayCustomer::class, $dto);
        $this->assertSame('cus_TEST123', $dto->gatewayId);
        $this->assertSame('foo@example.com', $dto->email);
        $this->assertSame('Foo Co.', $dto->name);
        $this->assertSame('pm_TEST_DEFAULT', $dto->defaultPaymentMethodId);
        $this->assertSame('mxn', $dto->currency);
        $this->assertFalse($dto->deleted);
        $this->assertSame(['tier' => 'pro'], $dto->metadata);
    }

    #[Test]
    public function retrieve_subscription_maps_status_and_period_dates(): void
    {
        $stripeSub = Subscription::constructFrom([
            'id' => 'sub_TEST',
            'customer' => 'cus_TEST',
            'status' => 'active',
            'items' => StripeObject::constructFrom([
                'data' => [
                    StripeObject::constructFrom([
                        'price' => StripeObject::constructFrom(['id' => 'price_TEST']),
                        'quantity' => 2,
                    ]),
                ],
            ]),
            'current_period_start' => 1_700_000_000,
            'current_period_end' => 1_702_592_000,
            'trial_end' => null,
            'cancel_at' => null,
            'canceled_at' => null,
            'cancel_at_period_end' => false,
            'metadata' => StripeObject::constructFrom([]),
        ]);

        /** @var SubscriptionService&MockInterface $service */
        $service = Mockery::mock(SubscriptionService::class);
        $service->shouldReceive('retrieve')->once()->with('sub_TEST')->andReturn($stripeSub);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->subscriptions = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $dto = $gateway->retrieveSubscription('sub_TEST');

        $this->assertInstanceOf(GatewaySubscription::class, $dto);
        $this->assertSame('sub_TEST', $dto->gatewayId);
        $this->assertSame('cus_TEST', $dto->gatewayCustomerId);
        $this->assertSame(SubscriptionStatus::Active->value, $dto->status);
        $this->assertSame('active', $dto->rawStatus);
        $this->assertCount(1, $dto->items);
        $this->assertSame('price_TEST', $dto->items[0]['price_id']);
        $this->assertSame(2, $dto->items[0]['quantity']);
        $this->assertSame(1_700_000_000, $dto->currentPeriodStart?->getTimestamp());
        $this->assertSame(1_702_592_000, $dto->currentPeriodEnd?->getTimestamp());
        $this->assertNull($dto->trialEnd);
        $this->assertFalse($dto->cancelAtPeriodEnd);
    }

    #[Test]
    public function unmappable_stripe_status_yields_null_status_with_raw_preserved(): void
    {
        $stripeSub = Subscription::constructFrom([
            'id' => 'sub_INC',
            'customer' => 'cus_X',
            'status' => 'incomplete',
            'items' => StripeObject::constructFrom(['data' => []]),
            'cancel_at_period_end' => false,
            'metadata' => StripeObject::constructFrom([]),
        ]);

        /** @var SubscriptionService&MockInterface $service */
        $service = Mockery::mock(SubscriptionService::class);
        $service->shouldReceive('retrieve')->andReturn($stripeSub);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->subscriptions = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());
        $dto = $gateway->retrieveSubscription('sub_INC');

        $this->assertNull($dto->status, 'incomplete has no domain equivalent');
        $this->assertSame('incomplete', $dto->rawStatus);
    }

    #[Test]
    public function stripe_unpaid_status_maps_to_suspended(): void
    {
        $stripeSub = Subscription::constructFrom([
            'id' => 'sub_UN',
            'customer' => 'cus_X',
            'status' => 'unpaid',
            'items' => StripeObject::constructFrom(['data' => []]),
            'cancel_at_period_end' => false,
            'metadata' => StripeObject::constructFrom([]),
        ]);

        /** @var SubscriptionService&MockInterface $service */
        $service = Mockery::mock(SubscriptionService::class);
        $service->shouldReceive('retrieve')->andReturn($stripeSub);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->subscriptions = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());
        $dto = $gateway->retrieveSubscription('sub_UN');

        $this->assertSame(SubscriptionStatus::Suspended->value, $dto->status);
    }

    #[Test]
    public function retrieve_invoice_maps_amounts_and_status(): void
    {
        $stripeInvoice = Invoice::constructFrom([
            'id' => 'in_TEST',
            'customer' => 'cus_TEST',
            'subscription' => 'sub_TEST',
            'status' => 'paid',
            'currency' => 'mxn',
            'amount_due' => 49900,
            'amount_paid' => 49900,
            'amount_remaining' => 0,
            'hosted_invoice_url' => 'https://invoice.stripe.com/TEST',
            'created' => 1_700_000_000,
            'due_date' => null,
            'status_transitions' => StripeObject::constructFrom([
                'paid_at' => 1_700_001_000,
            ]),
            'metadata' => StripeObject::constructFrom([]),
        ]);

        /** @var InvoiceService&MockInterface $service */
        $service = Mockery::mock(InvoiceService::class);
        $service->shouldReceive('retrieve')->once()->with('in_TEST')->andReturn($stripeInvoice);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->invoices = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());
        $dto = $gateway->retrieveInvoice('in_TEST');

        $this->assertInstanceOf(GatewayInvoice::class, $dto);
        $this->assertSame('in_TEST', $dto->gatewayId);
        $this->assertSame('cus_TEST', $dto->gatewayCustomerId);
        $this->assertSame('sub_TEST', $dto->gatewaySubscriptionId);
        $this->assertSame('paid', $dto->rawStatus);
        $this->assertSame('mxn', $dto->currency);
        $this->assertSame(49900, $dto->amountDue);
        $this->assertSame(49900, $dto->amountPaid);
        $this->assertSame(0, $dto->amountRemaining);
        $this->assertSame('https://invoice.stripe.com/TEST', $dto->hostedInvoiceUrl);
        $this->assertSame(1_700_000_000, $dto->created?->getTimestamp());
        $this->assertSame(1_700_001_000, $dto->paidAt?->getTimestamp());
    }

    #[Test]
    public function list_payment_methods_flags_the_default_one(): void
    {
        $customer = Customer::constructFrom([
            'id' => 'cus_PM',
            'invoice_settings' => StripeObject::constructFrom([
                'default_payment_method' => 'pm_DEFAULT',
            ]),
            'metadata' => StripeObject::constructFrom([]),
        ]);

        $pmDefault = PaymentMethod::constructFrom([
            'id' => 'pm_DEFAULT',
            'customer' => 'cus_PM',
            'type' => 'card',
            'card' => StripeObject::constructFrom([
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2030,
            ]),
            'metadata' => StripeObject::constructFrom([]),
        ]);

        $pmOther = PaymentMethod::constructFrom([
            'id' => 'pm_OTHER',
            'customer' => 'cus_PM',
            'type' => 'card',
            'card' => StripeObject::constructFrom([
                'brand' => 'mastercard',
                'last4' => '5555',
                'exp_month' => 6,
                'exp_year' => 2028,
            ]),
            'metadata' => StripeObject::constructFrom([]),
        ]);

        $collection = Collection::constructFrom([
            'data' => [$pmDefault, $pmOther],
            'has_more' => false,
        ]);

        /** @var CustomerService&MockInterface $customerService */
        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('retrieve')->once()->with('cus_PM')->andReturn($customer);

        /** @var PaymentMethodService&MockInterface $pmService */
        $pmService = Mockery::mock(PaymentMethodService::class);
        $pmService->shouldReceive('all')->once()->with(Mockery::on(function (array $args): bool {
            return $args['customer'] === 'cus_PM' && $args['type'] === 'card';
        }))->andReturn($collection);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->customers = $customerService;
        $client->paymentMethods = $pmService;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());
        $methods = $gateway->listPaymentMethods('cus_PM');

        $this->assertCount(2, $methods);
        $this->assertContainsOnlyInstancesOf(GatewayPaymentMethod::class, $methods);

        $this->assertSame('pm_DEFAULT', $methods[0]->gatewayId);
        $this->assertTrue($methods[0]->isDefault);
        $this->assertSame('visa', $methods[0]->brand);
        $this->assertSame('4242', $methods[0]->last4);

        $this->assertSame('pm_OTHER', $methods[1]->gatewayId);
        $this->assertFalse($methods[1]->isDefault);
    }

    #[Test]
    public function not_found_error_from_stripe_translates_to_gateway_not_found(): void
    {
        $stripeException = InvalidRequestException::factory(
            message: 'No such customer: cus_DOES_NOT_EXIST',
            httpStatus: 404,
            stripeCode: 'resource_missing',
        );

        /** @var CustomerService&MockInterface $service */
        $service = Mockery::mock(CustomerService::class);
        $service->shouldReceive('retrieve')->andThrow($stripeException);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->customers = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $this->expectException(GatewayNotFoundException::class);
        $gateway->retrieveCustomer('cus_DOES_NOT_EXIST');
    }

    #[Test]
    public function invalid_request_without_resource_missing_yields_generic_gateway_exception(): void
    {
        $stripeException = InvalidRequestException::factory(
            message: 'You provided an invalid parameter',
            httpStatus: 400,
            stripeCode: 'parameter_invalid_empty',
        );

        /** @var CustomerService&MockInterface $service */
        $service = Mockery::mock(CustomerService::class);
        $service->shouldReceive('retrieve')->andThrow($stripeException);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->customers = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        try {
            $gateway->retrieveCustomer('cus_X');
            $this->fail('Expected GatewayException was not thrown.');
        } catch (GatewayException $e) {
            $this->assertNotInstanceOf(GatewayNotFoundException::class, $e);
        }
    }

    #[Test]
    public function authentication_error_translates_to_authentication_exception(): void
    {
        $stripeException = AuthenticationException::factory(
            message: 'Invalid API key',
            httpStatus: 401,
        );

        /** @var CustomerService&MockInterface $service */
        $service = Mockery::mock(CustomerService::class);
        $service->shouldReceive('retrieve')->andThrow($stripeException);

        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);
        $client->customers = $service;

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $this->expectException(GatewayAuthenticationException::class);
        $gateway->retrieveCustomer('cus_X');
    }

    #[Test]
    public function verify_webhook_signature_with_bad_signature_throws_signature_exception(): void
    {
        // Drive Stripe\Webhook::constructEvent into its real signature
        // check with a bogus signature; the SDK will raise
        // SignatureVerificationException and our trait translates it.
        /** @var StripeClient&MockInterface $client */
        $client = Mockery::mock(StripeClient::class);

        $gateway = new StripeBillingGateway($client, $this->makeConfig());

        $payload = '{"id":"evt_test","type":"customer.created"}';
        $badSignature = 't=1700000000,v1=deadbeef';

        $this->expectException(GatewaySignatureException::class);
        $gateway->verifyWebhookSignature($payload, $badSignature);
    }
}
