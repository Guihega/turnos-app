import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

/**
 * Hook to access tenant branding from Inertia shared data.
 *
 * For authenticated pages:
 *   Uses `tenantBranding` from HandleInertiaRequests share().
 *
 * For public pages (Screen, Kiosk):
 *   Uses `branding` prop passed explicitly by the controller.
 *
 * Usage:
 *   const { branding, display, kiosk, tickets, logoUrl, tenantName } = useTenantBranding();
 */
export default function useTenantBranding() {
    const { props } = usePage();

    return useMemo(() => {
        // Public pages pass branding directly as prop
        const source = props.tenantBranding || props.branding || {};

        return {
            tenantName: source.name || 'Olinora',
            logoUrl: source.logo_url || null,
            branding: source.branding || {},
            display: source.display || {},
            kiosk: source.kiosk || {},
            tickets: source.tickets || {},

            // Convenience: CSS variables for inline style injection
            cssVars: {
                '--tenant-primary': source.branding?.primary_color || '#3B82F6',
                '--tenant-secondary': source.branding?.secondary_color || '#8B5CF6',
                '--tenant-accent': source.branding?.accent_color || '#10B981',
            },
        };
    }, [props.tenantBranding, props.branding]);
}
