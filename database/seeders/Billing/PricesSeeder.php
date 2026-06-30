<?php

declare(strict_types=1);

namespace Database\Seeders\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\Price;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds prices across the public paid plans x 6 currencies x 1-2 intervals.
 *
 * Amounts are stored in Stripe's smallest unit. For 2-decimal currencies
 * (USD/MXN/PEN) that means cents (major x 100). For zero-decimal currencies
 * (COP/CLP/ARS) the smallest unit IS the major unit (major x 1) -- Stripe
 * rejects sub-unit amounts for them. The multiplier is derived from
 * config('billing.currency_decimals'), the single source of truth.
 *
 * Idempotent via firstOrNew on the unique combination. Re-seeding updates
 * the amount/tax/active flags but PRESERVES gateway_refs: once a price is
 * linked to Stripe, a re-seed must not wipe that link (it would unlink the
 * whole catalog from the gateway and break checkout). gateway_refs is only
 * initialized to null when the row is brand new.
 *
 * @see config/billing.php (currency_decimals)
 * @see docs/billing/SPEC.md
 */
final class PricesSeeder extends Seeder
{
    /**
     * Monthly prices per (plan, currency) in MAJOR units.
     * Yearly is derived: monthly x 10.
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
        $price = Price::firstOrNew([
            'plan_id' => $plan->id,
            'currency' => $currency,
            'country' => null,
            'interval' => $interval->value,
            'interval_count' => 1,
        ]);

        $price->amount_cents = $amountMajor * $this->minorUnitFactor($currency);
        $price->tax_behavior = 'exclusive';
        $price->is_active = true;

        // Preserve gateway_refs: never overwrite an existing Stripe link on
        // re-seed. Only initialize to null when the row is brand new.
        if (! $price->exists) {
            $price->gateway_refs = null;
        }

        $price->save();
    }

    /**
     * Multiplier to convert a MAJOR amount into Stripe's smallest unit,
     * derived from the currency's decimal count. 2 decimals -> 100,
     * 0 decimals -> 1. Falls back to 2 decimals if a currency is missing
     * from the config map (conservative default).
     */
    private function minorUnitFactor(string $currency): int
    {
        $decimals = config('billing.currency_decimals.'.$currency, 2);

        return 10 ** $decimals;
    }
}
