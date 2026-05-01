<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    // ── Enforcement for admins ──

    public function test_admin_without_2fa_is_redirected_to_setup(): void
    {
        $admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        // Remove 2FA to test enforcement redirect
        $admin->updateQuietly([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        $response = $this->actingAs($admin->fresh())->get(route('dashboard'));

        $response->assertRedirect(route('two-factor.setup'));
    }

    public function test_admin_with_2fa_can_access_dashboard(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::TENANT_ADMIN,
            'email_verified_at' => now(),
            'two_factor_secret' => Crypt::encryptString('TESTSECRET1234567'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(['code1', 'code2'])),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_operator_without_2fa_can_access_normally(): void
    {
        $operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::OPERATOR,
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => null,
        ]);

        // Operators access their own routes, but let's test they're not redirected
        // by checking they can reach the help page (available to all auth users)
        $response = $this->actingAs($operator)->get(route('help.index'));

        $response->assertOk();
    }

    // ── Setup page ──

    public function test_setup_page_renders_for_admin_without_2fa(): void
    {
        $admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        // Remove 2FA to test setup page renders
        $admin->updateQuietly([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        $response = $this->actingAs($admin->fresh())->get(route('two-factor.setup'));

        $response->assertOk();
    }

    public function test_setup_page_redirects_to_dashboard_if_2fa_already_active(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::TENANT_ADMIN,
            'email_verified_at' => now(),
            'two_factor_secret' => Crypt::encryptString('TESTSECRET1234567'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(['code1'])),
        ]);

        $response = $this->actingAs($admin)->get(route('two-factor.setup'));

        $response->assertRedirect(route('dashboard'));
    }

    // ── Admin cannot disable 2FA ──

    public function test_admin_cannot_disable_2fa(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::TENANT_ADMIN,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(['code1'])),
        ]);

        $response = $this->actingAs($admin)->post(route('two-factor.disable'), [
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('password');

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
    }

    public function test_operator_can_disable_2fa(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::OPERATOR,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(['code1'])),
        ]);

        $response = $this->actingAs($operator)->post(route('two-factor.disable'), [
            'password' => 'password',
        ]);

        $operator->refresh();
        $this->assertNull($operator->two_factor_confirmed_at);
    }
}
