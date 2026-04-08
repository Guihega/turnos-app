<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ProductionSeeder — Creates the base tenant and admin user for production.
 *
 * Configure via .env:
 *   PILOT_TENANT_NAME="Nombre del Cliente"
 *   PILOT_ADMIN_NAME="Nombre Admin"
 *   PILOT_ADMIN_EMAIL="admin@cliente.com"
 *   PILOT_ADMIN_PASSWORD="SecurePassword123!"
 *
 * Usage:
 *   php artisan db:seed --class=ProductionSeeder
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $tenantName = env('PILOT_TENANT_NAME', 'Mi Empresa');
        $adminName = env('PILOT_ADMIN_NAME', 'Administrador');
        $adminEmail = env('PILOT_ADMIN_EMAIL', 'admin@empresa.com');
        $adminPassword = env('PILOT_ADMIN_PASSWORD', 'Olinora2026!');

        $this->command->info("╔══════════════════════════════════════════╗");
        $this->command->info("║  Olinora — Production Seeder            ║");
        $this->command->info("╚══════════════════════════════════════════╝");
        $this->command->info('');

        // ── Create Tenant ──
        $tenant = Tenant::firstOrCreate(
            ['slug' => Str::slug($tenantName)],
            [
                'id' => (string) Str::ulid(),
                'name' => $tenantName,
                'slug' => Str::slug($tenantName),
                'legal_name' => $tenantName,
                'email' => $adminEmail,
                'phone' => '',
                'timezone' => 'America/Mexico_City',
                'locale' => 'es',
                'plan' => 'basic',
                'is_active' => true,
                'settings' => $this->defaultSettings($tenantName),
            ]
        );

        $this->command->info("✓ Tenant: {$tenant->name} ({$tenant->id})");

        // ── Create Admin User ──
        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'id' => (string) Str::ulid(),
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'tenant_id' => $tenant->id,
                'role' => 'tenant_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("✓ Admin: {$admin->name} <{$admin->email}>");
        $this->command->info('');
        $this->command->info("┌─────────────────────────────────────────┐");
        $this->command->info("│ Acceso al sistema:                      │");
        $this->command->info("│ URL:   https://olinora.com.mx           │");
        $this->command->info("│ Email: {$adminEmail}");
        $this->command->info("│ Pass:  {$adminPassword}");
        $this->command->info("└─────────────────────────────────────────┘");
        $this->command->info('');
        $this->command->info("El admin puede configurar sucursales, servicios,");
        $this->command->info("colas, ventanillas y usuarios desde el panel.");
    }

    private function defaultSettings(string $tenantName): array
    {
        return [
            'branding' => [
                'primary_color' => '#3B82F6',
                'secondary_color' => '#1E40AF',
                'accent_color' => '#10B981',
                'logo_shape' => 'rounded',
                'dark_mode_default' => true,
            ],
            'display' => [
                'show_queue_name' => true,
                'show_service_name' => true,
                'show_wait_time' => true,
                'show_recent_count' => 5,
                'announcement_text' => null,
                'call_sound' => 'default',
            ],
            'kiosk' => [
                'welcome_text' => "Bienvenido a {$tenantName}",
                'show_priority_option' => false,
                'show_estimated_wait' => true,
                'print_ticket' => false,
            ],
            'tickets' => [
                'prefix' => 'T',
                'daily_reset' => true,
                'auto_close_minutes' => 5,
                'no_show_minutes' => 3,
            ],
        ];
    }
}
