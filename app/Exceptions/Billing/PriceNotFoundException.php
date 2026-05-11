<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use RuntimeException;

/**
 * Thrown when PriceResolver cannot find an active Price matching the
 * requested Plan + currency + interval combination.
 *
 * Surfaces as HTTP 422 to the API caller: the catalog does not offer
 * this plan in the tenant's currency/interval, so the request is
 * unfulfillable as-is.
 */
final class PriceNotFoundException extends RuntimeException {}
