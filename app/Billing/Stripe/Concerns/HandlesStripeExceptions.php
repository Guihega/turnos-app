<?php

declare(strict_types=1);

namespace App\Billing\Stripe\Concerns;

use App\Billing\Exceptions\GatewayAuthenticationException;
use App\Billing\Exceptions\GatewayException;
use App\Billing\Exceptions\GatewayNotFoundException;
use App\Billing\Exceptions\GatewaySignatureException;
use Closure;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Throwable;

/**
 * Wraps a closure so that Stripe SDK exceptions are translated to the
 * App\Billing\Exceptions\* hierarchy. See ADR-015 §4.
 *
 * The mapping is intentionally narrow: only the failure modes we
 * already model get a specific subclass. Everything else falls through
 * to the base GatewayException, preserving the original via $previous.
 */
trait HandlesStripeExceptions
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     *
     * @throws GatewayNotFoundException
     * @throws GatewayAuthenticationException
     * @throws GatewaySignatureException
     * @throws GatewayException
     */
    private function translateStripeExceptions(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (SignatureVerificationException $e) {
            throw new GatewaySignatureException(
                'Webhook signature verification failed.',
                previous: $e,
            );
        } catch (AuthenticationException $e) {
            throw new GatewayAuthenticationException(
                'Stripe rejected our credentials.',
                previous: $e,
            );
        } catch (InvalidRequestException $e) {
            // Stripe uses 'resource_missing' as the canonical "not found" code.
            if ($e->getStripeCode() === 'resource_missing') {
                throw new GatewayNotFoundException(
                    $e->getMessage(),
                    previous: $e,
                );
            }
            throw new GatewayException(
                'Invalid request to Stripe: '.$e->getMessage(),
                previous: $e,
            );
        } catch (ApiErrorException $e) {
            // All other Stripe API errors (ApiConnection, RateLimit, etc.)
            throw new GatewayException(
                'Stripe API error: '.$e->getMessage(),
                previous: $e,
            );
        } catch (Throwable $e) {
            // Defensive: anything not derived from ApiErrorException is
            // a programming error or an SDK contract violation. Wrap it
            // so callers have a single hierarchy to catch.
            throw new GatewayException(
                'Unexpected error talking to Stripe: '.$e->getMessage(),
                previous: $e,
            );
        }
    }
}
