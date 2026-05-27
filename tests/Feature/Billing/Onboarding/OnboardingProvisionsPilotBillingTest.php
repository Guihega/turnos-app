<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Onboarding;

use App\Models\Billing\Customer;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the onboarding hook (PR-S3): a tenant created via the
 * OnboardingController wizard is provisioned with pilot billing inline.
 *
 * The hook is best-effort: a billing failure must not abort onboarding (the
 * backfill command is the safety net). These tests assert both the happy path
 * (plan seeded -> tenant born with Customer + pilot Subscription) and the
 * degraded path (plan absent -> tenant still created, no exception bubbles).
 */
final class OnboardingProvisionsPilotBillingTest extends TestCase
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

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'María García',
            'email' => 'maria@nueva-empresa.com',
            'password' => 'Olinora2026!',
            'password_confirmation' => 'Olinora2026!',
            'company_name' => 'Clínica Santa Fe',
            'slug' => 'clinica-santa-fe',
            'branch_name' => 'Sucursal Centro',
            'branch_code' => 'CTR',
        ], $overrides);
    }

    #[Test]
    public function onboarding_provisions_pilot_billing_for_the_new_tenant(): void
    {
        Event::fake([Registered::class]);
        $this->seedPilotPlan();

        $this->post('/onboarding', $this->validData())
            ->assertRedirect(route('dashboard'));

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->where('slug', 'clinica-santa-fe')->firstOrFail();

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
    public function onboarding_succeeds_even_when_billing_provisioning_fails(): void
    {
        Event::fake([Registered::class]);
        // No pilot plan seeded: the pilot Subscription step fails (no plan to
        // resolve). The hook must swallow it and let onboarding complete. The
        // partial Customer row is expected — OnboardPilotAction has no outer
        // transaction by design, and the state is recoverable on re-run via
        // the backfill command (both sub-actions are idempotent).

        $this->post('/onboarding', $this->validData())
            ->assertRedirect(route('dashboard'));

        // Onboarding completed: the tenant exists and the user is authenticated.
        $this->assertDatabaseHas('tenants', ['slug' => 'clinica-santa-fe']);
        $this->assertAuthenticated();

        // Billing is incomplete: no pilot Subscription was provisioned.
        $this->assertDatabaseCount('billing_subscriptions', 0);
    }
}
