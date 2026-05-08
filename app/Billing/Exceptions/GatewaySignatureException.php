<?php

declare(strict_types=1);

namespace App\Billing\Exceptions;

/**
 * Webhook signature verification failed.
 *
 * Per ADR-012, signature verification happens BEFORE persisting the
 * webhook event in billing_webhook_events. Therefore this exception
 * means: drop the request, do not store, return 400 to the gateway.
 *
 * Re-thrown by adapters when their SDK signals a signature mismatch:
 * - Stripe: \Stripe\Exception\SignatureVerificationException → this.
 */
final class GatewaySignatureException extends GatewayException {}
