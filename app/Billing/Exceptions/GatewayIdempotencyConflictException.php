<?php

declare(strict_types=1);

namespace App\Billing\Exceptions;

/**
 * Thrown when the gateway reports that an idempotency key was reused
 * with a DIFFERENT request payload than the original call associated
 * with that key.
 *
 * This indicates a bug in our Action layer: idempotency keys must be
 * deterministic for a given logical operation. If this exception ever
 * fires in production, it means we generated the same key for two
 * semantically different requests.
 *
 * Stripe surfaces this as \Stripe\Exception\IdempotencyException.
 *
 * Non-retryable: the caller cannot recover by retrying the same key.
 * Logged at error level for operator investigation.
 */
final class GatewayIdempotencyConflictException extends GatewayException {}
