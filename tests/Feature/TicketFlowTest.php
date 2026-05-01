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
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TicketFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $branch;

    private Queue $queue;

    private Service $service;

    private Counter $counter;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->branch = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'max_daily_tickets' => 500,
            'max_concurrent_waiting' => 50,
        ]);
        $this->queue = Queue::factory()->create([
            'branch_id' => $this->branch->id,
            'prefix' => 'A',
            'is_active' => true,
            'max_capacity' => 100,
        ]);
        $this->service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->counter = Counter::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => 'open',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::OPERATOR,
        ]);
        $this->operator->branches()->attach($this->branch->id, ['role' => 'operator']);

        $this->queue->services()->attach($this->service->id);
    }

    public function test_kiosk_can_issue_ticket(): void
    {
        $this->withoutExceptionHandling();

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'customer_name' => 'Test Customer',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'branch_id' => $this->branch->id,
            'queue_id' => $this->queue->id,
            'customer_name' => 'Test Customer',
            'status' => 'waiting',
        ]);
    }

    public function test_operator_can_call_next_ticket(): void
    {
        $ticket = $this->createWaitingTicket();

        $this->counter->update([
            'current_operator_id' => $this->operator->id,
            'status' => 'open',
        ]);

        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->operator)->post(route('operator.call'), [
            'counter_id' => $this->counter->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals(TicketStatus::CALLED, $ticket->status);
        $this->assertEquals($this->operator->id, $ticket->served_by);
    }

    public function test_operator_can_start_serving(): void
    {
        $ticket = $this->createCalledTicket();

        $response = $this->actingAs($this->operator)->post(route('operator.start', $ticket));
        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals(TicketStatus::IN_PROGRESS, $ticket->status);
    }

    public function test_operator_can_complete_ticket(): void
    {
        $ticket = $this->createInProgressTicket();

        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->operator)->post(route('operator.complete', $ticket), [
            'rating' => 5,
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals(TicketStatus::COMPLETED, $ticket->status);
    }

    public function test_operator_can_transfer_ticket(): void
    {
        $targetQueue = Queue::factory()->create([
            'branch_id' => $this->branch->id,
            'prefix' => 'B',
            'is_active' => true,
        ]);
        $ticket = $this->createInProgressTicket();

        // Sync the cache so getDailySequence() won't collide with the
        // manually-created ticket (daily_sequence = 1).
        $cacheKey = "branch:{$this->branch->id}:daily_seq:".today()->format('Y-m-d');
        Cache::put($cacheKey, 1, now()->endOfDay());

        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->operator)->post(route('operator.transfer', $ticket), [
            'target_queue_id' => $targetQueue->id,
            'reason' => 'Needs specialist',
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals(TicketStatus::TRANSFERRED, $ticket->status);

        $newTicket = Ticket::where('transferred_from_id', $ticket->id)->first();
        $this->assertNotNull($newTicket);
        $this->assertEquals($targetQueue->id, $newTicket->queue_id);
    }

    public function test_operator_can_mark_no_show(): void
    {
        $ticket = $this->createCalledTicket();

        $response = $this->actingAs($this->operator)->post(route('operator.noshow', $ticket));
        $response->assertRedirect();

        $ticket->refresh();
        $this->assertEquals(TicketStatus::NO_SHOW, $ticket->status);
    }

    public function test_complete_flow_end_to_end(): void
    {
        $this->withoutExceptionHandling();

        // 1. Issue from kiosk
        $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'customer_name' => 'E2E Customer',
        ]);

        $ticket = Ticket::where('customer_name', 'E2E Customer')->firstOrFail();
        $this->assertEquals(TicketStatus::WAITING, $ticket->status);

        // Assign counter to operator
        $this->counter->update(['current_operator_id' => $this->operator->id, 'status' => 'open']);

        // 2. Call
        $this->actingAs($this->operator)->post(route('operator.call'), [
            'counter_id' => $this->counter->id,
        ]);
        $ticket->refresh();
        $this->assertEquals(TicketStatus::CALLED, $ticket->status);

        // 3. Start
        $this->actingAs($this->operator)->post(route('operator.start', $ticket));
        $ticket->refresh();
        $this->assertEquals(TicketStatus::IN_PROGRESS, $ticket->status);

        // 4. Complete
        $this->actingAs($this->operator)->post(route('operator.complete', $ticket), ['rating' => 4]);
        $ticket->refresh();
        $this->assertEquals(TicketStatus::COMPLETED, $ticket->status);
        $this->assertEquals(4, $ticket->rating);
    }

    // ── Helpers ──

    private function createWaitingTicket(): Ticket
    {
        return Ticket::create([
            'branch_id' => $this->branch->id,
            'queue_id' => $this->queue->id,
            'service_id' => $this->service->id,
            'ticket_number' => 'A-001',
            'daily_sequence' => 1,
            'display_number' => 'TST-A-001',
            'status' => TicketStatus::WAITING,
            'priority' => TicketPriority::NORMAL,
            'priority_score' => TicketPriority::NORMAL->weight(),
            'issued_at' => now(),
        ]);
    }

    private function createCalledTicket(): Ticket
    {
        $ticket = $this->createWaitingTicket();
        $this->counter->update(['current_operator_id' => $this->operator->id, 'status' => 'open']);
        $ticket->transitionTo(TicketStatus::CALLED, $this->operator->id);
        $ticket->update(['served_by' => $this->operator->id, 'counter_id' => $this->counter->id]);

        return $ticket;
    }

    private function createInProgressTicket(): Ticket
    {
        $ticket = $this->createCalledTicket();
        $ticket->transitionTo(TicketStatus::IN_PROGRESS, $this->operator->id);

        return $ticket;
    }
}
