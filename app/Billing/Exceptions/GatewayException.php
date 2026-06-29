<?php

declare(strict_types=1);

namespace App\Billing\Exceptions;

use RuntimeException;

/**
 * Base for all exceptions raised by a BillingGateway implementation.
 *
 * Catch this when you want to handle "the gateway failed for any reason".
 * Catch a more specific subclass (NotFound, Authentication, Signature)
 * when the failure mode matters.
 *
 * SDK-specific exceptions (e.g. \Stripe\Exception\*) MUST be caught at
 * the adapter boundary and re-thrown as one of these. See ADR-015 §4.
 */
class GatewayException extends RuntimeException {}
