<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialiteUser(array $overrides = []): SocialiteUser
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($overrides['id'] ?? '123456789');
        $socialiteUser->shouldReceive('getName')->andReturn($overrides['name'] ?? 'John Doe');
        $socialiteUser->shouldReceive('getEmail')->andReturn($overrides['email'] ?? 'john@example.com');
        $socialiteUser->shouldReceive('getAvatar')->andReturn($overrides['avatar'] ?? 'https://example.com/avatar.jpg');
        $socialiteUser->token = $overrides['token'] ?? 'mock-access-token';
        $socialiteUser->refreshToken = $overrides['refreshToken'] ?? 'mock-refresh-token';
        $socialiteUser->expiresIn = $overrides['expiresIn'] ?? 3600;

        return $socialiteUser;
    }

    private function mockSocialiteDriver(string $provider, SocialiteUser $user): void
    {
        $driver = Mockery::mock(\Laravel\Socialite\Two\AbstractProvider::class);
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(redirect('https://provider.com/auth'));
        $driver->shouldReceive('user')->andReturn($user);

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with($provider)->andReturn($driver);

        $this->app->instance(SocialiteFactory::class, $factory);
    }

    // ─── Redirect Tests ────────────────────────────────────

    public function test_redirect_to_google(): void
    {
        $this->mockSocialiteDriver('google', $this->mockSocialiteUser());

        $response = $this->get(route('social.redirect', ['provider' => 'google']));
        $response->assertRedirect();
    }

    public function test_redirect_to_facebook(): void
    {
        $this->mockSocialiteDriver('facebook', $this->mockSocialiteUser());

        $response = $this->get(route('social.redirect', ['provider' => 'facebook']));
        $response->assertRedirect();
    }

    public function test_redirect_with_invalid_provider_returns_404(): void
    {
        $response = $this->get('/auth/twitter/redirect');
        $response->assertNotFound();
    }

    // ─── Callback: Login existente ─────────────────────────

    public function test_callback_logs_in_user_with_linked_social_account(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $socialAccount = new SocialAccount();
        $socialAccount->user_id = $user->id;
        $socialAccount->provider = 'google';
        $socialAccount->provider_id = '123456789';
        $socialAccount->provider_email = $user->email;
        $socialAccount->save();

        $socialiteUser = $this->mockSocialiteUser(['email' => $user->email]);
        $this->mockSocialiteDriver('google', $socialiteUser);

        $response = $this->get(route('social.callback', ['provider' => 'google']));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    // ─── Callback: Vincular auto con email existente ───────

    public function test_callback_links_and_logs_in_existing_user_by_email(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'existing@example.com',
        ]);

        $socialiteUser = $this->mockSocialiteUser(['email' => 'existing@example.com']);
        $this->mockSocialiteDriver('google', $socialiteUser);

        $response = $this->get(route('social.callback', ['provider' => 'google']));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '123456789',
        ]);
    }

    // ─── Callback: Nuevo usuario → Onboarding ─────────────

    public function test_callback_redirects_new_user_to_onboarding(): void
    {
        $socialiteUser = $this->mockSocialiteUser(['email' => 'new@example.com']);
        $this->mockSocialiteDriver('google', $socialiteUser);

        $response = $this->get(route('social.callback', ['provider' => 'google']));

        $response->assertRedirect(route('onboarding'));
        $response->assertSessionHas('social_registration');

        $socialData = session('social_registration');
        $this->assertEquals('google', $socialData['provider']);
        $this->assertEquals('new@example.com', $socialData['email']);
        $this->assertEquals('John Doe', $socialData['name']);
    }

    // ─── Link desde Perfil ─────────────────────────────────

    public function test_authenticated_user_can_link_social_account(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $socialiteUser = $this->mockSocialiteUser(['email' => 'social@example.com']);
        $this->mockSocialiteDriver('google', $socialiteUser);

        $this->actingAs($user);

        $response = $this->get(route('social.link.callback', ['provider' => 'google']));

        $response->assertRedirect(route('profile.edit'));
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => '123456789',
        ]);
    }

    public function test_cannot_link_social_account_already_linked_to_another_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user1 = User::factory()->create(['tenant_id' => $tenant->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant->id]);

        $socialAccount = new SocialAccount();
        $socialAccount->user_id = $user1->id;
        $socialAccount->provider = 'google';
        $socialAccount->provider_id = '123456789';
        $socialAccount->provider_email = $user1->email;
        $socialAccount->save();

        $socialiteUser = $this->mockSocialiteUser();
        $this->mockSocialiteDriver('google', $socialiteUser);

        $this->actingAs($user2);

        $response = $this->get(route('social.link.callback', ['provider' => 'google']));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $user2->id,
            'provider' => 'google',
        ]);
    }

    public function test_cannot_link_duplicate_provider(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $socialAccount = new SocialAccount();
        $socialAccount->user_id = $user->id;
        $socialAccount->provider = 'google';
        $socialAccount->provider_id = '111111111';
        $socialAccount->provider_email = $user->email;
        $socialAccount->save();

        $socialiteUser = $this->mockSocialiteUser(['id' => '999999999']);
        $this->mockSocialiteDriver('google', $socialiteUser);

        $this->actingAs($user);

        $response = $this->get(route('social.link.callback', ['provider' => 'google']));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('error');
    }

    // ─── Unlink desde Perfil ───────────────────────────────

    public function test_authenticated_user_can_unlink_social_account(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => bcrypt('password'), // Tiene password, puede desvincular
        ]);

        $socialAccount = new SocialAccount();
        $socialAccount->user_id = $user->id;
        $socialAccount->provider = 'google';
        $socialAccount->provider_id = '123456789';
        $socialAccount->provider_email = $user->email;
        $socialAccount->save();

        $this->actingAs($user);

        $response = $this->delete(route('social.unlink', ['provider' => 'google']));

        $response->assertRedirect(route('profile.edit'));
        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    public function test_cannot_unlink_only_auth_method(): void
    {
        $tenant = Tenant::factory()->create();
        // Crear usuario con password vacío (simula registro social-only)
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => '',
        ]);

        $socialAccount = new SocialAccount();
        $socialAccount->user_id = $user->id;
        $socialAccount->provider = 'google';
        $socialAccount->provider_id = '123456789';
        $socialAccount->provider_email = $user->email;
        $socialAccount->save();

        $this->actingAs($user);

        $response = $this->delete(route('social.unlink', ['provider' => 'google']));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('error');
        // La cuenta social debe seguir existiendo
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    // ─── Invalid provider ──────────────────────────────────

    public function test_callback_with_invalid_provider_returns_404(): void
    {
        $response = $this->get('/auth/twitter/callback');
        $response->assertNotFound();
    }

    public function test_unlink_invalid_provider_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);

        $response = $this->delete('/auth/twitter/unlink');
        $response->assertNotFound();
    }

    // ─── Guest protection ──────────────────────────────────

    public function test_link_requires_authentication(): void
    {
        $response = $this->get(route('social.link', ['provider' => 'google']));
        $response->assertRedirect(route('login'));
    }

    public function test_unlink_requires_authentication(): void
    {
        $response = $this->delete(route('social.unlink', ['provider' => 'google']));
        $response->assertRedirect(route('login'));
    }
}
