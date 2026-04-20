<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Enable 2FA: generate secret, return QR code data.
     * User still needs to confirm with a valid code before 2FA is active.
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        // Generate new secret
        $secret = $this->google2fa->generateSecretKey();

        // Store encrypted secret (not yet confirmed)
        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        // Generate QR code URL for Google Authenticator
        $qrUrl = $this->google2fa->getQRCodeUrl(
            config('app.name', 'Olinora'),
            $user->email,
            $secret
        );

        return back()->with('twoFactor', [
            'qrUrl' => $qrUrl,
            'secret' => $secret,
            'showSetup' => true,
        ]);
    }

    /**
     * Confirm 2FA setup with a valid TOTP code.
     * This activates 2FA and generates recovery codes.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return back()->withErrors(['code' => 'Primero debes iniciar la configuración de 2FA.']);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        if (! $this->google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => 'El código es inválido. Verifica e intenta de nuevo.']);
        }

        // Generate recovery codes
        $recoveryCodes = Collection::times(8, fn () => Str::random(10) . '-' . Str::random(10));

        $user->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString($recoveryCodes->toJson()),
        ]);

        return back()->with('twoFactor', [
            'recoveryCodes' => $recoveryCodes->all(),
            'showRecoveryCodes' => true,
        ]);
    }

    /**
     * Disable 2FA. Requires current password.
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('success', 'Autenticación en dos pasos desactivada.');
    }
}
