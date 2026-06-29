import { useState } from 'react';
import { Head, router } from '@inertiajs/react';

// ─── Design Tokens (mirror Onboarding/Register.jsx) ───
const T = {
    blue:    '#2563eb',
    blueDk:  '#1d4ed8',
    blueLt:  '#dbeafe',
    green:   '#16a34a',
    red:     '#dc2626',
    gray50:  '#f9fafb',
    gray100: '#f3f4f6',
    gray200: '#e5e7eb',
    gray300: '#d1d5db',
    gray400: '#9ca3af',
    gray500: '#6b7280',
    gray600: '#4b5563',
    gray700: '#374151',
    gray800: '#1f2937',
    gray900: '#111827',
    card:    '#ffffff',
    bg:      '#f8fafc',
    sans:    "'Inter', system-ui, -apple-system, sans-serif",
    radius:  '12px',
    shadow:  '0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)',
    shadowMd:'0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06)',
};

function formatPriceMxn(cents) {
    const pesos = cents / 100;
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(pesos);
}

export default function Select({ plans = [] }) {
    const [interval, setInterval] = useState('month');

    const intervalLabel = interval === 'month' ? '/mes' : '/año';

    const handleChoose = (code) => {
        router.visit(`/checkout/signup?plan=${encodeURIComponent(code)}&interval=${encodeURIComponent(interval)}`);
    };

    return (
        <>
            <Head title="Elige tu plan — Olinora" />
            <div style={{
                minHeight: '100vh',
                background: `linear-gradient(135deg, ${T.bg} 0%, ${T.blueLt} 100%)`,
                padding: '48px 16px',
                fontFamily: T.sans,
            }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>

                    {/* Brand */}
                    <div style={{ textAlign: 'center', marginBottom: 32 }}>
                        <a href="/" style={{ textDecoration: 'none' }}>
                            <h1 style={{
                                fontSize: 28, fontWeight: 800, color: T.blue,
                                letterSpacing: '-0.02em', margin: 0,
                            }}>Olinora</h1>
                        </a>
                        <p style={{ color: T.gray600, marginTop: 8, fontSize: 16 }}>
                            Elige el plan que mejor se adapte a tu operación
                        </p>
                    </div>

                    {/* Interval toggle */}
                    <div style={{
                        display: 'flex',
                        justifyContent: 'center',
                        marginBottom: 40,
                    }}>
                        <div style={{
                            display: 'inline-flex',
                            background: T.card,
                            borderRadius: T.radius,
                            padding: 4,
                            boxShadow: T.shadow,
                            border: `1px solid ${T.gray200}`,
                        }}>
                            {['month', 'year'].map((opt) => {
                                const isActive = interval === opt;
                                return (
                                    <button
                                        key={opt}
                                        type="button"
                                        onClick={() => setInterval(opt)}
                                        style={{
                                            padding: '10px 24px',
                                            background: isActive ? T.blue : 'transparent',
                                            color: isActive ? '#fff' : T.gray700,
                                            border: 'none',
                                            borderRadius: 8,
                                            fontWeight: 600,
                                            fontSize: 14,
                                            cursor: 'pointer',
                                            transition: 'all 0.15s',
                                        }}
                                    >
                                        {opt === 'month' ? 'Mensual' : 'Anual'}
                                        {opt === 'year' && (
                                            <span style={{
                                                marginLeft: 8,
                                                fontSize: 11,
                                                padding: '2px 8px',
                                                background: isActive ? '#fff' : T.green,
                                                color: isActive ? T.green : '#fff',
                                                borderRadius: 999,
                                                fontWeight: 700,
                                            }}>
                                                2 meses gratis
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Plans grid */}
                    <div style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
                        gap: 24,
                        marginBottom: 32,
                    }}>
                        {plans.map((plan) => {
                            const price = plan.prices.find((p) => p.interval === interval);
                            const amountCents = price ? price.amount_cents : null;
                            const isFree = amountCents === 0;
                            const hasPrice = amountCents !== null;

                            return (
                                <div
                                    key={plan.code}
                                    style={{
                                        background: T.card,
                                        borderRadius: T.radius,
                                        padding: 28,
                                        boxShadow: T.shadowMd,
                                        border: `1px solid ${T.gray200}`,
                                        display: 'flex',
                                        flexDirection: 'column',
                                    }}
                                >
                                    <h3 style={{
                                        fontSize: 20, fontWeight: 700, color: T.gray900,
                                        margin: 0, marginBottom: 8,
                                    }}>{plan.name}</h3>

                                    {plan.description && (
                                        <p style={{
                                            color: T.gray600, fontSize: 14,
                                            margin: 0, marginBottom: 20, minHeight: 40,
                                        }}>{plan.description}</p>
                                    )}

                                    <div style={{ marginBottom: 20 }}>
                                        {hasPrice ? (
                                            isFree ? (
                                                <div style={{
                                                    fontSize: 28, fontWeight: 800,
                                                    color: T.green,
                                                }}>Gratis</div>
                                            ) : (
                                                <>
                                                    <span style={{
                                                        fontSize: 32, fontWeight: 800,
                                                        color: T.gray900,
                                                    }}>{formatPriceMxn(amountCents)}</span>
                                                    <span style={{
                                                        fontSize: 14, color: T.gray500,
                                                        marginLeft: 6,
                                                    }}>{intervalLabel}</span>
                                                </>
                                            )
                                        ) : (
                                            <span style={{ color: T.gray400, fontSize: 14 }}>
                                                Precio no disponible
                                            </span>
                                        )}
                                    </div>

                                    <div style={{
                                        fontSize: 12, color: T.gray600,
                                        background: T.blueLt, padding: '8px 12px',
                                        borderRadius: 8, marginBottom: 20,
                                        textAlign: 'center', fontWeight: 600,
                                    }}>
                                        14 días de prueba gratis
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => handleChoose(plan.code)}
                                        disabled={!hasPrice}
                                        style={{
                                            marginTop: 'auto',
                                            padding: '12px 16px',
                                            background: hasPrice ? T.blue : T.gray300,
                                            color: '#fff',
                                            border: 'none',
                                            borderRadius: 8,
                                            fontWeight: 600,
                                            fontSize: 15,
                                            cursor: hasPrice ? 'pointer' : 'not-allowed',
                                            transition: 'background 0.15s',
                                        }}
                                    >
                                        Elegir {plan.name}
                                    </button>
                                </div>
                            );
                        })}
                    </div>

                    {/* Footer */}
                    <div style={{ textAlign: 'center', color: T.gray600, fontSize: 14 }}>
                        ¿Ya tienes cuenta?{' '}
                        <a href="/login" style={{ color: T.blue, fontWeight: 600, textDecoration: 'none' }}>
                            Inicia sesión
                        </a>
                    </div>

                </div>
            </div>
        </>
    );
}
