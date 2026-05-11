<?php

declare(strict_types=1);

namespace App\Billing\Exceptions;

/**
 * Thrown when the gateway rejected the request payload as invalid
 * (missing required field, wrong shape, unsupported value).
 *
 * For Stripe this maps from \Stripe\Exception\InvalidRequestException
 * when the error is NOT a resource_missing (those map to
 * GatewayNotFoundException). See HandlesStripeExceptions trait.
 *
 * This exception is non-retryable: the caller's payload is wrong,
 * retrying with the same payload will fail again. Surfacing this to
 * the HTTP layer typically results in a 422.
 */
final class GatewayValidationException extends GatewayException {}
