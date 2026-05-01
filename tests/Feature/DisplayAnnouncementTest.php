<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\DisplayAnnouncement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplayAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::TENANT_ADMIN,
        ]);
        $this->branch = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->branches()->attach($this->branch->id);
    }

    // ─── Listado ───

    public function test_admin_can_view_announcements_index(): void
    {
        DisplayAnnouncement::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.announcements.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Announcements/Index')
            ->has('announcements.data', 3)
        );
    }

    // ─── Crear ───

    public function test_admin_can_create_announcement(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type' => 'announcement',
            'title' => 'Horario especial viernes',
            'body' => 'Cerraremos a las 14:00',
            'priority' => 5,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('display_announcements', [
            'tenant_id' => $this->tenant->id,
            'title' => 'Horario especial viernes',
            'type' => 'announcement',
        ]);
    }

    public function test_can_create_announcement_for_specific_branch(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type' => 'news',
            'title' => 'Nueva ventanilla abierta',
            'branch_id' => $this->branch->id,
            'priority' => 0,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('display_announcements', [
            'branch_id' => $this->branch->id,
            'title' => 'Nueva ventanilla abierta',
        ]);
    }

    public function test_title_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type' => 'announcement',
            'title' => '',
            'is_active' => true,
            'priority' => 0,
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_type_must_be_valid(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type' => 'invalid_type',
            'title' => 'Test',
            'is_active' => true,
            'priority' => 0,
        ]);

        $response->assertSessionHasErrors('type');
    }

    // ─── Editar ───

    public function test_admin_can_update_announcement(): void
    {
        $announcement = DisplayAnnouncement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Original',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.announcements.update', $announcement), [
            'type' => 'promo',
            'title' => 'Actualizado',
            'priority' => 10,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('display_announcements', [
            'id' => $announcement->id,
            'title' => 'Actualizado',
            'type' => 'promo',
        ]);
    }

    // ─── Toggle ───

    public function test_admin_can_toggle_announcement(): void
    {
        $announcement = DisplayAnnouncement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->patch(route('admin.announcements.toggle', $announcement));

        $this->assertDatabaseHas('display_announcements', [
            'id' => $announcement->id,
            'is_active' => false,
        ]);
    }

    // ─── Eliminar ───

    public function test_admin_can_delete_announcement(): void
    {
        $announcement = DisplayAnnouncement::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.announcements.destroy', $announcement));

        $response->assertRedirect();
        $this->assertDatabaseMissing('display_announcements', ['id' => $announcement->id]);
    }

    // ─── Aislamiento de tenant ───

    public function test_cannot_update_other_tenant_announcement(): void
    {
        $otherTenant = Tenant::factory()->create();
        $announcement = DisplayAnnouncement::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.announcements.update', $announcement), [
            'type' => 'news',
            'title' => 'Hacked',
            'is_active' => true,
            'priority' => 0,
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_delete_other_tenant_announcement(): void
    {
        $otherTenant = Tenant::factory()->create();
        $announcement = DisplayAnnouncement::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.announcements.destroy', $announcement));

        $response->assertForbidden();
    }

    public function test_cannot_assign_branch_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherBranch = Branch::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type' => 'announcement',
            'title' => 'Test',
            'branch_id' => $otherBranch->id,
            'priority' => 0,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('branch_id');
    }

    // ─── Scopes del modelo ───

    public function test_active_scope_filters_inactive(): void
    {
        DisplayAnnouncement::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        DisplayAnnouncement::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $count = DisplayAnnouncement::where('tenant_id', $this->tenant->id)->active()->count();
        $this->assertEquals(1, $count);
    }

    public function test_active_scope_filters_expired(): void
    {
        DisplayAnnouncement::factory()->expired()->create(['tenant_id' => $this->tenant->id]);
        DisplayAnnouncement::factory()->scheduled()->create(['tenant_id' => $this->tenant->id]);

        $count = DisplayAnnouncement::where('tenant_id', $this->tenant->id)->active()->count();
        $this->assertEquals(1, $count);
    }

    public function test_active_scope_filters_future(): void
    {
        DisplayAnnouncement::factory()->future()->create(['tenant_id' => $this->tenant->id]);

        $count = DisplayAnnouncement::where('tenant_id', $this->tenant->id)->active()->count();
        $this->assertEquals(0, $count);
    }

    public function test_for_branch_scope_includes_global_and_specific(): void
    {
        // Global (sin branch)
        DisplayAnnouncement::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => null]);
        // Para esta branch
        DisplayAnnouncement::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);
        // Para otra branch
        $otherBranch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        DisplayAnnouncement::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $otherBranch->id]);

        $count = DisplayAnnouncement::where('tenant_id', $this->tenant->id)
            ->forBranch($this->branch->id)
            ->count();

        $this->assertEquals(2, $count); // global + específico
    }

    // ─── Permisos ───

    public function test_operator_cannot_access_announcements(): void
    {
        $operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::OPERATOR,
        ]);
        $operator->branches()->attach($this->branch->id);

        $response = $this->actingAs($operator)->get(route('admin.announcements.index'));

        $response->assertForbidden();
    }

    // ─── Pantalla pública incluye anuncios ───

    public function test_public_display_includes_announcements(): void
    {
        DisplayAnnouncement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Anuncio visible',
            'is_active' => true,
        ]);

        $response = $this->get(route('display.public', $this->branch));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Display/Screen')
            ->has('announcements', 1)
        );
    }
}
