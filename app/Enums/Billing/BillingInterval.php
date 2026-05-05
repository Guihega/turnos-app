<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Billing periods supported.
 *
 * Stored in billing_prices.interval. Combined with interval_count
 * (e.g. interval=month, interval_count=3 = quarterly).
 */
enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Month => 'Mensual',
            self::Year => 'Anual',
        };
    }

    /**
     * Number of days in this interval (approximate, for prorate calcs).
     * Use Carbon for actual date arithmetic; this is for estimates only.
     */
    public function approximateDays(): int
    {
        return match ($this) {
            self::Month => 30,
            self::Year => 365,
        };
    }
}
