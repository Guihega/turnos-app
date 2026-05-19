import { Head, router } from '@inertiajs/react';

// ─── Design Tokens (mirror Onboarding/Register.jsx) ───
const T = {
    blue:    '#2563eb',
    blueDk:  '#1d4ed8',
    blueLt:  '#dbeafe',
    green:   '#16a34a',
    gray500: '#6b7280',
    gray600: '#4b5563',
    gray700: '#374151',
    gray900: '#111827',
    card:    '#ffffff',
    bg:      '#f8fafc',
    sans:    "'Inter', system-ui, -apple-system, sans-serif",
    radius:  '12px',
    shadowMd:'0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06)',
};

function formatTrialEnd(iso) {
    if (!iso) return null;
    try {
        const d = new Date(iso);
        return new Intl.DateTimeFormat('es-MX', {
            day: 'numeric', month: 'long', year: 'numeric',
        }).format(d);
    } catch {
        return null;
    }
}

function intervalLabel(interval) {
    if (interval === 'month') return 'mensual';
    if (interval === 'year') return 'anual';
    return null;
}

export default function Confirmation({
    tenantName = null,
    tenantSlug = null,
    planName = null,
    interval = null,
    trialEndsAt = null,
}) {
    const trialFormatted = formatTrialEnd(trialEndsAt);
    const intervalText = intervalLabel(interval);

    return (
        <>
            <Head title="Cuenta creada — Olinora" />
            <div style={{
                minHeight: '100vh',
                background: `linear-gradient(135deg, ${T.bg} 0%, ${T.blueLt} 100%)`,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '32px 16px',
                fontFamily: T.sans,
            }}>
                <div style={{ width: '100%', maxWidth: 520 }}>

                    <div style={{
                        background: T.card,
                        borderRadius: T.radius,
                        padding: '40px 32px',
                        boxShadow: T.shadowMd,
                        textAlign: 'center',
                    }}>

                        {/* Success icon */}
                        <div style={{
                            width: 64, height: 64,
                            borderRadius: '50%',
                            background: T.green,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            margin: '0 auto 20px',
                        }}>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 13l4 4L19 7" stroke="#fff" strokeWidth="3"
                                    strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </div>

                        <h1 style={{
                            fontSize: 26, fontWeight: 800, color: T.gray900,
                            margin: 0, marginBottom: 8,
                        }}>
                            ¡Bienvenido{tenantName ? ` a ${tenantName}` : ''}!
                        </h1>
                        <p style={{
                            color: T.gray600, fontSize: 16,
                            margin: 0, marginBottom: 24,
                        }}>
                            Tu cuenta ha sido creada exitosamente.
                        </p>

                        {/* Summary */}
                        {(planName || trialFormatted || tenantSlug) && (
                            <div style={{
                                background: T.bg,
                                borderRadius: 10,
                                padding: 20,
                                textAlign: 'left',
                                marginBottom: 28,
                            }}>
                                {planName && (
                                    <div style={{ marginBottom: 10 }}>
                                        <div style={{ fontSize: 12, color: T.gray500, fontWeight: 600 }}>
                                            PLAN
                                        </div>
                                        <div style={{ fontSize: 15, color: T.gray900, fontWeight: 600 }}>
                                            {planName}{intervalText ? ` · ${intervalText}` : ''}
                                        </div>
                                    </div>
                                )}
                                {trialFormatted && (
                                    <div style={{ marginBottom: 10 }}>
                                        <div style={{ fontSize: 12, color: T.gray500, fontWeight: 600 }}>
                                            PRUEBA GRATUITA HASTA
                                        </div>
                                        <div style={{ fontSize: 15, color: T.gray900, fontWeight: 600 }}>
                                            {trialFormatted}
                                        </div>
                                    </div>
                                )}
                                {tenantSlug && (
                                    <div>
                                        <div style={{ fontSize: 12, color: T.gray500, fontWeight: 600 }}>
                                            IDENTIFICADOR
                                        </div>
                                        <div style={{ fontSize: 15, color: T.gray900, fontWeight: 600 }}>
                                            {tenantSlug}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        <button
                            type="button"
                            onClick={() => router.visit('/dashboard')}
                            style={{
                                width: '100%',
                                padding: '14px 16px',
                                background: T.blue,
                                color: '#fff',
                                border: 'none',
                                borderRadius: 8,
                                fontWeight: 700,
                                fontSize: 16,
                                cursor: 'pointer',
                                transition: 'background 0.15s',
                            }}
                        >
                            Ir al panel de control
                        </button>

                    </div>

                </div>
            </div>
        </>
    );
}
