<?php

declare(strict_types=1);

namespace App\Billing\Exceptions;

/**
 * The requested resource (customer, subscription, invoice, etc.) does
 * not exist in the gateway, or has been deleted.
 *
 * Adapters map their SDK's "not found" condition to this:
 * - Stripe: \Stripe\Exception\InvalidRequestException with stripe code
 *   'resource_missing' → GatewayNotFoundException.
 */
final class GatewayNotFoundException extends GatewayException {}
