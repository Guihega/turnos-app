<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Feature types supported in the entitlements catalog.
 *
 *  - Boolean: on/off feature (whitelabel.full, api.access).
 *  - Quota:   numeric limit (branches.max, tickets.monthly).
 *             Value -1 means unlimited.
 *  - Metered: numeric, billed per unit consumed (branches.metered).
 *             Tracked in billing_usage_records.
 *  - StringValue: arbitrary string (support.tier = "priority").
 */
enum FeatureType: string
{
    case Boolean = 'boolean';
    case Quota = 'quota';
    case Metered = 'metered';
    case StringValue = 'string';

    public function label(): string
    {
        return match ($this) {
            self::Boolean => 'Booleano',
            self::Quota => 'Cuota numérica',
            self::Metered => 'Cobrable por uso',
            self::StringValue => 'Texto',
        };
    }

    /**
     * Whether this type stores its value in value_numeric column.
     */
    public function isNumeric(): bool
    {
        return $this === self::Quota || $this === self::Metered;
    }
}
