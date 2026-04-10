<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TenantSettingsController extends Controller
{
    /**
     * Show the customization panel.
     */
    public function edit(Request $request)
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('Admin/TenantSettings', [
            'tenant'   => $tenant->only('id', 'name', 'slug', 'logo_url'),
            'settings' => $tenant->getEffectiveSettings(),
            'logoUrl'  => $tenant->logo_url ? asset('storage/' . $tenant->logo_url) : null,
        ]);
    }

    /**
     * Update branding settings.
     */
    public function updateBranding(Request $request)
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'primary_color'     => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color'   => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color'      => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_shape'        => ['required', Rule::in(['rounded', 'square', 'circle'])],
            'dark_mode_default' => ['required', 'boolean'],
        ]);

        $tenant->updateSettingsSection('branding', $validated);

        return back()->with('success', 'Configuración de marca actualizada.');
    }

    /**
     * Update display/screen settings.
     */
    public function updateDisplay(Request $request)
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'show_queue_name'    => ['required', 'boolean'],
            'show_service_name'  => ['required', 'boolean'],
            'show_wait_time'     => ['required', 'boolean'],
            'show_recent_count'  => ['required', 'integer', 'min:1', 'max:20'],
            'announcement_text'  => ['nullable', 'string', 'max:500'],
            'call_sound'         => ['required', Rule::in(['chime', 'bell', 'ding', 'none'])],
        ]);

        $tenant->updateSettingsSection('display', $validated);

        return back()->with('success', 'Configuración de pantalla actualizada.');
    }

    /**
     * Update kiosk settings.
     */
    public function updateKiosk(Request $request)
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'welcome_text'         => ['required', 'string', 'max:200'],
            'show_priority_option' => ['required', 'boolean'],
            'show_estimated_wait'  => ['required', 'boolean'],
            'print_ticket'         => ['required', 'boolean'],
        ]);

        $tenant->updateSettingsSection('kiosk', $validated);

        return back()->with('success', 'Configuración de kiosco actualizada.');
    }

    /**
     * Update ticket settings.
     */
    public function updateTickets(Request $request)
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'prefix'             => ['required', 'string', 'max:5', 'alpha_num'],
            'daily_reset'        => ['required', 'boolean'],
            'auto_close_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'no_show_minutes'    => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $tenant->updateSettingsSection('tickets', $validated);

        return back()->with('success', 'Configuración de turnos actualizada.');
    }

    /**
     * Update security / rate-limiting settings.
     */
    public function updateSecurity(Request $request)
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'max_tickets_per_hour'      => ['required', 'integer', 'min:10', 'max:500'],
            'max_tickets_per_ip_minute' => ['required', 'integer', 'min:1', 'max:30'],
            'max_concurrent_waiting'    => ['required', 'integer', 'min:5', 'max:200'],
            'max_daily_tickets'         => ['required', 'integer', 'min:50', 'max:5000'],
            'bot_protection'            => ['required', 'boolean'],
            'require_customer_name'     => ['required', 'boolean'],
        ]);

        $tenant->updateSettingsSection('security', $validated);

        return back()->with('success', 'Configuración de seguridad actualizada.');
    }

    /**
     * Upload tenant logo.
     */
    public function uploadLogo(Request $request)
    {
        $tenant = $request->user()->tenant;

        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        if ($tenant->logo_url) {
            Storage::disk('public')->delete($tenant->logo_url);
        }

        $path = $request->file('logo')->store(
            "logos/{$tenant->slug}",
            'public'
        );

        $tenant->update(['logo_url' => $path]);

        return back()->with('success', 'Logo actualizado.');
    }

    /**
     * Remove tenant logo.
     */
    public function removeLogo(Request $request)
    {
        $tenant = $request->user()->tenant;

        if ($tenant->logo_url) {
            Storage::disk('public')->delete($tenant->logo_url);
            $tenant->update(['logo_url' => null]);
        }

        return back()->with('success', 'Logo eliminado.');
    }
}
