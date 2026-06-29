<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use RuntimeException;

/**
 * Thrown when a resolved Price has no gateway_refs entry for the target
 * gateway (e.g. ['stripe' => 'price_xxx'] is missing).
 *
 * Causes:
 *   - The Price was seeded but the Stripe-side product/price was never
 *     created (incomplete admin setup).
 *   - The catalog was migrated from another gateway and Stripe IDs
 *     haven't been populated.
 *
 * Resolution: admin must populate gateway_refs.stripe via the catalog
 * management UI / seeder before the price can be sold via Stripe.
 */
final class PriceMissingGatewayMappingException extends RuntimeException {}
