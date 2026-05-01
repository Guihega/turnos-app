<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    /**
     * Show the 2FA challenge form.
     */
    public function create(Request $request)
    {
        // Only accessible if user passed password auth but hasn't verified 2FA yet
        if (! $request->session()->has('two_factor:user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verify the 2FA code or recovery code.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $userId = $request->session()->get('two_factor:user_id');
        $remember = $request->session()->get('two_factor:remember', false);

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user || ! $user->two_factor_secret) {
            $request->session()->forget(['two_factor:user_id', 'two_factor:remember']);

            return redirect()->route('login');
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        // Try TOTP code first
        if ($request->filled('code')) {
            $google2fa = new Google2FA;

            if (! $google2fa->verifyKey($secret, $request->code)) {
                return back()->withErrors(['code' => 'El código es inválido.']);
            }
        }
        // Try recovery code
        elseif ($request->filled('recovery_code')) {
            if (! $this->useRecoveryCode($user, $request->recovery_code)) {
                return back()->withErrors(['recovery_code' => 'El código de recuperación es inválido.']);
            }
        } else {
            return back()->withErrors(['code' => 'Ingresa un código de verificación.']);
        }

        // Clear 2FA session data
        $request->session()->forget(['two_factor:user_id', 'two_factor:remember']);

        // Complete login
        Auth::login($user, $remember);

        $request->session()->regenerate();

        // Track login metadata
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Validate and consume a recovery code.
     */
    private function useRecoveryCode(User $user, string $code): bool
    {
        if (! $user->two_factor_recovery_codes) {
            return false;
        }

        $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

        if (! in_array($code, $codes)) {
            return false;
        }

        // Remove used code
        $remaining = array_values(array_diff($codes, [$code]));

        $user->update([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($remaining)),
        ]);

        return true;
    }
}
