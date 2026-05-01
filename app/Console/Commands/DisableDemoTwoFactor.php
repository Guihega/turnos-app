<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Desactiva 2FA de la cuenta demo para permitir login directo sin TOTP.
 *
 * Uso: php artisan demo:disable-2fa
 *      php artisan demo:disable-2fa --email=otro@ejemplo.com
 *
 * Este comando es seguro correr múltiples veces (idempotente).
 * Solo afecta a la cuenta especificada — las demás cuentas
 * mantienen su configuración de 2FA intacta.
 *
 * NOTA TÉCNICA:
 * Este comando usa whereBlind() porque los emails en User están
 * cifrados con CipherSweet. Una búsqueda normal where('email', ...)
 * no funciona porque compara contra el valor cifrado en BD.
 */
class DisableDemoTwoFactor extends Command
{
    protected $signature = 'demo:disable-2fa 
                            {--email=admin@empresa.com : Email de la cuenta demo}';

    protected $description = 'Desactiva 2FA de la cuenta demo para permitir acceso público sin TOTP';

    public function handle(): int
    {
        $email = $this->option('email');

        // Usar whereBlind porque el email está cifrado con CipherSweet
        $user = User::whereBlind('email', 'email_index', $email)->first();

        if (! $user) {
            $this->error("No se encontró el usuario con email: {$email}");
            $this->line('');
            $this->warn('Nota: los emails en User están cifrados con CipherSweet.');
            $this->line('Si estás seguro que la cuenta existe, verifica:');
            $this->line('  php artisan tinker');
            $this->line("  App\\Models\\User::whereBlind('email', 'email_index', '{$email}')->first();");

            return self::FAILURE;
        }

        $this->info("Usuario encontrado: {$user->name} ({$user->email})");
        $this->line("ID: {$user->id}");
        $this->line('Rol: '.($user->role->value ?? 'N/A'));
        $this->line('2FA actual: '.($user->two_factor_confirmed_at ? 'Activado' : 'Desactivado'));

        if (! $user->two_factor_confirmed_at) {
            $this->warn('El 2FA ya está desactivado. No se requieren cambios.');

            return self::SUCCESS;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->refresh();

        // Validar que realmente se desactivó
        if ($user->two_factor_confirmed_at !== null) {
            $this->error('Error: 2FA no se desactivó correctamente.');

            return self::FAILURE;
        }

        $this->info('✓ 2FA desactivado exitosamente para la cuenta demo.');
        $this->line('');
        $this->line('Siguiente paso: probar login en ventana incógnita.');
        $this->line('URL: '.config('app.url').'/login');
        $this->line('');
        $this->line('Los campos deben venir pre-llenados con las credenciales.');
        $this->line('Al hacer clic en "Iniciar Sesión" debe entrar al dashboard directo.');

        return self::SUCCESS;
    }
}
