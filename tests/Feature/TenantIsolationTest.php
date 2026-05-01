<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['name' => 'Clinic A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Clinic B']);

        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Branch A']);
        $this->branchB = Branch::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Branch B']);

        $this->adminA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::TENANT_ADMIN,
        ]);

        $this->adminB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => UserRole::TENANT_ADMIN,
        ]);
    }

    public function test_admin_sees_only_own_tenant_branches(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('dashboard'));
        $response->assertOk();

        // Verify Branch A is in the response data
        $page = $response->original->getData()['page']['props'];
        $branchNames = collect($page['branches'] ?? [])->pluck('name')->toArray();

        $this->assertContains('Branch A', $branchNames);
        $this->assertNotContains('Branch B', $branchNames);
    }

    public function test_admin_b_sees_only_own_branches(): void
    {
        $response = $this->actingAs($this->adminB)->get(route('dashboard'));
        $response->assertOk();

        $page = $response->original->getData()['page']['props'];
        $branchNames = collect($page['branches'] ?? [])->pluck('name')->toArray();

        $this->assertContains('Branch B', $branchNames);
        $this->assertNotContains('Branch A', $branchNames);
    }

    public function test_admin_can_access_own_admin_panel(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('admin.dashboard'));
        $response->assertOk();
    }

    public function test_operator_cannot_access_admin_panel(): void
    {
        $operator = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::OPERATOR,
        ]);

        $response = $this->actingAs($operator)->get(route('admin.dashboard'));
        $response->assertForbidden();
    }

    public function test_viewer_cannot_access_operator_panel(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::VIEWER,
        ]);

        $response = $this->actingAs($viewer)->get(route('operator.index'));
        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_dashboard(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_kiosk_is_accessible_without_auth(): void
    {
        $response = $this->get(route('kiosk.public', $this->branchA));
        $response->assertOk();
    }
}
