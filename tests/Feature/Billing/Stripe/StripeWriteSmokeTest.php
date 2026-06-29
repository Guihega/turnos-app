<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Stripe;

use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\CreateCustomerInput;
use App\Billing\DTOs\CreateSubscriptionInput;
use App\Billing\DTOs\GatewayCustomer;
use App\Billing\DTOs\GatewaySubscription;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LIVE smoke test against Stripe test mode.
 *
 * Calls the real Stripe API via BillingGatewayWriter (no mock).
 * Creates a real Customer and Subscription with trial. Does NOT
 * clean up; each run creates resources in the Stripe test dashboard.
 *
 * Skip conditions (in priority order):
 *   - STRIPE_TEST_SECRET_KEY not set: skip (CI without creds).
 *   - STRIPE_LIVE_SMOKE != 'true': skip (manual opt-in).
 *
 * To run: STRIPE_LIVE_SMOKE=true php artisan test --filter=StripeWriteSmokeTest
 */
final class StripeWriteSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $secret = config('billing.gateways.stripe.secret_key');
        if (! is_string($secret) || $secret === '') {
            $this->markTestSkipped('Stripe secret_key not configured.');
        }
        if (config('billing.smoke.live') !== true) {
            $this->markTestSkipped('Live smoke test is opt-in. Set STRIPE_LIVE_SMOKE=true.');
        }
    }

    #[Test]
    public function creates_a_real_customer_and_subscription_against_stripe_test_mode(): void
    {
        /** @var BillingGatewayWriter $writer */
        $writer = $this->app->make(BillingGatewayWriter::class);

        $runMarker = (string) time();

        $customerInput = new CreateCustomerInput(
            email: "smoke+{$runMarker}@turnos-app.test",
            name: 'PR-E Smoke Test',
            country: 'MX',
            taxId: null,
            metadata: [
                'source' => 'StripeWriteSmokeTest',
                'run_marker' => $runMarker,
            ],
        );
        $customer = $writer->createCustomer($customerInput, "smoke_cust_{$runMarker}");

        $this->assertInstanceOf(GatewayCustomer::class, $customer);
        $this->assertStringStartsWith('cus_', $customer->gatewayId);

        $stripeTestPriceId = config('billing.smoke.test_price_id');
        if (! is_string($stripeTestPriceId) || $stripeTestPriceId === '') {
            $this->markTestIncomplete(
                'STRIPE_TEST_PRICE_ID not set — customer created, subscription leg skipped.'
            );
        }

        $subscriptionInput = new CreateSubscriptionInput(
            gatewayCustomerId: $customer->gatewayId,
            gatewayPriceId: $stripeTestPriceId,
            trialDays: 14,
            metadata: [
                'source' => 'StripeWriteSmokeTest',
                'run_marker' => $runMarker,
            ],
        );
        $subscription = $writer->createSubscription($subscriptionInput, "smoke_sub_{$runMarker}");

        $this->assertInstanceOf(GatewaySubscription::class, $subscription);
        $this->assertStringStartsWith('sub_', $subscription->gatewayId);
        $this->assertSame($customer->gatewayId, $subscription->gatewayCustomerId);
    }
}
