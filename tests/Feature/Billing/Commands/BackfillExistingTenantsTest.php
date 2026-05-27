<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Commands;

use App\Models\Billing\Customer;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Behavior tests for the billing:backfill-existing-tenants command (PR-S3).
 *
 * Exercises the command's orchestration concerns: tenant selection via
 * whereDoesntHave('customer'), the --dry-run no-write path, idempotent
 * exclusion of already-billed tenants, the empty-set path, and exit codes.
 * The end-to-end effect of OnboardPilotAction (entitlement materialization,
 * etc.) is covered by OnboardPilotActionTest and not re-asserted here.
 */
final class BackfillExistingTenantsTest extends TestCase
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
    public function it_backfills_a_tenant_that_has_no_billing(): void
    {
        $this->seedPilotPlan();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $this->artisan('billing:backfill-existing-tenants')
            ->assertExitCode(0);

        $this->assertDatabaseHas('billing_customers', [
            'tenant_id' => $tenant->id,
        ]);
        $this->assertSame(
            1,
            Subscription::query()
                ->whereIn('customer_id', Customer::query()->where('tenant_id', $tenant->id)->pluck('id'))
                ->count(),
        );
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $this->seedPilotPlan();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $this->artisan('billing:backfill-existing-tenants --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseCount('billing_customers', 0);
        $this->assertDatabaseCount('billing_subscriptions', 0);
    }

    #[Test]
    public function it_skips_tenants_that_already_have_billing(): void
    {
        $this->seedPilotPlan();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        // First run provisions billing.
        $this->artisan('billing:backfill-existing-tenants')->assertExitCode(0);

        // Second run must not duplicate: the tenant is now excluded.
        $this->artisan('billing:backfill-existing-tenants')->assertExitCode(0);

        $this->assertSame(1, Customer::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(
            1,
            Subscription::query()
                ->whereIn('customer_id', Customer::query()->where('tenant_id', $tenant->id)->pluck('id'))
                ->count(),
        );
    }

    #[Test]
    public function it_reports_when_no_tenants_are_pending(): void
    {
        $this->seedPilotPlan();

        $this->artisan('billing:backfill-existing-tenants')
            ->expectsOutputToContain('No tenants pending backfill')
            ->assertExitCode(0);
    }
}
