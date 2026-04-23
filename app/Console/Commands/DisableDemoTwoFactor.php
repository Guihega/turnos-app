<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Desactiva 2FA de la cuenta demo para permitir login directo sin TOTP.
 *
 * Uso: php artisan demo:disable-2fa
 *
 * Este comando es seguro correr múltiples veces (idempotente).
 * Solo afecta a la cuenta admin@empresa.com — las demás cuentas
 * mantienen su configuración de 2FA intacta.
 */
class DisableDemoTwoFactor extends Command
{
    protected $signature = 'demo:disable-2fa {email=admin@empresa.com : Email de la cuenta demo}';

    protected $description = 'Desactiva 2FA de la cuenta demo para permitir acceso público sin TOTP';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No se encontró el usuario con email: {$email}");
            return self::FAILURE;
        }

        $this->info("Usuario encontrado: {$user->name} ({$user->email})");
        $this->line("Rol: {$user->role->value}");
        $this->line("2FA actual: " . ($user->two_factor_confirmed_at ? 'Activado' : 'Desactivado'));

        if (! $user->two_factor_confirmed_at) {
            $this->warn('El 2FA ya está desactivado. No se requieren cambios.');
            return self::SUCCESS;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info('✓ 2FA desactivado exitosamente para la cuenta demo.');
        $this->line('');
        $this->line('Siguiente paso: probar login en ventana incógnita.');
        $this->line('URL: ' . config('app.url') . '/login');

        return self::SUCCESS;
    }
}
