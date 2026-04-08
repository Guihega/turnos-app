<?php

namespace App\Models\Concerns;

trait HasTenantSettings
{
    /**
     * Default settings structure.
     * Merged with stored settings so new keys are always available.
     */
    public static function defaultSettings(): array
    {
        return [
            'branding' => [
                'primary_color'     => '#3B82F6',
                'secondary_color'   => '#8B5CF6',
                'accent_color'      => '#10B981',
                'logo_shape'        => 'rounded',
                'dark_mode_default' => true,
            ],
            'display' => [
                'show_queue_name'    => true,
                'show_service_name'  => true,
                'show_wait_time'     => true,
                'show_recent_count'  => 5,
                'announcement_text'  => null,
                'call_sound'         => 'chime',
            ],
            'kiosk' => [
                'welcome_text'         => 'Bienvenido',
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

    /**
     * Get merged settings (stored values + defaults for any missing keys).
     */
    public function getEffectiveSettings(): array
    {
        return array_replace_recursive(
            static::defaultSettings(),
            $this->settings ?? []
        );
    }

    /**
     * Get a specific setting with dot notation.
     * Always falls back to default if not set.
     *
     * Usage: $tenant->setting('branding.primary_color')
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $effective = $this->getEffectiveSettings();

        return data_get($effective, $key, $default);
    }

    /**
     * Update a specific settings section (e.g., 'branding', 'display').
     * Merges with existing settings rather than replacing everything.
     */
    public function updateSettingsSection(string $section, array $values): void
    {
        $current = $this->settings ?? [];
        $current[$section] = array_merge(
            static::defaultSettings()[$section] ?? [],
            $current[$section] ?? [],
            $values
        );

        $this->update(['settings' => $current]);
    }

    /**
     * Replace all settings at once (for full-form save).
     */
    public function replaceSettings(array $settings): void
    {
        $this->update(['settings' => array_replace_recursive(
            static::defaultSettings(),
            $settings
        )]);
    }

    /**
     * Get branding settings for frontend consumption.
     * Includes logo_url from the model itself.
     */
    public function getBrandingForFrontend(): array
    {
        $effective = $this->getEffectiveSettings();

        return [
            'name'       => $this->name,
            'logo_url'   => $this->logo_url ? asset('storage/' . $this->logo_url) : null,
            'branding'   => $effective['branding'],
            'display'    => $effective['display'],
            'kiosk'      => $effective['kiosk'],
            'tickets'    => $effective['tickets'],
        ];
    }
}
