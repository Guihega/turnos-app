<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Stripe;

use App\Billing\Contracts\BillingGateway;
use App\Billing\Stripe\StripeBillingGateway;
use App\Billing\Stripe\StripeClientFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Connectivity smoke test for StripeBillingGateway.
 *
 * This test verifies the FULL resolution chain end-to-end:
 *   config -> StripeClientFactory -> StripeClient -> Stripe API
 *
 * It does ONE read-only call (Balance::retrieve) which any account
 * can satisfy, and is therefore safe to run against test mode.
 *
 * The test SKIPS itself when STRIPE_TEST_SECRET_KEY is missing or
 * empty, so it does not break CI environments without Stripe creds.
 *
 * Why this exists:
 *
 *   PR-D's StripeClientFactory had two bugs that none of the unit
 *   tests caught — they used mocked config and a mocked StripeClient,
 *   so the real wiring (config path, SDK constructor signature) was
 *   never exercised. A single end-to-end smoke test prevents that
 *   class of regression.
 */
#[Group('integration')]
#[Group('billing-stripe')]
final class StripeConnectivitySmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $secret = config('billing.gateways.stripe.secret_key');
        if (! is_string($secret) || $secret === '') {
            $this->markTestSkipped(
                'STRIPE_TEST_SECRET_KEY not set — skipping live connectivity smoke test.'
            );
        }
    }

    #[Test]
    public function gateway_resolves_from_the_container_as_a_stripe_implementation(): void
    {
        $gateway = $this->app->make(BillingGateway::class);

        $this->assertInstanceOf(
            StripeBillingGateway::class,
            $gateway,
            'BillingGateway must resolve to StripeBillingGateway when stripe is the default gateway.'
        );
    }

    #[Test]
    public function the_resolved_gateway_can_talk_to_stripe(): void
    {
        // Drive a single read-only call through the gateway's own
        // dependency: the StripeClient built by StripeClientFactory.
        // We do NOT add a public method on the gateway just for this
        // test; we exercise the SDK directly using the same config
        // path the gateway uses.
        $client = $this->app->make(StripeClientFactory::class)->make();

        $balance = $client->balance->retrieve();

        // Shape sanity: Stripe always returns at least an `available`
        // collection on Balance. Asserting on the SDK's internal
        // StripeObject properties is outside the scope of this smoke
        // test — connectivity is proven by a non-empty response.
        $this->assertNotEmpty($balance->available);
    }
}
