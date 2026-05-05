<?php

declare(strict_types=1);

namespace Database\Seeders\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds 42 prices across 4 public plans × 6 currencies × 1-2 intervals.
 *
 *   pilot:        6 rows  (1 interval, all amounts = 0)
 *   starter:     12 rows  (month + year)
 *   professional:12 rows  (month + year)
 *   business:    12 rows  (month + year)
 *   enterprise:   0 rows  (custom, no public price)
 *
 * Yearly amount = monthly amount × 10  (≈17% effective discount).
 *
 * Amounts are stored in CENTS (smallest currency unit). For zero-decimal
 * currencies (COP, ARS, CLP) we still multiply by 100 for table-wide
 * consistency; the application layer is the single source of truth on
 * how to format each currency.
 *
 * Idempotent via Price::updateOrCreate on the unique combination
 * (plan_id, currency, country, interval, interval_count).
 *
 * @see docs/billing/SPEC.md §5
 */
final class PricesSeeder extends Seeder
{
    /**
     * Monthly prices per (plan, currency) in MAJOR units (USD/MXN/etc).
     * Yearly is derived: monthly × 10. Cents conversion happens at write time.
     *
     * @var array<string, array<string, int>>
     */
    private const MONTHLY_PRICES = [
        'pilot' => [
            'USD' => 0,
            'MXN' => 0,
            'COP' => 0,
            'ARS' => 0,
            'CLP' => 0,
            'PEN' => 0,
        ],
        'starter' => [
            'USD' => 29,
            'MXN' => 499,
            'COP' => 129000,
            'ARS' => 29900,
            'CLP' => 27990,
            'PEN' => 109,
        ],
        'professional' => [
            'USD' => 79,
            'MXN' => 1399,
            'COP' => 349000,
            'ARS' => 79900,
            'CLP' => 74990,
            'PEN' => 299,
        ],
        'business' => [
            'USD' => 199,
            'MXN' => 3499,
            'COP' => 879000,
            'ARS' => 199000,
            'CLP' => 189990,
            'PEN' => 749,
        ],
    ];

    private const YEARLY_RATIO = 10;

    public function run(): void
    {
        $plans = Plan::query()
            ->whereIn('code', array_keys(self::MONTHLY_PRICES))
            ->get()
            ->keyBy('code');

        foreach (self::MONTHLY_PRICES as $planCode => $byCurrency) {
            $plan = $plans->get($planCode);

            if ($plan === null) {
                throw new RuntimeException(
                    "Plan '{$planCode}' not found. Did the plans seed run?"
                );
            }

            foreach ($byCurrency as $currency => $monthlyMajor) {
                $this->upsertPrice(
                    plan: $plan,
                    currency: $currency,
                    interval: BillingInterval::Month,
                    amountMajor: $monthlyMajor,
                );

                // Pilot has no yearly price (it's a 90-day trial, not a subscription).
                if ($planCode === 'pilot') {
                    continue;
                }

                $this->upsertPrice(
                    plan: $plan,
                    currency: $currency,
                    interval: BillingInterval::Year,
                    amountMajor: $monthlyMajor * self::YEARLY_RATIO,
                );
            }
        }
    }

    private function upsertPrice(
        Plan $plan,
        string $currency,
        BillingInterval $interval,
        int $amountMajor,
    ): void {
        Price::updateOrCreate(
            [
                'plan_id' => $plan->id,
                'currency' => $currency,
                'country' => null,
                'interval' => $interval->value,
                'interval_count' => 1,
            ],
            [
                'amount_cents' => $amountMajor * 100,
                'tax_behavior' => 'exclusive',
                'gateway_refs' => null,
                'is_active' => true,
            ]
        );
    }
}
