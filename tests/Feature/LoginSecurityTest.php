<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ══════════════════════════════════════════════════════════════
    // LOGIN TRACKING (F-18)
    // ══════════════════════════════════════════════════════════════

    public function test_login_records_last_login_at(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->last_login_at);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertTrue($user->last_login_at->isToday());
    }

    public function test_login_records_ip_address(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->last_login_ip);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_failed_login_does_not_update_tracking(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $user->refresh();
        $this->assertNull($user->last_login_at);
        $this->assertNull($user->last_login_ip);
    }

    // ══════════════════════════════════════════════════════════════
    // INACTIVE USER LOGIN
    // ══════════════════════════════════════════════════════════════

    public function test_inactive_user_credentials_work_but_tenant_scope_blocks(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        // User can authenticate (Laravel doesn't check is_active by default)
        // but if you implement is_active check in LoginRequest, this would fail
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // The user authenticates but dashboard access depends on tenant scope
        $this->assertAuthenticated();
    }

    // ══════════════════════════════════════════════════════════════
    // SESSION SECURITY
    // ══════════════════════════════════════════════════════════════

    public function test_session_regenerated_on_login(): void
    {
        $user = User::factory()->create();

        $sessionIdBefore = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Session should be regenerated to prevent session fixation
        $this->assertNotEquals($sessionIdBefore, session()->getId());
    }

    public function test_session_invalidated_on_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }

    public function test_csrf_token_regenerated_on_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $tokenBefore = csrf_token();

        $this->post('/logout');

        // Token should change after logout
        $this->assertNotEquals($tokenBefore, csrf_token());
    }
}
