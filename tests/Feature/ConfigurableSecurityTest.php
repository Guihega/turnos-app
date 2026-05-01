<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurableSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $branch;

    private Queue $queue;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['settings' => null]);
        $this->branch = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'max_daily_tickets' => 500,
            'max_concurrent_waiting' => 50,
            'operating_hours' => null,
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
    // REQUIRE CUSTOMER NAME (tenant configurable)
    // ══════════════════════════════════════════════════════════════

    public function test_kiosk_allows_empty_name_by_default(): void
    {
        // Default: require_customer_name = false
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            // No customer_name
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_kiosk_requires_name_when_tenant_setting_enabled(): void
    {
        $this->tenant->updateSettingsSection('security', [
            'require_customer_name' => true,
        ]);

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            // No customer_name — should fail validation
        ]);

        $response->assertSessionHasErrors('customer_name');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_kiosk_accepts_name_when_required_and_provided(): void
    {
        $this->tenant->updateSettingsSection('security', [
            'require_customer_name' => true,
        ]);

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'customer_name' => 'Juan Pérez',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tickets', ['customer_name' => 'Juan Pérez']);
    }

    // ══════════════════════════════════════════════════════════════
    // BOT PROTECTION TOGGLE (tenant configurable)
    // ══════════════════════════════════════════════════════════════

    public function test_kiosk_rejects_honeypot_when_bot_protection_enabled(): void
    {
        // Default: bot_protection = true
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'website' => 'http://spam.com',
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_kiosk_ignores_honeypot_when_bot_protection_disabled(): void
    {
        $this->tenant->updateSettingsSection('security', [
            'bot_protection' => false,
        ]);

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            'website' => 'http://spam.com', // Would normally be rejected
        ]);

        // With bot_protection off, honeypot is ignored
        $response->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_kiosk_ignores_fast_timing_when_bot_protection_disabled(): void
    {
        $this->tenant->updateSettingsSection('security', [
            'bot_protection' => false,
        ]);

        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
            '_t' => time(), // 0 seconds elapsed — would normally be rejected
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
    }

    // ══════════════════════════════════════════════════════════════
    // CONFIGURABLE MAX CONCURRENT WAITING
    // ══════════════════════════════════════════════════════════════

    public function test_kiosk_uses_tenant_concurrent_limit_not_branch_default(): void
    {
        // Branch has max_concurrent_waiting = 50 (high)
        // Tenant security setting overrides to 2 (low)
        $this->tenant->updateSettingsSection('security', [
            'max_concurrent_waiting' => 2,
        ]);

        // Create 2 waiting tickets
        for ($i = 1; $i <= 2; $i++) {
            Ticket::factory()->waiting()->create([
                'branch_id' => $this->branch->id,
                'queue_id' => $this->queue->id,
                'service_id' => $this->service->id,
                'daily_sequence' => $i,
            ]);
        }

        // 3rd ticket should be rejected by tenant's limit of 2
        $response = $this->post(route('kiosk.store', $this->branch), [
            'service_id' => $this->service->id,
            'queue_id' => $this->queue->id,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('tickets', 2);
    }

    // ══════════════════════════════════════════════════════════════
    // SECURITY SETTINGS DEFAULTS AND MERGE
    // ══════════════════════════════════════════════════════════════

    public function test_partial_security_update_preserves_defaults(): void
    {
        // Only update one field
        $this->tenant->updateSettingsSection('security', [
            'max_tickets_per_hour' => 100,
        ]);

        $this->tenant->refresh();

        // Updated field
        $this->assertEquals(100, $this->tenant->setting('security.max_tickets_per_hour'));

        // Other fields should still have defaults
        $this->assertEquals(3, $this->tenant->setting('security.max_tickets_per_ip_minute'));
        $this->assertEquals(50, $this->tenant->setting('security.max_concurrent_waiting'));
        $this->assertEquals(500, $this->tenant->setting('security.max_daily_tickets'));
        $this->assertTrue($this->tenant->setting('security.bot_protection'));
        $this->assertFalse($this->tenant->setting('security.require_customer_name'));
    }

    public function test_security_settings_dont_affect_branding(): void
    {
        // Set branding first
        $this->tenant->updateSettingsSection('branding', [
            'primary_color' => '#FF0000',
        ]);

        // Then update security
        $this->tenant->updateSettingsSection('security', [
            'max_tickets_per_hour' => 200,
        ]);

        $this->tenant->refresh();

        // Both should coexist
        $this->assertEquals('#FF0000', $this->tenant->setting('branding.primary_color'));
        $this->assertEquals(200, $this->tenant->setting('security.max_tickets_per_hour'));
    }
}
