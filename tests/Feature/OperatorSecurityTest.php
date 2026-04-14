<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Branch $branchA;
    private Branch $branchB;
    private Queue $queueA;
    private Queue $queueB;
    private Service $serviceA;
    private Counter $counterA;
    private Counter $counterB;
    private User $operatorA;
    private User $operatorB;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant A setup
        $this->tenantA = Tenant::factory()->create();
        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->queueA = Queue::factory()->create(['branch_id' => $this->branchA->id, 'is_active' => true]);
        $this->serviceA = Service::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->queueA->services()->attach($this->serviceA->id);
        $this->counterA = Counter::factory()->create(['branch_id' => $this->branchA->id, 'status' => 'open']);
        $this->operatorA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => UserRole::OPERATOR]);
        $this->operatorA->branches()->attach($this->branchA->id, ['role' => 'operator']);

        // Tenant B setup
        $this->tenantB = Tenant::factory()->create();
        $this->branchB = Branch::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->queueB = Queue::factory()->create(['branch_id' => $this->branchB->id, 'is_active' => true]);
        $this->counterB = Counter::factory()->create(['branch_id' => $this->branchB->id, 'status' => 'open']);
        $this->operatorB = User::factory()->create(['tenant_id' => $this->tenantB->id, 'role' => UserRole::OPERATOR]);
        $this->operatorB->branches()->attach($this->branchB->id, ['role' => 'operator']);
    }

    // ══════════════════════════════════════════════════════════════
    // TICKET OWNERSHIP — operator can only act on own tickets
    // ══════════════════════════════════════════════════════════════

    public function test_operator_cannot_start_ticket_assigned_to_other_operator(): void
    {
        $ticket = $this->createCalledTicket($this->branchA, $this->queueA, $this->serviceA, $this->operatorA, $this->counterA);

        // Operator B tries to start Operator A's ticket
        $response = $this->actingAs($this->operatorB)->post(route('operator.start', $ticket));
        $response->assertForbidden();
    }

    public function test_operator_cannot_complete_ticket_assigned_to_other_operator(): void
    {
        $ticket = $this->createInProgressTicket($this->branchA, $this->queueA, $this->serviceA, $this->operatorA, $this->counterA);

        $response = $this->actingAs($this->operatorB)->post(route('operator.complete', $ticket));
        $response->assertForbidden();
    }

    public function test_operator_cannot_recall_ticket_assigned_to_other_operator(): void
    {
        $ticket = $this->createCalledTicket($this->branchA, $this->queueA, $this->serviceA, $this->operatorA, $this->counterA);

        $response = $this->actingAs($this->operatorB)->post(route('operator.recall', $ticket));
        $response->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════
    // CROSS-TENANT — operator cannot touch tickets from other tenant
    // ══════════════════════════════════════════════════════════════

    public function test_operator_cannot_cancel_ticket_from_other_tenant(): void
    {
        $ticketB = $this->createWaitingTicket($this->branchB, $this->queueB);

        $response = $this->actingAs($this->operatorA)->post(route('operator.cancel', $ticketB));
        $response->assertForbidden();
    }

    public function test_operator_cannot_noshow_ticket_from_other_tenant(): void
    {
        $ticketB = $this->createCalledTicket($this->branchB, $this->queueB, $this->serviceA, $this->operatorB, $this->counterB);

        $response = $this->actingAs($this->operatorA)->post(route('operator.noshow', $ticketB));
        $response->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════
    // TRANSFER VALIDATION
    // ══════════════════════════════════════════════════════════════

    public function test_operator_cannot_transfer_to_queue_in_different_branch(): void
    {
        $ticket = $this->createInProgressTicket($this->branchA, $this->queueA, $this->serviceA, $this->operatorA, $this->counterA);

        $response = $this->actingAs($this->operatorA)->post(route('operator.transfer', $ticket), [
            'target_queue_id' => $this->queueB->id, // Queue from another branch/tenant
            'reason' => 'Testing cross-branch transfer',
        ]);

        $response->assertSessionHasErrors();
        $ticket->refresh();
        $this->assertNotEquals(TicketStatus::TRANSFERRED, $ticket->status);
    }

    public function test_operator_can_transfer_to_queue_in_same_branch(): void
    {
        $targetQueue = Queue::factory()->create(['branch_id' => $this->branchA->id, 'prefix' => 'TRF', 'is_active' => true]);
        $ticket = $this->createInProgressTicket($this->branchA, $this->queueA, $this->serviceA, $this->operatorA, $this->counterA);

        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->operatorA)->post(route('operator.transfer', $ticket), [
            'target_queue_id' => $targetQueue->id,
            'reason' => 'Needs specialist',
        ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertEquals(TicketStatus::TRANSFERRED, $ticket->status);
    }

    // ══════════════════════════════════════════════════════════════
    // COUNTER VALIDATION
    // ══════════════════════════════════════════════════════════════

    public function test_operator_cannot_call_from_counter_in_other_tenant(): void
    {
        $this->createWaitingTicket($this->branchA, $this->queueA);

        $response = $this->actingAs($this->operatorA)->post(route('operator.call'), [
            'counter_id' => $this->counterB->id, // Counter from tenant B
            'queue_id' => $this->queueA->id,
        ]);

        $response->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════
    // STATE MACHINE ENFORCEMENT
    // ══════════════════════════════════════════════════════════════

    public function test_cannot_start_ticket_not_in_called_status(): void
    {
        $ticket = $this->createWaitingTicket($this->branchA, $this->queueA);
        $ticket->update(['served_by' => $this->operatorA->id]);

        $response = $this->actingAs($this->operatorA)->post(route('operator.start', $ticket));
        $response->assertSessionHasErrors();
    }

    public function test_cannot_recall_ticket_not_in_called_status(): void
    {
        $ticket = $this->createInProgressTicket($this->branchA, $this->queueA, $this->serviceA, $this->operatorA, $this->counterA);

        $response = $this->actingAs($this->operatorA)->post(route('operator.recall', $ticket));
        $response->assertSessionHasErrors();
    }

    // ══════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════

    private function createWaitingTicket(Branch $branch, Queue $queue): Ticket
    {
        return Ticket::create([
            'branch_id' => $branch->id,
            'queue_id' => $queue->id,
            'service_id' => $this->serviceA->id,
            'ticket_number' => 'A-' . fake()->unique()->numberBetween(100, 999),
            'daily_sequence' => fake()->unique()->numberBetween(1, 999),
            'display_number' => 'TST-A-' . fake()->unique()->numberBetween(100, 999),
            'status' => TicketStatus::WAITING,
            'priority' => TicketPriority::NORMAL,
            'priority_score' => TicketPriority::NORMAL->weight(),
            'issued_at' => now(),
        ]);
    }

    private function createCalledTicket(Branch $branch, Queue $queue, Service $service, User $operator, Counter $counter): Ticket
    {
        $ticket = $this->createWaitingTicket($branch, $queue);
        $counter->update(['current_operator_id' => $operator->id]);
        $ticket->transitionTo(TicketStatus::CALLED, $operator->id);
        $ticket->update(['served_by' => $operator->id, 'counter_id' => $counter->id]);
        return $ticket;
    }

    private function createInProgressTicket(Branch $branch, Queue $queue, Service $service, User $operator, Counter $counter): Ticket
    {
        $ticket = $this->createCalledTicket($branch, $queue, $service, $operator, $counter);
        $ticket->transitionTo(TicketStatus::IN_PROGRESS, $operator->id);
        return $ticket;
    }
}
