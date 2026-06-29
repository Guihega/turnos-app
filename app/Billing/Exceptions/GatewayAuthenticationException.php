<?php

declare(strict_types=1);

namespace App\Billing\Exceptions;

/**
 * The gateway rejected our credentials.
 *
 * For Stripe this typically means the API key is invalid, revoked, or
 * was sent for the wrong mode (test key against live endpoints, or
 * vice-versa). See docs/billing/SECRETS.md for rotation procedure.
 */
final class GatewayAuthenticationException extends GatewayException {}
