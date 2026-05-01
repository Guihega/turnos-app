<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $this->google2fa = new Google2FA;
    }

    // ── Enable 2FA ──

    public function test_user_can_start_2fa_setup(): void
    {
        $response = $this->actingAs($this->user)->post(route('two-factor.enable'));

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_confirmed_at);
    }

    public function test_user_can_confirm_2fa_with_valid_code(): void
    {
        // Start setup
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
        ]);

        // Generate valid code
        $code = $this->google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($this->user)->post(route('two-factor.confirm'), [
            'code' => $code,
        ]);

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_confirmed_at);
        $this->assertNotNull($this->user->two_factor_recovery_codes);
    }

    public function test_invalid_code_does_not_confirm_2fa(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
        ]);

        $response = $this->actingAs($this->user)->post(route('two-factor.confirm'), [
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors('code');

        $this->user->refresh();
        $this->assertNull($this->user->two_factor_confirmed_at);
    }

    public function test_recovery_codes_are_generated_on_confirm(): void
    {
        $secret = $this->google2fa->generateSecretKey();
        $this->user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
        ]);

        $code = $this->google2fa->getCurrentOtp($secret);

        $this->actingAs($this->user)->post(route('two-factor.confirm'), [
            'code' => $code,
        ]);

        $this->user->refresh();
        $codes = json_decode(Crypt::decryptString($this->user->two_factor_recovery_codes), true);
        $this->assertCount(8, $codes);
    }

    // ── Disable 2FA ──

    public function test_user_can_disable_2fa_with_password(): void
    {
        $this->enableTwoFactor();

        $response = $this->actingAs($this->user)->post(route('two-factor.disable'), [
            'password' => 'password',
        ]);

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_confirmed_at);
        $this->assertNull($this->user->two_factor_recovery_codes);
    }

    public function test_cannot_disable_2fa_with_wrong_password(): void
    {
        $this->enableTwoFactor();

        $response = $this->actingAs($this->user)->post(route('two-factor.disable'), [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_confirmed_at);
    }

    // ── Login with 2FA ──

    public function test_login_redirects_to_challenge_when_2fa_enabled(): void
    {
        $this->enableTwoFactor();

        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
    }

    public function test_login_goes_to_dashboard_when_2fa_not_enabled(): void
    {
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_challenge_page_requires_session(): void
    {
        $response = $this->get(route('two-factor.challenge'));

        $response->assertRedirect(route('login'));
    }

    public function test_valid_totp_code_completes_login(): void
    {
        $secret = $this->enableTwoFactor();

        // Start login (will redirect to challenge)
        $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        // Verify with TOTP
        $code = $this->google2fa->getCurrentOtp($secret);

        $response = $this->post(route('two-factor.challenge.verify'), [
            'code' => $code,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_invalid_totp_code_fails(): void
    {
        $this->enableTwoFactor();

        $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response = $this->post(route('two-factor.challenge.verify'), [
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_valid_recovery_code_completes_login(): void
    {
        $this->enableTwoFactor();

        // Get a recovery code
        $codes = json_decode(Crypt::decryptString($this->user->two_factor_recovery_codes), true);
        $recoveryCode = $codes[0];

        // Start login
        $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        // Use recovery code
        $response = $this->post(route('two-factor.challenge.verify'), [
            'recovery_code' => $recoveryCode,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);

        // Recovery code should be consumed
        $this->user->refresh();
        $remainingCodes = json_decode(Crypt::decryptString($this->user->two_factor_recovery_codes), true);
        $this->assertCount(7, $remainingCodes);
        $this->assertNotContains($recoveryCode, $remainingCodes);
    }

    public function test_invalid_recovery_code_fails(): void
    {
        $this->enableTwoFactor();

        $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response = $this->post(route('two-factor.challenge.verify'), [
            'recovery_code' => 'invalid-code-here',
        ]);

        $response->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    // ── Helpers ──

    /**
     * Enable and confirm 2FA for the test user.
     * Returns the plaintext secret for generating codes.
     */
    private function enableTwoFactor(): string
    {
        $secret = $this->google2fa->generateSecretKey();

        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = Str::random(10).'-'.Str::random(10);
        }

        $this->user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
        ]);

        return $secret;
    }
}
