<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = $this->createUser(UserRole::TENANT_ADMIN);
        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();
    }

    public function test_branch_manager_cannot_access_admin_routes(): void
    {
        $manager = $this->createUser(UserRole::BRANCH_MANAGER);
        $response = $this->actingAs($manager)->get(route('admin.dashboard'));
        $response->assertForbidden();
    }

    public function test_operator_can_access_operator_routes(): void
    {
        $operator = $this->createUser(UserRole::OPERATOR);
        $response = $this->actingAs($operator)->get(route('operator.index'));
        $response->assertOk();
    }

    public function test_viewer_cannot_access_operator_routes(): void
    {
        $viewer = $this->createUser(UserRole::VIEWER);
        $response = $this->actingAs($viewer)->get(route('operator.index'));
        $response->assertForbidden();
    }

    public function test_receptionist_cannot_access_operator_routes(): void
    {
        $receptionist = $this->createUser(UserRole::RECEPTIONIST);
        $response = $this->actingAs($receptionist)->get(route('operator.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_access_operator_routes(): void
    {
        $admin = $this->createUser(UserRole::TENANT_ADMIN);
        $response = $this->actingAs($admin)->get(route('operator.index'));
        $response->assertOk();
    }

    private function createUser(UserRole $role): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => $role,
        ]);
    }
}
