<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use RuntimeException;

/**
 * Thrown when CreateSubscriptionAction is asked to create a subscription
 * for a Customer that has no CustomerGatewayRef for the target gateway.
 *
 * Causes:
 *   - The customer was created out-of-band (legacy import, manual fix)
 *     and never had a Stripe customer created for it.
 *   - The gateway ref row was hard-deleted accidentally.
 *
 * Resolution: invoke CreateCustomerAction first, then retry.
 */
final class CustomerNotRegisteredInGatewayException extends RuntimeException {}
