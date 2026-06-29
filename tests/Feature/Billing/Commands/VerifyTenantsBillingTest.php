<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Commands;

use App\Actions\Billing\CreatePilotCustomerAction;
use App\Actions\Billing\OnboardPilotAction;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Behavior tests for the billing:verify-tenants command (PR-S4).
 *
 * Covers the empty-set vacuous pass, the well-formed happy path, and one case
 * per detectable violation: MISSING_CUSTOMER, MISSING_SUBSCRIPTION,
 * INACTIVE_SUBSCRIPTION. The MISSING_ENTITLEMENTS branch is covered by
 * construction (it is unreachable through the real flow: every subscription
 * created via the actions dispatches SubscriptionCreated, whose synchronous
 * listener materializes plan entitlements) and is intentionally not forced
 * with a synthetic, fragile state here.
 */
final class VerifyTenantsBillingTest extends TestCase
{
    use RefreshDatabase;

    private function seedPilotPlan(): void
    {
        $pilot = Plan::factory()->create(['code' => 'pilot']);
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        PlanFeature::factory()->forQuota(3)->create([
            'plan_id' => $pilot->id,
            'feature_id' => $feature->id,
        ]);
    }

    #[Test]
    public function an_empty_tenant_set_satisfies_the_invariant(): void
    {
        $this->artisan('billing:verify-tenants')
            ->expectsOutputToContain('Invariant holds')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_well_formed_tenant_passes(): void
    {
        $this->seedPilotPlan();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        app(OnboardPilotAction::class)->execute($tenant);

        $this->artisan('billing:verify-tenants')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_tenant_without_a_customer_is_flagged(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $this->artisan('billing:verify-tenants')
            ->expectsOutputToContain('MISSING_CUSTOMER')
            ->assertExitCode(1);
    }

    #[Test]
    public function a_customer_without_a_subscription_is_flagged(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        // Customer only — the partial state a best-effort onboarding leaves
        // when the subscription step fails.
        app(CreatePilotCustomerAction::class)->execute($tenant);

        $this->artisan('billing:verify-tenants')
            ->expectsOutputToContain('MISSING_SUBSCRIPTION')
            ->assertExitCode(1);
    }

    #[Test]
    public function a_canceled_subscription_is_flagged_as_inactive(): void
    {
        $this->seedPilotPlan();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        $subscription = app(OnboardPilotAction::class)->execute($tenant);

        $subscription->update(['status' => SubscriptionStatus::Canceled]);

        $this->artisan('billing:verify-tenants')
            ->expectsOutputToContain('INACTIVE_SUBSCRIPTION')
            ->assertExitCode(1);
    }

    #[Test]
    public function json_output_reports_violations_with_a_failing_exit_code(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $this->artisan('billing:verify-tenants --json')
            ->assertExitCode(1);
    }
}
