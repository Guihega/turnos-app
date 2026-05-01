<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplaySecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Branch $branchA;

    private Branch $branchB;

    private User $operatorA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id, 'is_active' => true]);
        $this->branchB = Branch::factory()->create(['tenant_id' => $this->tenantB->id, 'is_active' => true]);

        $this->operatorA = User::factory()->operator()->create(['tenant_id' => $this->tenantA->id]);
        $this->operatorA->branches()->attach($this->branchA->id, ['role' => 'operator']);
    }

    // ══════════════════════════════════════════════════════════════
    // AUTHENTICATED DISPLAY ACCESS
    // ══════════════════════════════════════════════════════════════

    public function test_operator_can_access_own_branch_display(): void
    {
        $response = $this->actingAs($this->operatorA)->get(route('display.show', $this->branchA));
        $response->assertOk();
    }

    public function test_operator_cannot_access_other_tenant_branch_display(): void
    {
        $response = $this->actingAs($this->operatorA)->get(route('display.show', $this->branchB));
        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_display_index(): void
    {
        $response = $this->get(route('display.index'));
        $response->assertRedirect(route('login'));
    }

    // ══════════════════════════════════════════════════════════════
    // PUBLIC DISPLAY
    // ══════════════════════════════════════════════════════════════

    public function test_public_display_accessible_without_auth(): void
    {
        $response = $this->get(route('display.public', $this->branchA));
        $response->assertOk();
    }

    public function test_public_display_rejects_inactive_branch(): void
    {
        $this->branchA->update(['is_active' => false]);

        $response = $this->get(route('display.public', $this->branchA));
        $response->assertNotFound();
    }

    public function test_public_display_rejects_inactive_tenant(): void
    {
        $this->tenantA->update(['is_active' => false]);

        $response = $this->get(route('display.public', $this->branchA));
        $response->assertNotFound();
    }

    // ══════════════════════════════════════════════════════════════
    // KIOSK PUBLIC ACCESS
    // ══════════════════════════════════════════════════════════════

    public function test_kiosk_accessible_without_auth(): void
    {
        $response = $this->get(route('kiosk.public', $this->branchA));
        $response->assertOk();
    }

    public function test_kiosk_returns_branch_data(): void
    {
        $response = $this->get(route('kiosk.public', $this->branchA));
        $response->assertOk();

        $props = $response->original->getData()['page']['props'];
        $this->assertEquals($this->branchA->id, $props['branch']['id']);
        $this->assertArrayHasKey('is_open', $props['branch']);
        $this->assertArrayHasKey('services', $props);
        $this->assertArrayHasKey('waitingCount', $props);
    }

    // ══════════════════════════════════════════════════════════════
    // REGISTRATION DISABLED
    // ══════════════════════════════════════════════════════════════

    public function test_register_get_returns_404(): void
    {
        $response = $this->get('/register');
        $response->assertNotFound();
    }

    public function test_register_post_returns_404(): void
    {
        $response = $this->post('/register', [
            'name' => 'Attacker',
            'email' => 'attacker@evil.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'attacker@evil.com']);
    }
}
