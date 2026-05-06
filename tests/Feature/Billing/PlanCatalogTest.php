<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Price;
use Database\Seeders\Billing\BillingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class PlanCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingCatalogSeeder::class);
    }

    public function test_public_scope_filters_out_enterprise(): void
    {
        $codes = Plan::query()->public()->pluck('code')->all();

        $this->assertContains('pilot', $codes);
        $this->assertContains('starter', $codes);
        $this->assertContains('professional', $codes);
        $this->assertContains('business', $codes);
        $this->assertNotContains('enterprise', $codes);
    }

    public function test_active_scope_returns_all_five_plans(): void
    {
        $this->assertSame(5, Plan::query()->active()->count());
    }

    public function test_it_loads_plan_features_with_typed_values(): void
    {
        $professional = Plan::query()
            ->where('code', 'professional')
            ->with('planFeatures.feature')
            ->firstOrFail();

        /** @var Collection<int, PlanFeature> $planFeatures */
        $planFeatures = $professional->planFeatures;

        // professional has 9 features per the seed matrix (no trial.days).
        $this->assertCount(9, $planFeatures);

        $branchesMax = $this->planFeatureFor($planFeatures, 'branches.max');
        $this->assertSame(10, $branchesMax->value_numeric);

        $whitelabel = $this->planFeatureFor($planFeatures, 'whitelabel.full');
        $this->assertTrue($whitelabel->value_boolean);

        $supportTier = $this->planFeatureFor($planFeatures, 'support.tier');
        $this->assertSame('email', $supportTier->value_string);
    }

    public function test_starter_has_both_monthly_and_yearly_prices(): void
    {
        $starter = Plan::query()->where('code', 'starter')->firstOrFail();

        /** @var Price $monthly */
        $monthly = $starter->prices()
            ->where('interval', BillingInterval::Month->value)
            ->where('currency', 'USD')
            ->firstOrFail();

        /** @var Price $yearly */
        $yearly = $starter->prices()
            ->where('interval', BillingInterval::Year->value)
            ->where('currency', 'USD')
            ->firstOrFail();

        $this->assertSame(2900, $monthly->amount_cents); // $29.00 USD
        $this->assertSame(29000, $yearly->amount_cents); // $290.00 USD = 10x
        $this->assertSame(10 * $monthly->amount_cents, $yearly->amount_cents);
    }

    public function test_unlimited_quota_convention_works(): void
    {
        $business = Plan::query()
            ->where('code', 'business')
            ->with('planFeatures.feature')
            ->firstOrFail();

        /** @var Collection<int, PlanFeature> $planFeatures */
        $planFeatures = $business->planFeatures;

        $operatorsMax = $this->planFeatureFor($planFeatures, 'operators.max');

        $this->assertSame(-1, $operatorsMax->value_numeric);
        $this->assertTrue($operatorsMax->isUnlimited());
    }

    /**
     * Helper: locate the PlanFeature row for a given feature code, or fail
     * the test if it's missing. Centralizes the type narrowing so each
     * assertion stays readable.
     *
     * @param  Collection<int, PlanFeature>  $planFeatures
     */
    private function planFeatureFor(Collection $planFeatures, string $featureCode): PlanFeature
    {
        $found = $planFeatures->first(
            function (PlanFeature $pf) use ($featureCode): bool {
                $feature = $pf->feature;
                $this->assertInstanceOf(Feature::class, $feature);

                return $feature->code === $featureCode;
            }
        );

        $this->assertInstanceOf(
            PlanFeature::class,
            $found,
            "Expected to find a PlanFeature for code '{$featureCode}'."
        );

        return $found;
    }
}
