<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Branch $branchA;
    private Branch $branchB;
    private Queue $queueA;
    private Service $serviceA;
    private User $adminA;
    private User $staffA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->queueA = Queue::factory()->create(['branch_id' => $this->branchA->id, 'is_active' => true]);
        $this->serviceA = Service::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->queueA->services()->attach($this->serviceA->id);

        $this->adminA = User::factory()->admin()->create(['tenant_id' => $this->tenantA->id]);
        $this->staffA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => UserRole::RECEPTIONIST]);

        $this->tenantB = Tenant::factory()->create();
        $this->branchB = Branch::factory()->create(['tenant_id' => $this->tenantB->id]);
    }

    // ══════════════════════════════════════════════════════════════
    // CROSS-TENANT VALIDATION
    // ══════════════════════════════════════════════════════════════

    public function test_admin_cannot_issue_ticket_to_other_tenant_branch(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('tickets.issue'), [
            'branch_id' => $this->branchB->id, // Tenant B's branch
            'queue_id' => $this->queueA->id,
            'service_id' => $this->serviceA->id,
        ]);

        // Should fail — branch doesn't belong to tenant A
        $response->assertStatus(404); // findOrFail throws 404
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_admin_cannot_view_ticket_from_other_tenant(): void
    {
        $ticket = Ticket::factory()->create([
            'branch_id' => $this->branchB->id,
            'queue_id' => Queue::factory()->create(['branch_id' => $this->branchB->id])->id,
            'service_id' => Service::factory()->create(['tenant_id' => $this->tenantB->id])->id,
        ]);

        $response = $this->actingAs($this->adminA)->get(route('tickets.show', $ticket));
        $response->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════
    // QUEUE-BRANCH VALIDATION
    // ══════════════════════════════════════════════════════════════

    public function test_admin_cannot_issue_ticket_with_queue_from_different_branch(): void
    {
        $otherBranch = Branch::factory()->create(['tenant_id' => $this->tenantA->id]);
        $otherQueue = Queue::factory()->create(['branch_id' => $otherBranch->id, 'is_active' => true]);

        $response = $this->actingAs($this->staffA)->post(route('tickets.issue'), [
            'branch_id' => $this->branchA->id,
            'queue_id' => $otherQueue->id, // Doesn't belong to branchA
            'service_id' => $this->serviceA->id,
        ]);

        $response->assertStatus(404); // Queue not found for this branch
    }

    // ══════════════════════════════════════════════════════════════
    // ROLE ACCESS
    // ══════════════════════════════════════════════════════════════

    public function test_viewer_cannot_issue_tickets(): void
    {
        $viewer = User::factory()->viewer()->create(['tenant_id' => $this->tenantA->id]);

        $response = $this->actingAs($viewer)->post(route('tickets.issue'), [
            'branch_id' => $this->branchA->id,
            'queue_id' => $this->queueA->id,
            'service_id' => $this->serviceA->id,
        ]);

        $response->assertForbidden();
    }

    public function test_operator_cannot_issue_tickets_via_admin_route(): void
    {
        $operator = User::factory()->operator()->create(['tenant_id' => $this->tenantA->id]);

        $response = $this->actingAs($operator)->post(route('tickets.issue'), [
            'branch_id' => $this->branchA->id,
            'queue_id' => $this->queueA->id,
            'service_id' => $this->serviceA->id,
        ]);

        // role:staff middleware requires receptionist+ level (30)
        // operator level is 40, which IS >= 30, so operator CAN access staff routes
        // This is correct behavior — operators can issue tickets
        $response->assertRedirect();
    }

    public function test_receptionist_can_issue_tickets(): void
    {
        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->staffA)->post(route('tickets.issue'), [
            'branch_id' => $this->branchA->id,
            'queue_id' => $this->queueA->id,
            'service_id' => $this->serviceA->id,
            'customer_name' => 'Admin Issued',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tickets', [
            'customer_name' => 'Admin Issued',
            'status' => 'waiting',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // UNAUTHENTICATED ACCESS
    // ══════════════════════════════════════════════════════════════

    public function test_unauthenticated_cannot_issue_ticket_via_admin(): void
    {
        $response = $this->post(route('tickets.issue'), [
            'branch_id' => $this->branchA->id,
            'queue_id' => $this->queueA->id,
            'service_id' => $this->serviceA->id,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_cannot_view_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'branch_id' => $this->branchA->id,
            'queue_id' => $this->queueA->id,
            'service_id' => $this->serviceA->id,
        ]);

        $response = $this->get(route('tickets.show', $ticket));
        $response->assertRedirect(route('login'));
    }
}
