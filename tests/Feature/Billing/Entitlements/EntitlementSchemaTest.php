<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Entitlements;

use App\Models\Billing\Entitlement;
use App\Models\Billing\EntitlementGrant;
use App\Models\Billing\Feature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Schema-level tests for billing_entitlements and billing_entitlement_grants.
 *
 * Verifies persistence, FK constraints, the active scope on grants, and
 * the eloquent relations on Subscription and Tenant. The EntitlementService
 * (PR-R) will build on top of this schema; behavior tests live there.
 */
final class EntitlementSchemaTest extends TestCase
{
    use RefreshDatabase;

    // ─── Entitlement: defaults & persistence ────────────────────────

    #[Test]
    public function it_creates_an_entitlement_with_default_source_plan(): void
    {
        $entitlement = Entitlement::factory()->create();

        $this->assertSame(Entitlement::SOURCE_PLAN, $entitlement->source);
        $this->assertDatabaseHas('billing_entitlements', [
            'id' => $entitlement->id,
            'source' => 'plan',
        ]);
    }

    #[Test]
    public function it_persists_a_grant_sourced_entitlement(): void
    {
        $entitlement = Entitlement::factory()->grant()->create();

        $this->assertSame(Entitlement::SOURCE_GRANT, $entitlement->source);
    }

    #[Test]
    public function it_persists_an_unlimited_quota(): void
    {
        $entitlement = Entitlement::factory()->unlimited()->create();

        $this->assertSame(-1, $entitlement->value_numeric);
    }

    // ─── Entitlement: constraints ───────────────────────────────────

    #[Test]
    public function it_enforces_unique_subscription_feature_pair(): void
    {
        $first = Entitlement::factory()->create();

        $this->expectException(QueryException::class);

        Entitlement::factory()->create([
            'subscription_id' => $first->subscription_id,
            'feature_id' => $first->feature_id,
        ]);
    }

    #[Test]
    public function it_cascades_entitlements_when_subscription_is_deleted(): void
    {
        $subscription = Subscription::factory()->create();
        Entitlement::factory()->count(3)->create([
            'subscription_id' => $subscription->id,
        ]);

        $this->assertDatabaseCount('billing_entitlements', 3);

        $subscription->forceDelete();

        $this->assertDatabaseCount('billing_entitlements', 0);
    }

    #[Test]
    public function it_restricts_feature_deletion_when_entitlement_exists(): void
    {
        $entitlement = Entitlement::factory()->create();
        $feature = Feature::find($entitlement->feature_id);
        $this->assertNotNull($feature);

        $this->expectException(QueryException::class);

        $feature->forceDelete();
    }

    // ─── Entitlement: relations ─────────────────────────────────────

    #[Test]
    public function it_loads_entitlements_relation_from_subscription(): void
    {
        $subscription = Subscription::factory()->create();
        Entitlement::factory()->count(2)->create([
            'subscription_id' => $subscription->id,
        ]);

        $loaded = Subscription::with('entitlements')->find($subscription->id);

        $this->assertNotNull($loaded);
        $this->assertCount(2, $loaded->entitlements);
    }

    // ─── EntitlementGrant: defaults & persistence ────────────────────

    #[Test]
    public function it_creates_a_grant_with_required_fields(): void
    {
        $grant = EntitlementGrant::factory()->create();

        $this->assertNotNull($grant->reason);
        $this->assertNull($grant->expires_at);
        $this->assertNull($grant->revoked_at);
        $this->assertDatabaseHas('billing_entitlement_grants', [
            'id' => $grant->id,
        ]);
    }

    // ─── EntitlementGrant: FK behaviors ──────────────────────────────

    #[Test]
    public function it_cascades_grants_when_tenant_is_deleted(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        EntitlementGrant::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseCount('billing_entitlement_grants', 2);

        $tenant->forceDelete();

        $this->assertDatabaseCount('billing_entitlement_grants', 0);
    }

    #[Test]
    public function it_nullifies_granted_by_when_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $grant = EntitlementGrant::factory()->create(['granted_by' => $user->id]);

        $this->assertSame($user->id, $grant->granted_by);

        $user->forceDelete();

        $grant->refresh();
        $this->assertNull($grant->granted_by);
    }

    // ─── EntitlementGrant: active scope ──────────────────────────────

    #[Test]
    public function active_scope_includes_perpetual_grants(): void
    {
        EntitlementGrant::factory()->perpetual()->create();

        $this->assertSame(1, EntitlementGrant::active()->count());
    }

    #[Test]
    public function active_scope_excludes_revoked_grants(): void
    {
        EntitlementGrant::factory()->revoked()->create();
        EntitlementGrant::factory()->perpetual()->create();

        $this->assertSame(1, EntitlementGrant::active()->count());
    }

    #[Test]
    public function active_scope_excludes_expired_grants(): void
    {
        EntitlementGrant::factory()->expired()->create();
        EntitlementGrant::factory()->perpetual()->create();

        $this->assertSame(1, EntitlementGrant::active()->count());
    }

    #[Test]
    public function active_scope_includes_grants_with_future_expiry(): void
    {
        EntitlementGrant::factory()->expiring(30)->create();
        EntitlementGrant::factory()->expiring(1)->create();

        $this->assertSame(2, EntitlementGrant::active()->count());
    }

    // ─── EntitlementGrant: relations ─────────────────────────────────

    #[Test]
    public function it_loads_grants_relation_from_tenant(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        EntitlementGrant::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $loaded = Tenant::with('entitlementGrants')->find($tenant->id);

        $this->assertNotNull($loaded);
        $this->assertCount(3, $loaded->entitlementGrants);
    }
}
