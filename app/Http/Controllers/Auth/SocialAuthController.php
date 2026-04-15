<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialAuthController extends Controller
{
    /**
     * Providers permitidos.
     */
    private const ALLOWED_PROVIDERS = ['google', 'facebook'];

    /**
     * Redirigir al provider OAuth.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (! $this->isValidProvider($provider)) {
            abort(404);
        }

        // Guardar la intención (login o onboarding) en session
        session()->put('social_auth_action', request('action', 'login'));

        return $this->buildDriver($provider)->redirect();
    }

    /**
     * Callback del provider OAuth.
     */
    public function callback(string $provider): RedirectResponse
    {
        if (! $this->isValidProvider($provider)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException $e) {
            Log::warning("OAuth invalid state for {$provider}", ['error' => $e->getMessage()]);

            return redirect()->route('login')
                ->with('error', 'La autenticación fue cancelada o expiró. Intenta de nuevo.');
        } catch (\Exception $e) {
            Log::error("OAuth error for {$provider}", ['error' => $e->getMessage()]);

            return redirect()->route('login')
                ->with('error', 'Hubo un error al conectar con ' . ucfirst($provider) . '. Intenta de nuevo.');
        }

        // Buscar si ya existe una cuenta social vinculada
        $socialAccount = SocialAccount::findByProvider($provider, $socialUser->getId());

        if ($socialAccount) {
            // Login directo — ya tiene cuenta vinculada
            return $this->loginExistingUser($socialAccount, $socialUser);
        }

        // Buscar si existe un usuario con ese email
        $existingUser = User::where('email', $socialUser->getEmail())->first();

        if ($existingUser) {
            // Vincular automáticamente y hacer login
            return $this->linkAndLogin($existingUser, $provider, $socialUser);
        }

        // No existe — determinar acción
        $action = session()->pull('social_auth_action', 'login');

        if ($action === 'onboarding') {
            // Redirigir al onboarding con datos pre-llenados
            return $this->redirectToOnboarding($provider, $socialUser);
        }

        // Desde login, redirigir a onboarding con datos pre-llenados
        return $this->redirectToOnboarding($provider, $socialUser);
    }

    /**
     * Vincular cuenta social desde Perfil (usuario autenticado).
     */
    public function link(string $provider): RedirectResponse
    {
        if (! $this->isValidProvider($provider)) {
            abort(404);
        }

        session()->put('social_auth_action', 'link');

        return $this->buildDriver($provider)->redirect();
    }

    /**
     * Callback de vinculación desde Perfil.
     */
    public function linkCallback(string $provider): RedirectResponse
    {
        if (! $this->isValidProvider($provider)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            Log::error("OAuth link error for {$provider}", ['error' => $e->getMessage()]);

            return redirect()->route('profile.edit')
                ->with('error', 'No se pudo conectar con ' . ucfirst($provider) . '.');
        }

        $user = Auth::user();

        // Verificar que no esté ya vinculada a otro usuario
        $existing = SocialAccount::findByProvider($provider, $socialUser->getId());

        if ($existing && $existing->user_id !== $user->id) {
            return redirect()->route('profile.edit')
                ->with('error', 'Esta cuenta de ' . ucfirst($provider) . ' ya está vinculada a otro usuario.');
        }

        // Verificar que el usuario no tenga ya una cuenta de este provider
        if ($user->socialAccounts()->where('provider', $provider)->exists()) {
            return redirect()->route('profile.edit')
                ->with('error', 'Ya tienes una cuenta de ' . ucfirst($provider) . ' vinculada.');
        }

        // Crear vinculación
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_email' => $socialUser->getEmail(),
            'provider_avatar' => $socialUser->getAvatar(),
            'provider_token' => [
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_in' => $socialUser->expiresIn,
            ],
        ]);

        return redirect()->route('profile.edit')
            ->with('success', 'Cuenta de ' . ucfirst($provider) . ' vinculada exitosamente.');
    }

    /**
     * Desvincular cuenta social desde Perfil.
     */
    public function unlink(Request $request, string $provider): RedirectResponse
    {
        if (! $this->isValidProvider($provider)) {
            abort(404);
        }

        $user = $request->user();

        // No permitir desvincular si es el único método de autenticación.
        // Un password vacío ('') o null significa que no tiene password establecido.
        $hasPassword = $user->password !== null
            && $user->password !== ''
            && ! Hash::check('', $user->password);
        $socialCount = $user->socialAccounts()->count();

        if (! $hasPassword && $socialCount <= 1) {
            return redirect()->route('profile.edit')
                ->with('error', 'No puedes desvincular tu única forma de inicio de sesión. Establece una contraseña primero.');
        }

        $user->socialAccounts()->where('provider', $provider)->delete();

        return redirect()->route('profile.edit')
            ->with('success', 'Cuenta de ' . ucfirst($provider) . ' desvinculada.');
    }

    /**
     * Build the Socialite driver with proper scopes.
     *
     * Facebook: setScopes([]) overrides Socialite's default 'email' scope
     * which is rejected by Facebook Graph API v23+.
     * Google: uses standard OpenID Connect scopes.
     */
    private function buildDriver(string $provider)
    {
        $driver = Socialite::driver($provider);

        if ($provider === 'facebook') {
            // setScopes() REPLACES all scopes (including Socialite's default 'email')
            // Facebook Graph API v23+ rejects 'email' as scope — it's included by default
            $driver->setScopes([]);
        } else {
            // scopes() ADDS to existing scopes
            $driver->scopes($this->getScopes($provider));
        }

        return $driver;
    }

    /**
     * Login de usuario existente con cuenta social vinculada.
     */
    private function loginExistingUser(SocialAccount $socialAccount, $socialUser): RedirectResponse
    {
        // Actualizar token
        $socialAccount->update([
            'provider_token' => [
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_in' => $socialUser->expiresIn,
            ],
            'provider_avatar' => $socialUser->getAvatar(),
        ]);

        $user = $socialAccount->user;

        // Actualizar login tracking
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Vincular cuenta social a usuario existente y hacer login.
     */
    private function linkAndLogin(User $user, string $provider, $socialUser): RedirectResponse
    {
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_email' => $socialUser->getEmail(),
            'provider_avatar' => $socialUser->getAvatar(),
            'provider_token' => [
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_in' => $socialUser->expiresIn,
            ],
        ]);

        // Actualizar login tracking
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Redirigir al onboarding con datos del provider.
     */
    private function redirectToOnboarding(string $provider, $socialUser): RedirectResponse
    {
        // Guardar datos en session para pre-llenar el onboarding
        session()->put('social_registration', [
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'token' => [
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_in' => $socialUser->expiresIn,
            ],
        ]);

        return redirect()->route('onboarding')
            ->with('info', 'Completa tu registro para continuar.');
    }

    private function isValidProvider(string $provider): bool
    {
        return in_array($provider, self::ALLOWED_PROVIDERS);
    }

    private function getScopes(string $provider): array
    {
        return match ($provider) {
            'google' => ['openid', 'profile', 'email'],
            'facebook' => [],
            default => [],
        };
    }
}
