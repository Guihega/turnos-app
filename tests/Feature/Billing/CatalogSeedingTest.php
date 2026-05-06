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

        // pilot: 1 interval × 6 currencies = 6 rows
        $pilot = Plan::query()->where('code', 'pilot')->firstOrFail();
        $this->assertSame(6, $pilot->prices()->count());

        // each non-pilot public plan: 2 intervals × 6 currencies = 12 rows
        foreach (['starter', 'professional', 'business'] as $code) {
            $plan = Plan::query()->where('code', $code)->firstOrFail();
            $this->assertSame(
                12,
                $plan->prices()->count(),
                "plan {$code} should have 12 prices"
            );
        }

        // enterprise: 0 prices (custom, not public)
        $enterprise = Plan::query()->where('code', 'enterprise')->firstOrFail();
        $this->assertSame(0, $enterprise->prices()->count());
    }
}
