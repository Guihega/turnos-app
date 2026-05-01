<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $branch;

    private Queue $queue;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->branch = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'max_daily_tickets' => 500,
            'max_concurrent_waiting' => 5, // Low for testing
            'operating_hours' => null, // null = always open
        ]);
        $this->queue = Queue::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'prefix' => 'A',
            'max_capacity' => 100,
        ]);
        $this->service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->queue->services()->attach($this->service->id);
    }

    // ══════════════════════════════════════════════════════════════
    // CROSS-ENTITY VALIDATION
    // ══════════════════════════════════════════════════════════════

    public function test_kiosk_rejects_queue_from_different_branch(): void
    {
        $otherBranch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherQueue = Queue::factory()->create(['branch_id' => $otherBranch->id, 'is_active' => true]);

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $otherQueue->id,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_kiosk_rejects_service_not_linked_to_queue(): void
    {
        $unlinkedService = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        // NOT attached to $this->queue

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $unlinkedService->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_kiosk_rejects_inactive_queue(): void
    {
        $this->queue->update(['is_active' => false]);

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_kiosk_rejects_inactive_service(): void
    {
        $this->service->update(['is_active' => false]);
        // Re-attach since the query checks is_active
        $this->queue->services()->syncWithoutDetaching([$this->service->id]);

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 0);
    }

    // ══════════════════════════════════════════════════════════════
    // BRANCH STATUS VALIDATION
    // ══════════════════════════════════════════════════════════════

    public function test_kiosk_rejects_when_branch_closed_by_schedule(): void
    {
        // Set operating hours to only open on a day that is NOT today
        $today = strtolower(now('America/Mexico_City')->format('D'));
        $otherDay = $today === 'mon' ? 'tue' : 'mon';

        $this->branch->update([
            'operating_hours' => [
                $otherDay => ['open' => '08:00', 'close' => '18:00'],
            ],
        ]);

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
        ]);

        // IssueTicketAction validates isOpen() and throws RuntimeException
        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_kiosk_rejects_inactive_branch(): void
    {
        $this->branch->update(['is_active' => false]);

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_kiosk_shows_is_open_status_correctly(): void
    {
        $response = $this->get(route('kiosk.public', $this->branch));
        $response->assertOk();

        $props = $response->original->getData()['page']['props'];
        // With null operating_hours, branch is always open
        $this->assertTrue($props['branch']['is_open']);
    }

    // ══════════════════════════════════════════════════════════════
    // CAPACITY LIMITS
    // ══════════════════════════════════════════════════════════════

    public function test_kiosk_rejects_when_max_concurrent_waiting_reached(): void
    {
        // Create 5 waiting tickets (max_concurrent_waiting = 5)
        for ($i = 1; $i <= 5; $i++) {
            Ticket::factory()->waiting()->create([
                'branch_id' => $this->branch->id,
                'queue_id' => $this->queue->id,
                'service_id' => $this->service->id,
                'daily_sequence' => $i,
            ]);
        }

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 5); // No new ticket created
    }

    public function test_kiosk_allows_ticket_when_under_concurrent_limit(): void
    {
        // Branch has max_concurrent_waiting = 5
        // With 0 tickets in queue, creating one should succeed
        $this->withoutExceptionHandling();

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'customer_name' => 'Under Limit Customer',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tickets', [
            'branch_id' => $this->branch->id,
            'customer_name' => 'Under Limit Customer',
            'status' => 'waiting',
        ]);
    }

    public function test_kiosk_rejects_when_queue_is_full(): void
    {
        $this->queue->update(['max_capacity' => 2]);

        // Fill queue to capacity
        for ($i = 1; $i <= 2; $i++) {
            Ticket::factory()->waiting()->create([
                'branch_id' => $this->branch->id,
                'queue_id' => $this->queue->id,
                'service_id' => $this->service->id,
                'daily_sequence' => $i,
            ]);
        }

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_kiosk_rejects_when_daily_limit_reached(): void
    {
        $this->branch->update(['max_daily_tickets' => 3]);

        // Create 3 tickets today (any status counts)
        for ($i = 1; $i <= 3; $i++) {
            Ticket::factory()->create([
                'branch_id' => $this->branch->id,
                'queue_id' => $this->queue->id,
                'service_id' => $this->service->id,
                'daily_sequence' => $i,
            ]);
        }

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors();
    }

    // ══════════════════════════════════════════════════════════════
    // BOT PROTECTION
    // ══════════════════════════════════════════════════════════════

    public function test_kiosk_rejects_honeypot_filled(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'website' => 'http://spam.com', // Bot filled the honeypot
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_kiosk_rejects_submission_too_fast(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            '_t' => time(), // Submitted instantly (0 seconds elapsed)
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_kiosk_allows_normal_submission_timing(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            '_t' => time() - 5, // 5 seconds ago — normal human timing
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_kiosk_allows_without_timing_field(): void
    {
        // If _t is not present, bot protection is skipped (backward compat)
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_kiosk_allows_empty_honeypot(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'website' => '', // Empty = human (not filled)
            '_t' => time() - 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
    }

    // ══════════════════════════════════════════════════════════════
    // INPUT VALIDATION
    // ══════════════════════════════════════════════════════════════

    public function test_kiosk_requires_service_id(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors('service_id');
    }

    public function test_kiosk_requires_queue_id(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
        ]);

        $response->assertSessionHasErrors('queue_id');
    }

    public function test_kiosk_rejects_nonexistent_service(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => 'nonexistent-id',
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors('service_id');
    }

    public function test_kiosk_rejects_nonexistent_queue(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => 'nonexistent-id',
        ]);

        $response->assertSessionHasErrors('queue_id');
    }

    public function test_kiosk_sanitizes_customer_name_length(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'customer_name' => str_repeat('A', 300), // Over 255 limit
        ]);

        $response->assertSessionHasErrors('customer_name');
    }

    public function test_kiosk_sanitizes_customer_phone_length(): void
    {
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'customer_phone' => str_repeat('1', 25), // Over 20 limit
        ]);

        $response->assertSessionHasErrors('customer_phone');
    }
}
