<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function defaultSettings(): array
    {
        return [
            'branding' => [
                'primary_color'    => '#3B82F6',
                'secondary_color'  => '#8B5CF6',
                'accent_color'     => '#10B981',
                'logo_shape'       => 'rounded',   // rounded | square | circle
                'dark_mode_default' => true,
            ],
            'display' => [
                'show_queue_name'    => true,
                'show_service_name'  => true,
                'show_wait_time'     => true,
                'show_recent_count'  => 5,
                'announcement_text'  => null,
                'call_sound'         => 'chime',    // chime | bell | ding | none
            ],
            'kiosk' => [
                'welcome_text'        => 'Bienvenido',
                'show_priority_option' => false,
                'show_estimated_wait'  => true,
                'print_ticket'         => false,
            ],
            'tickets' => [
                'prefix'             => 'A',
                'daily_reset'        => true,
                'auto_close_minutes' => 120,
                'no_show_minutes'    => 15,
            ],
        ];
    }

    public function up(): void
    {
        // Populate settings for any tenants that have null settings
        $tenants = DB::table('tenants')->whereNull('settings')->get();

        foreach ($tenants as $tenant) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['settings' => json_encode($this->defaultSettings())]);
        }
    }

    public function down(): void
    {
        // No-op: we don't want to destroy settings on rollback
    }
};
