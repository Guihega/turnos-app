<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\FeatureType;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Structural tests for how plan-feature values are persisted by type.
 *
 * Runtime entitlement resolution (Entitlement::for($tenant)->has(...))
 * is out of scope here — that lands in Phase 2.
 */
final class EntitlementsResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_boolean_feature_stores_in_value_boolean(): void
    {
        $plan = Plan::factory()->create();
        $feature = Feature::factory()->boolean()->create();

        $pf = PlanFeature::factory()
            ->forBoolean(true)
            ->create([
                'plan_id' => $plan->id,
                'feature_id' => $feature->id,
            ]);

        $this->assertSame(FeatureType::Boolean, $feature->type);
        $this->assertTrue($pf->value_boolean);
        $this->assertNull($pf->value_numeric);
        $this->assertNull($pf->value_string);
    }

    public function test_quota_feature_stores_in_value_numeric(): void
    {
        $plan = Plan::factory()->create();
        $feature = Feature::factory()->quota()->create();

        $pf = PlanFeature::factory()
            ->forQuota(5000)
            ->create([
                'plan_id' => $plan->id,
                'feature_id' => $feature->id,
            ]);

        $this->assertSame(FeatureType::Quota, $feature->type);
        $this->assertSame(5000, $pf->value_numeric);
        $this->assertNull($pf->value_boolean);
        $this->assertNull($pf->value_string);
        $this->assertFalse($pf->isUnlimited());
    }

    public function test_string_feature_stores_in_value_string(): void
    {
        $plan = Plan::factory()->create();
        $feature = Feature::factory()->stringValue()->create();

        $pf = PlanFeature::factory()
            ->forString('priority')
            ->create([
                'plan_id' => $plan->id,
                'feature_id' => $feature->id,
            ]);

        $this->assertSame(FeatureType::StringValue, $feature->type);
        $this->assertSame('priority', $pf->value_string);
        $this->assertNull($pf->value_numeric);
        $this->assertNull($pf->value_boolean);
    }
}
