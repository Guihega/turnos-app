<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'settings' => null,
        ]);

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ── Access Control ─────────────────────────────────

    public function test_admin_can_access_settings_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/TenantSettings')
                ->has('settings')
                ->has('tenant')
            );
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $operator = User::factory()->operator()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($operator)
            ->get(route('admin.settings.edit'))
            ->assertForbidden();
    }

    // ── Default Settings ───────────────────────────────

    public function test_tenant_returns_defaults_when_settings_null(): void
    {
        $this->assertNull($this->tenant->getRawOriginal('settings'));

        $effective = $this->tenant->getEffectiveSettings();

        $this->assertEquals('#3B82F6', $effective['branding']['primary_color']);
        $this->assertEquals('chime', $effective['display']['call_sound']);
        $this->assertEquals('Bienvenido', $effective['kiosk']['welcome_text']);
        $this->assertTrue($effective['tickets']['daily_reset']);
    }

    public function test_setting_helper_with_dot_notation(): void
    {
        $this->assertEquals('#3B82F6', $this->tenant->setting('branding.primary_color'));
        $this->assertEquals(5, $this->tenant->setting('display.show_recent_count'));
        $this->assertEquals('fallback', $this->tenant->setting('nonexistent.key', 'fallback'));
    }

    // ── Branding Update ────────────────────────────────

    public function test_admin_can_update_branding(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.branding'), [
                'primary_color'     => '#FF0000',
                'secondary_color'   => '#00FF00',
                'accent_color'      => '#0000FF',
                'logo_shape'        => 'circle',
                'dark_mode_default' => false,
            ])
            ->assertRedirect();

        $this->tenant->refresh();
        $this->assertEquals('#FF0000', $this->tenant->setting('branding.primary_color'));
        $this->assertEquals('circle', $this->tenant->setting('branding.logo_shape'));
        $this->assertFalse($this->tenant->setting('branding.dark_mode_default'));
    }

    public function test_branding_validates_color_format(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.branding'), [
                'primary_color'     => 'not-a-color',
                'secondary_color'   => '#00FF00',
                'accent_color'      => '#0000FF',
                'logo_shape'        => 'circle',
                'dark_mode_default' => false,
            ])
            ->assertSessionHasErrors('primary_color');
    }

    // ── Display Update ─────────────────────────────────

    public function test_admin_can_update_display_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.display'), [
                'show_queue_name'    => false,
                'show_service_name'  => true,
                'show_wait_time'     => false,
                'show_recent_count'  => 10,
                'announcement_text'  => 'Horario especial hoy',
                'call_sound'         => 'bell',
            ])
            ->assertRedirect();

        $this->tenant->refresh();
        $this->assertFalse($this->tenant->setting('display.show_queue_name'));
        $this->assertEquals(10, $this->tenant->setting('display.show_recent_count'));
        $this->assertEquals('Horario especial hoy', $this->tenant->setting('display.announcement_text'));
    }

    // ── Kiosk Update ───────────────────────────────────

    public function test_admin_can_update_kiosk_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.kiosk'), [
                'welcome_text'         => 'Bienvenido a San Rafael',
                'show_priority_option' => true,
                'show_estimated_wait'  => false,
                'print_ticket'         => true,
            ])
            ->assertRedirect();

        $this->tenant->refresh();
        $this->assertEquals('Bienvenido a San Rafael', $this->tenant->setting('kiosk.welcome_text'));
        $this->assertTrue($this->tenant->setting('kiosk.show_priority_option'));
    }

    // ── Tickets Update ─────────────────────────────────

    public function test_admin_can_update_ticket_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.tickets'), [
                'prefix'             => 'CSR',
                'daily_reset'        => false,
                'auto_close_minutes' => 60,
                'no_show_minutes'    => 10,
            ])
            ->assertRedirect();

        $this->tenant->refresh();
        $this->assertEquals('CSR', $this->tenant->setting('tickets.prefix'));
        $this->assertFalse($this->tenant->setting('tickets.daily_reset'));
        $this->assertEquals(60, $this->tenant->setting('tickets.auto_close_minutes'));
    }

    public function test_ticket_prefix_validates_alphanumeric(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.tickets'), [
                'prefix'             => 'A-B',
                'daily_reset'        => true,
                'auto_close_minutes' => 120,
                'no_show_minutes'    => 15,
            ])
            ->assertSessionHasErrors('prefix');
    }

    // ── Logo Upload ────────────────────────────────────

    public function test_admin_can_upload_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.settings.logo.upload'), [
                'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
            ])
            ->assertRedirect();

        $this->tenant->refresh();
        $this->assertNotNull($this->tenant->logo_url);
        Storage::disk('public')->assertExists($this->tenant->logo_url);
    }

    public function test_admin_can_remove_logo(): void
    {
        Storage::fake('public');

        // First upload
        $this->actingAs($this->admin)
            ->post(route('admin.settings.logo.upload'), [
                'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
            ]);

        $this->tenant->refresh();
        $oldPath = $this->tenant->logo_url;

        // Then remove
        $this->actingAs($this->admin)
            ->delete(route('admin.settings.logo.remove'))
            ->assertRedirect();

        $this->tenant->refresh();
        $this->assertNull($this->tenant->logo_url);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_logo_upload_rejects_non_images(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.settings.logo.upload'), [
                'logo' => UploadedFile::fake()->create('document.pdf', 500),
            ])
            ->assertSessionHasErrors('logo');
    }

    // ── Section Merge (doesn't clobber other sections) ──

    public function test_updating_one_section_preserves_others(): void
    {
        // First set branding
        $this->actingAs($this->admin)
            ->put(route('admin.settings.branding'), [
                'primary_color'     => '#FF0000',
                'secondary_color'   => '#00FF00',
                'accent_color'      => '#0000FF',
                'logo_shape'        => 'square',
                'dark_mode_default' => true,
            ]);

        // Then update kiosk — branding should remain untouched
        $this->actingAs($this->admin)
            ->put(route('admin.settings.kiosk'), [
                'welcome_text'         => 'Hola',
                'show_priority_option' => true,
                'show_estimated_wait'  => true,
                'print_ticket'         => false,
            ]);

        $this->tenant->refresh();
        $this->assertEquals('#FF0000', $this->tenant->setting('branding.primary_color'));
        $this->assertEquals('square', $this->tenant->setting('branding.logo_shape'));
        $this->assertEquals('Hola', $this->tenant->setting('kiosk.welcome_text'));
    }
}
