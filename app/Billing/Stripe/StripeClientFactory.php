<?php

declare(strict_types=1);

namespace App\Billing\Stripe;

use Illuminate\Contracts\Config\Repository;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Builds a configured StripeClient using credentials resolved from
 * config('billing.stripe'). The config layer (PR #8) already does the
 * STRIPE_TEST_* vs STRIPE_LIVE_* switch; this factory just consumes
 * the resolved values.
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
        $secret = $this->config->get('billing.stripe.secret_key');
        $apiVersion = $this->config->get('billing.stripe.api_version');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException(
                'billing.stripe.secret_key is missing or empty. Check STRIPE_MODE and the matching STRIPE_*_SECRET env var.'
            );
        }

        if (! is_string($apiVersion) || $apiVersion === '') {
            throw new RuntimeException(
                'billing.stripe.api_version is missing. It must be pinned (see PR #8).'
            );
        }

        return new StripeClient([
            'api_key' => $secret,
            'stripe_version' => $apiVersion,
            // SDK retries 5xx automatically. 3 keeps tail latency bounded.
            'max_network_retries' => 3,
        ]);
    }
}
