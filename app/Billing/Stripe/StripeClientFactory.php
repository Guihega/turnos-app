<?php

declare(strict_types=1);

namespace App\Billing\Stripe;

use Illuminate\Contracts\Config\Repository;
use RuntimeException;
use Stripe\Stripe;
use Stripe\StripeClient;

/**
 * Builds a configured StripeClient using credentials resolved from
 * config('billing.gateways.stripe'). The config layer (PR #8) does the
 * STRIPE_TEST_* vs STRIPE_LIVE_* switch via STRIPE_MODE; this factory
 * just consumes the resolved values.
 *
 * Why a factory instead of constructing StripeClient inline:
 * - keeps the API key + version + retries policy in ONE place,
 * - is trivially mockable in tests by binding a different factory,
 * - prevents tests from accidentally hitting real Stripe by binding
 *   a noop StripeClient.
 *
 * Per docs/billing/SECRETS.md, the secret key is read from process
 * environment via config — never hard-coded, never logged.
 */
final class StripeClientFactory
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function make(): StripeClient
    {
        $secret = $this->config->get('billing.gateways.stripe.secret_key');
        $apiVersion = $this->config->get('billing.gateways.stripe.api_version');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException(
                'billing.gateways.stripe.secret_key is missing or empty. Check STRIPE_MODE and the matching STRIPE_*_SECRET_KEY env var.'
            );
        }

        if (! is_string($apiVersion) || $apiVersion === '') {
            throw new RuntimeException(
                'billing.gateways.stripe.api_version is missing. It must be pinned (see PR #8).'
            );
        }

        // SDK retries 5xx automatically. 3 keeps tail latency bounded.
        // Note: max_network_retries is NOT a constructor option of
        // StripeClient (verified against stripe/stripe-php ^14); it must
        // be set via the SDK's static configuration.
        Stripe::setMaxNetworkRetries(3);

        return new StripeClient([
            'api_key' => $secret,
            'stripe_version' => $apiVersion,
        ]);
    }
}
