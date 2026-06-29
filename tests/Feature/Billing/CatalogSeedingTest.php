<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Price;
use Database\Seeders\Billing\BillingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_expected_catalog_counts(): void
    {
        $this->seed(BillingCatalogSeeder::class);

        $this->assertSame(10, Feature::query()->count(), 'features');
        $this->assertSame(5, Plan::query()->count(), 'plans');
        $this->assertSame(30, PlanFeature::query()->count(), 'plan_features');
        $this->assertSame(42, Price::query()->count(), 'prices');
    }

    public function test_it_is_idempotent_on_re_run(): void
    {
        $this->seed(BillingCatalogSeeder::class);
        $this->seed(BillingCatalogSeeder::class);

        $this->assertSame(10, Feature::query()->count());
        $this->assertSame(5, Plan::query()->count());
        $this->assertSame(30, PlanFeature::query()->count());
        $this->assertSame(42, Price::query()->count());
    }

    public function test_it_seeds_known_plan_codes(): void
    {
        $this->seed(BillingCatalogSeeder::class);

        $codes = Plan::query()->orderBy('sort_order')->pluck('code')->all();

        $this->assertSame(
            ['pilot', 'starter', 'professional', 'business', 'enterprise'],
            $codes
        );
    }

    public function test_it_seeds_prices_for_all_six_currencies_on_public_plans(): void
    {
        $this->seed(BillingCatalogSeeder::class);

        $pilot = Plan::query()->where('code', 'pilot')->firstOrFail();
        $this->assertSame(6, $pilot->prices()->count());

        foreach (['starter', 'professional', 'business'] as $code) {
            $plan = Plan::query()->where('code', $code)->firstOrFail();
            $this->assertSame(
                12,
                $plan->prices()->count(),
                "plan {$code} should have 12 prices"
            );
        }

        $enterprise = Plan::query()->where('code', 'enterprise')->firstOrFail();
        $this->assertSame(0, $enterprise->prices()->count());
    }

    /**
     * Regression guard: zero-decimal currencies (ARS/CLP/COP) must NOT be
     * multiplied by 100 into amount_cents. Two-decimal currencies (USD/MXN/PEN)
     * must be. This is the bug fixed on 2026-06-29 where the seeder blindly
     * multiplied every currency by 100, inflating zero-decimal amounts 100x.
     *
     * Starter monthly major amounts (see PricesSeeder::MONTHLY_PRICES):
     *   USD 29, MXN 499, PEN 109   (2 decimals -> x100)
     *   COP 129000, ARS 29900, CLP 27990  (0 decimals -> x1)
     */
    public function test_zero_decimal_currencies_are_not_inflated(): void
    {
        $this->seed(BillingCatalogSeeder::class);

        $starter = Plan::query()->where('code', 'starter')->firstOrFail();

        $monthly = fn (string $currency): int => (int) $starter->prices()
            ->where('currency', $currency)
            ->where('interval', 'month')
            ->value('amount_cents');

        // Two-decimal currencies: major x 100.
        $this->assertSame(2900, $monthly('USD'), 'USD starter monthly');
        $this->assertSame(49900, $monthly('MXN'), 'MXN starter monthly');
        $this->assertSame(10900, $monthly('PEN'), 'PEN starter monthly');

        // Zero-decimal currencies: major x 1 (NOT inflated).
        $this->assertSame(129000, $monthly('COP'), 'COP starter monthly');
        $this->assertSame(29900, $monthly('ARS'), 'ARS starter monthly');
        $this->assertSame(27990, $monthly('CLP'), 'CLP starter monthly');
    }

    /**
     * Regression guard: no price may exceed Stripe's maximum unit_amount
     * (99,999,999). This is what caused the 2 failures on first sync —
     * Business yearly in ARS/CLP exceeded the cap before the fix.
     */
    public function test_no_price_exceeds_stripe_max_unit_amount(): void
    {
        $this->seed(BillingCatalogSeeder::class);

        $maxAmount = Price::query()->max('amount_cents');

        $this->assertLessThanOrEqual(
            99999999,
            $maxAmount,
            'No price may exceed Stripe max unit_amount (99,999,999)'
        );
    }
}
