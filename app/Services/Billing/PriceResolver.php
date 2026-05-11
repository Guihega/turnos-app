<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingInterval;
use App\Exceptions\Billing\PriceNotFoundException;
use App\Models\Billing\Customer;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;

/**
 * Resolves a (Plan, Customer, BillingInterval) tuple to a concrete
 * Price row.
 *
 * Per ADR-016, the HTTP endpoint takes plan_id and an interval choice.
 * Currency is derived from $customer->default_currency. Country
 * preference is $customer->country, falling back to country=NULL
 * (currency-wide price).
 *
 * If no matching price exists, throws PriceNotFoundException — the
 * tenant has selected a plan that isn't offered in their currency/
 * interval combo.
 */
final class PriceResolver
{
    /**
     * @throws PriceNotFoundException
     */
    public function resolve(Plan $plan, Customer $customer, BillingInterval $interval): Price
    {
        $price = Price::query()
            ->where('plan_id', $plan->id)
            ->where('currency', $customer->default_currency)
            ->where('interval', $interval->value)
            ->where('is_active', true)
            ->where(function ($q) use ($customer) {
                // Prefer country-specific row; fall back to country=NULL.
                $q->where('country', $customer->country)
                    ->orWhereNull('country');
            })
            // Country-specific (NOT NULL) wins over the NULL fallback.
            ->orderByRaw('CASE WHEN country IS NULL THEN 1 ELSE 0 END')
            ->orderBy('interval_count')
            ->first();

        if ($price === null) {
            throw new PriceNotFoundException(sprintf(
                'No active Price for plan=%s currency=%s interval=%s country=%s',
                $plan->code,
                $customer->default_currency,
                $interval->value,
                $customer->country,
            ));
        }

        return $price;
    }
}
