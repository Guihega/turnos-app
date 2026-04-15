<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests cross-tenant isolation for all Admin CRUD operations.
 *
 * Verifies that an admin from Tenant A cannot create, read, update,
 * or delete resources belonging to Tenant B — even by manipulating
 * IDs in the request payload or URL.
 */
class AdminCrudSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private Branch $branchA;
    private Branch $branchB;
    private Service $serviceA;
    private Service $serviceB;
    private Queue $queueA;
    private Queue $queueB;
    private Counter $counterA;
    private Counter $counterB;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant A
        $this->tenantA = Tenant::factory()->create();
        $this->adminA = User::factory()->admin()->create(['tenant_id' => $this->tenantA->id]);
        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->serviceA = Service::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->queueA = Queue::factory()->create(['branch_id' => $this->branchA->id, 'prefix' => 'QA', 'is_active' => true]);
        $this->counterA = Counter::factory()->create(['branch_id' => $this->branchA->id]);

        // Tenant B
        $this->tenantB = Tenant::factory()->create();
        $this->adminB = User::factory()->admin()->create(['tenant_id' => $this->tenantB->id]);
        $this->branchB = Branch::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->serviceB = Service::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->queueB = Queue::factory()->create(['branch_id' => $this->branchB->id, 'prefix' => 'QB', 'is_active' => true]);
        $this->counterB = Counter::factory()->create(['branch_id' => $this->branchB->id]);
    }

    // ══════════════════════════════════════════════════════════════
    // BRANCH — cross-tenant protection
    // ══════════════════════════════════════════════════════════════

    public function test_admin_cannot_edit_branch_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('admin.sucursales.edit', $this->branchB));
        $response->assertForbidden();
    }

    public function test_admin_cannot_update_branch_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->put(route('admin.sucursales.update', $this->branchB), [
            'name' => 'Hacked Branch',
            'code' => 'HCK',
        ]);
        $response->assertForbidden();
        $this->branchB->refresh();
        $this->assertNotEquals('Hacked Branch', $this->branchB->name);
    }

    public function test_admin_cannot_delete_branch_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->delete(route('admin.sucursales.destroy', $this->branchB));
        $response->assertForbidden();
        $this->assertNotSoftDeleted($this->branchB);
    }

    // ══════════════════════════════════════════════════════════════
    // SERVICE — cross-tenant protection
    // ══════════════════════════════════════════════════════════════

    public function test_admin_cannot_edit_service_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('admin.servicios.edit', $this->serviceB));
        $response->assertForbidden();
    }

    public function test_admin_cannot_update_service_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->put(route('admin.servicios.update', $this->serviceB), [
            'name' => 'Hacked Service',
            'code' => 'HCK',
            'estimated_duration_minutes' => 15,
        ]);
        $response->assertForbidden();
        $this->serviceB->refresh();
        $this->assertNotEquals('Hacked Service', $this->serviceB->name);
    }

    public function test_admin_cannot_delete_service_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->delete(route('admin.servicios.destroy', $this->serviceB));
        $response->assertForbidden();
        $this->assertNotSoftDeleted($this->serviceB);
    }

    // ══════════════════════════════════════════════════════════════
    // QUEUE — cross-tenant protection
    // ══════════════════════════════════════════════════════════════

    public function test_admin_cannot_create_queue_in_other_tenant_branch(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('admin.colas.store'), [
            'branch_id' => $this->branchB->id,
            'name' => 'Injected Queue',
            'prefix' => 'INJ',
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('queues', ['name' => 'Injected Queue']);
    }

    public function test_admin_cannot_edit_queue_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('admin.colas.edit', $this->queueB));
        $response->assertForbidden();
    }

    public function test_admin_cannot_update_queue_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->put(route('admin.colas.update', $this->queueB), [
            'name' => 'Hacked Queue',
            'prefix' => 'HCK',
        ]);
        $response->assertForbidden();
        $this->queueB->refresh();
        $this->assertNotEquals('Hacked Queue', $this->queueB->name);
    }

    public function test_admin_cannot_delete_queue_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->delete(route('admin.colas.destroy', $this->queueB));
        $response->assertForbidden();
        $this->assertNotSoftDeleted($this->queueB);
    }

    // ══════════════════════════════════════════════════════════════
    // COUNTER — cross-tenant protection
    // ══════════════════════════════════════════════════════════════

    public function test_admin_cannot_create_counter_in_other_tenant_branch(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('admin.ventanillas.store'), [
            'branch_id' => $this->branchB->id,
            'name' => 'Injected Counter',
            'number' => '99',
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('counters', ['name' => 'Injected Counter']);
    }

    public function test_admin_cannot_edit_counter_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('admin.ventanillas.edit', $this->counterB));
        $response->assertForbidden();
    }

    public function test_admin_cannot_update_counter_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->put(route('admin.ventanillas.update', $this->counterB), [
            'name' => 'Hacked Counter',
            'number' => '99',
        ]);
        $response->assertForbidden();
        $this->counterB->refresh();
        $this->assertNotEquals('Hacked Counter', $this->counterB->name);
    }

    public function test_admin_cannot_delete_counter_from_other_tenant(): void
    {
        $response = $this->actingAs($this->adminA)->delete(route('admin.ventanillas.destroy', $this->counterB));
        $response->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════
    // USER — cross-tenant protection
    // ══════════════════════════════════════════════════════════════

    public function test_admin_cannot_edit_user_from_other_tenant(): void
    {
        $userB = User::factory()->operator()->create(['tenant_id' => $this->tenantB->id]);

        $response = $this->actingAs($this->adminA)->get(route('admin.usuarios.edit', $userB));
        $response->assertForbidden();
    }

    public function test_admin_cannot_update_user_from_other_tenant(): void
    {
        $userB = User::factory()->operator()->create(['tenant_id' => $this->tenantB->id]);

        $response = $this->actingAs($this->adminA)->put(route('admin.usuarios.update', $userB), [
            'name' => 'Hacked User',
            'email' => 'hacked@evil.com',
            'role' => 'operator',
        ]);
        $response->assertForbidden();
        $userB->refresh();
        $this->assertNotEquals('Hacked User', $userB->name);
    }

    public function test_admin_cannot_delete_user_from_other_tenant(): void
    {
        $userB = User::factory()->operator()->create(['tenant_id' => $this->tenantB->id]);

        $response = $this->actingAs($this->adminA)->delete(route('admin.usuarios.destroy', $userB));
        $response->assertForbidden();
    }

    public function test_admin_cannot_assign_user_to_other_tenant_branch(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('admin.usuarios.store'), [
            'name' => 'New Operator',
            'email' => 'new@tenantA.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'operator',
            'branch_ids' => [$this->branchB->id],
        ]);
        $response->assertForbidden();
    }

    public function test_admin_cannot_create_super_admin(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('admin.usuarios.store'), [
            'name' => 'Super Admin Attempt',
            'email' => 'super@tenantA.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::SUPER_ADMIN->value,
        ]);
        $response->assertForbidden();
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $response = $this->actingAs($this->adminA)->delete(route('admin.usuarios.destroy', $this->adminA));
        $response->assertSessionHasErrors('user');
    }

    // ══════════════════════════════════════════════════════════════
    // POSITIVE CASES — admin CAN manage own tenant resources
    // ══════════════════════════════════════════════════════════════

    public function test_admin_can_create_queue_in_own_branch(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('admin.colas.store'), [
            'branch_id' => $this->branchA->id,
            'name' => 'New Queue',
            'prefix' => 'NQ',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('queues', ['name' => 'New Queue', 'branch_id' => $this->branchA->id]);
    }

    public function test_admin_can_create_counter_in_own_branch(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('admin.ventanillas.store'), [
            'branch_id' => $this->branchA->id,
            'name' => 'New Counter',
            'number' => '999',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('counters', ['name' => 'New Counter', 'branch_id' => $this->branchA->id]);
    }

    public function test_admin_can_update_own_branch(): void
    {
        $response = $this->actingAs($this->adminA)->put(route('admin.sucursales.update', $this->branchA), [
            'name' => 'Updated Branch A',
            'code' => $this->branchA->code,
        ]);
        $response->assertRedirect();
        $this->branchA->refresh();
        $this->assertEquals('Updated Branch A', $this->branchA->name);
    }

    public function test_admin_can_delete_own_service(): void
    {
        $response = $this->actingAs($this->adminA)->delete(route('admin.servicios.destroy', $this->serviceA));
        $response->assertRedirect();
        $this->assertSoftDeleted($this->serviceA);
    }
}
