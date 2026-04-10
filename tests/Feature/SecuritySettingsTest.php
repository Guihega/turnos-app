<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['settings' => null]);
        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // DEFAULT SECURITY SETTINGS
    // ══════════════════════════════════════════════════════════════

    public function test_security_settings_have_defaults(): void
    {
        $effective = $this->tenant->getEffectiveSettings();

        $this->assertArrayHasKey('security', $effective);
        $this->assertEquals(60, $effective['security']['max_tickets_per_hour']);
        $this->assertEquals(3, $effective['security']['max_tickets_per_ip_minute']);
        $this->assertEquals(50, $effective['security']['max_concurrent_waiting']);
        $this->assertEquals(500, $effective['security']['max_daily_tickets']);
        $this->assertTrue($effective['security']['bot_protection']);
        $this->assertFalse($effective['security']['require_customer_name']);
    }

    public function test_security_defaults_accessible_via_dot_notation(): void
    {
        $this->assertEquals(60, $this->tenant->setting('security.max_tickets_per_hour'));
        $this->assertEquals(3, $this->tenant->setting('security.max_tickets_per_ip_minute'));
        $this->assertTrue($this->tenant->setting('security.bot_protection'));
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN CAN UPDATE SECURITY SETTINGS
    // ══════════════════════════════════════════════════════════════

    public function test_admin_can_update_security_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security'), [
                'max_tickets_per_hour' => 100,
                'max_tickets_per_ip_minute' => 5,
                'max_concurrent_waiting' => 80,
                'max_daily_tickets' => 1000,
                'bot_protection' => false,
                'require_customer_name' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->tenant->refresh();
        $this->assertEquals(100, $this->tenant->setting('security.max_tickets_per_hour'));
        $this->assertEquals(5, $this->tenant->setting('security.max_tickets_per_ip_minute'));
        $this->assertEquals(80, $this->tenant->setting('security.max_concurrent_waiting'));
        $this->assertEquals(1000, $this->tenant->setting('security.max_daily_tickets'));
        $this->assertFalse($this->tenant->setting('security.bot_protection'));
        $this->assertTrue($this->tenant->setting('security.require_customer_name'));
    }

    // ══════════════════════════════════════════════════════════════
    // VALIDATION BOUNDARIES
    // ══════════════════════════════════════════════════════════════

    public function test_security_rejects_tickets_per_hour_below_minimum(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security'), [
                'max_tickets_per_hour' => 5, // Min is 10
                'max_tickets_per_ip_minute' => 3,
                'max_concurrent_waiting' => 50,
                'max_daily_tickets' => 500,
                'bot_protection' => true,
                'require_customer_name' => false,
            ])
            ->assertSessionHasErrors('max_tickets_per_hour');
    }

    public function test_security_rejects_tickets_per_hour_above_maximum(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security'), [
                'max_tickets_per_hour' => 600, // Max is 500
                'max_tickets_per_ip_minute' => 3,
                'max_concurrent_waiting' => 50,
                'max_daily_tickets' => 500,
                'bot_protection' => true,
                'require_customer_name' => false,
            ])
            ->assertSessionHasErrors('max_tickets_per_hour');
    }

    public function test_security_rejects_daily_tickets_below_minimum(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security'), [
                'max_tickets_per_hour' => 60,
                'max_tickets_per_ip_minute' => 3,
                'max_concurrent_waiting' => 50,
                'max_daily_tickets' => 10, // Min is 50
                'bot_protection' => true,
                'require_customer_name' => false,
            ])
            ->assertSessionHasErrors('max_daily_tickets');
    }

    public function test_security_rejects_concurrent_waiting_below_minimum(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security'), [
                'max_tickets_per_hour' => 60,
                'max_tickets_per_ip_minute' => 3,
                'max_concurrent_waiting' => 2, // Min is 5
                'max_daily_tickets' => 500,
                'bot_protection' => true,
                'require_customer_name' => false,
            ])
            ->assertSessionHasErrors('max_concurrent_waiting');
    }

    // ══════════════════════════════════════════════════════════════
    // ACCESS CONTROL
    // ══════════════════════════════════════════════════════════════

    public function test_operator_cannot_update_security_settings(): void
    {
        $operator = User::factory()->operator()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($operator)
            ->put(route('admin.settings.security'), [
                'max_tickets_per_hour' => 100,
                'max_tickets_per_ip_minute' => 5,
                'max_concurrent_waiting' => 80,
                'max_daily_tickets' => 1000,
                'bot_protection' => false,
                'require_customer_name' => true,
            ])
            ->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════
    // SECTION ISOLATION — updating security doesn't affect other settings
    // ══════════════════════════════════════════════════════════════

    public function test_security_update_preserves_other_sections(): void
    {
        // First set branding
        $this->actingAs($this->admin)
            ->put(route('admin.settings.branding'), [
                'primary_color' => '#FF0000',
                'secondary_color' => '#00FF00',
                'accent_color' => '#0000FF',
                'logo_shape' => 'circle',
                'dark_mode_default' => true,
            ]);

        // Then update security
        $this->actingAs($this->admin)
            ->put(route('admin.settings.security'), [
                'max_tickets_per_hour' => 100,
                'max_tickets_per_ip_minute' => 5,
                'max_concurrent_waiting' => 80,
                'max_daily_tickets' => 1000,
                'bot_protection' => true,
                'require_customer_name' => false,
            ]);

        $this->tenant->refresh();

        // Branding untouched
        $this->assertEquals('#FF0000', $this->tenant->setting('branding.primary_color'));
        $this->assertEquals('circle', $this->tenant->setting('branding.logo_shape'));

        // Security updated
        $this->assertEquals(100, $this->tenant->setting('security.max_tickets_per_hour'));
    }
}
