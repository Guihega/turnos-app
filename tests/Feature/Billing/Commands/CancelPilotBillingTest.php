<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Commands;

use App\Actions\Billing\OnboardPilotAction;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Billing\Entitlement;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Behavior tests for the billing:cancel-pilot command (PR-S4).
 *
 * The command cancels pilot billing instead of hard-deleting it (see ADR-011:
 * the state-transitions trigger forbids physical deletes, so the canonical
 * pivot is to transition Pilot -> Canceled and revoke entitlements). These
 * tests assert the new semantics: Customer and Subscription survive, the
 * subscription status moves to Canceled, entitlements are physically removed,
 * and a canceled tenant is NOT re-onboardable by the backfill.
 */
final class CancelPilotBillingTest extends TestCase
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

    private function onboardedTenant(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        app(OnboardPilotAction::class)->execute($tenant);

        return $tenant;
    }

    #[Test]
    public function it_cancels_a_single_tenant(): void
    {
        $this->seedPilotPlan();
        $tenant = $this->onboardedTenant();

        $this->artisan("billing:cancel-pilot --tenant={$tenant->slug}")
            ->assertExitCode(0);

        // Customer and subscription survive; status is Canceled; entitlements gone.
        $this->assertDatabaseHas('billing_customers', ['tenant_id' => $tenant->id]);
        $customer = Customer::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $subscription = Subscription::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertSame(0, Entitlement::query()->where('subscription_id', $subscription->id)->count());
    }

    #[Test]
    public function a_canceled_tenant_is_not_re_onboarded_by_backfill(): void
    {
        $this->seedPilotPlan();
        $tenant = $this->onboardedTenant();

        $this->artisan("billing:cancel-pilot --tenant={$tenant->slug}")->assertExitCode(0);

        // The tenant still has a customer, so the backfill (whereDoesntHave('customer'))
        // does NOT pick it up. Re-enabling is a separate, deliberate operation.
        $this->artisan('billing:backfill-existing-tenants')->assertExitCode(0);

        $customer = Customer::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $subscription = Subscription::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertSame(1, Subscription::query()->where('customer_id', $customer->id)->count());
    }

    #[Test]
    public function all_mode_cancels_pilot_tenants(): void
    {
        $this->seedPilotPlan();
        $this->onboardedTenant();
        $this->onboardedTenant();

        $this->artisan('billing:cancel-pilot --all')->assertExitCode(0);

        $this->assertSame(
            2,
            Subscription::query()->where('status', SubscriptionStatus::Canceled->value)->count(),
        );
        $this->assertDatabaseCount('billing_entitlements', 0);
    }

    #[Test]
    public function all_mode_never_touches_paid_customers(): void
    {
        $this->seedPilotPlan();
        $pilot = $this->onboardedTenant();

        /** @var Tenant $paidTenant */
        $paidTenant = Tenant::factory()->create();
        /** @var Customer $paidCustomer */
        $paidCustomer = Customer::factory()->create([
            'tenant_id' => $paidTenant->id,
            'metadata' => null,
        ]);
        CustomerGatewayRef::factory()->create(['customer_id' => $paidCustomer->id]);

        $this->artisan('billing:cancel-pilot --all')->assertExitCode(0);

        // Pilot tenant canceled, paid customer untouched.
        $pilotCustomer = Customer::query()->where('tenant_id', $pilot->id)->firstOrFail();
        $pilotSub = Subscription::query()->where('customer_id', $pilotCustomer->id)->firstOrFail();
        $this->assertSame(SubscriptionStatus::Canceled, $pilotSub->status);
        // Paid customer's row still there, untouched.
        $this->assertDatabaseHas('billing_customers', ['id' => $paidCustomer->id]);
    }

    #[Test]
    public function dry_run_changes_nothing(): void
    {
        $this->seedPilotPlan();
        $tenant = $this->onboardedTenant();

        $this->artisan("billing:cancel-pilot --tenant={$tenant->slug} --dry-run")
            ->assertExitCode(0);

        $customer = Customer::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $subscription = Subscription::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(SubscriptionStatus::Pilot, $subscription->status);
        $this->assertSame(1, Entitlement::query()->where('subscription_id', $subscription->id)->count());
    }

    #[Test]
    public function canceling_an_already_canceled_tenant_is_idempotent(): void
    {
        $this->seedPilotPlan();
        $tenant = $this->onboardedTenant();

        $this->artisan("billing:cancel-pilot --tenant={$tenant->slug}")->assertExitCode(0);
        // Second run: TransitionSubscriptionAction no-ops on same-state, the
        // entitlements delete is also a no-op (already 0). Exit clean.
        $this->artisan("billing:cancel-pilot --tenant={$tenant->slug}")->assertExitCode(0);

        $customer = Customer::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $subscription = Subscription::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
    }

    #[Test]
    public function it_requires_a_targeting_mode(): void
    {
        $this->artisan('billing:cancel-pilot')->assertExitCode(1);
    }

    #[Test]
    public function tenant_and_all_are_mutually_exclusive(): void
    {
        $this->artisan('billing:cancel-pilot --tenant=foo --all')->assertExitCode(1);
    }

    #[Test]
    public function an_unknown_tenant_fails(): void
    {
        $this->artisan('billing:cancel-pilot --tenant=does-not-exist')->assertExitCode(1);
    }
}
