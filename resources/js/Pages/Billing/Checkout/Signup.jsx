import { useState, useEffect, useRef } from 'react';
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

function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

function formatPriceMxn(cents) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency', currency: 'MXN',
        minimumFractionDigits: 0, maximumFractionDigits: 0,
    }).format(cents / 100);
}

function slugifyBranchCode(name) {
    return name
        .toUpperCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 10);
}

function slugifyTenant(name) {
    return name
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 63);
}

// ─── Section block ───
function Section({ title, children }) {
    return (
        <div style={{ marginBottom: 28 }}>
            <h3 style={{
                fontSize: 13, fontWeight: 700, color: T.gray500,
                textTransform: 'uppercase', letterSpacing: '0.06em',
                margin: 0, marginBottom: 12,
            }}>{title}</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                {children}
            </div>
        </div>
    );
}

// ─── Input field ───
function Field({ label, error, children }) {
    return (
        <label style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <span style={{ fontSize: 13, fontWeight: 600, color: T.gray700 }}>{label}</span>
            {children}
            {error && (
                <span style={{ fontSize: 12, color: T.red, fontWeight: 500 }}>{error}</span>
            )}
        </label>
    );
}

const inputStyle = (hasError) => ({
    width: '100%',
    padding: '10px 12px',
    fontSize: 14,
    fontFamily: T.sans,
    color: T.gray900,
    background: T.card,
    border: `1px solid ${hasError ? T.red : T.gray300}`,
    borderRadius: 8,
    outline: 'none',
    boxSizing: 'border-box',
});

export default function Signup({ plan, price, errors = {} }) {
    const [data, setData] = useState({
        plan_code: plan?.code ?? '',
        interval: price?.interval ?? 'month',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        company_name: '',
        slug: '',
        branch_name: '',
        branch_code: '',
    });

    const [processing, setProcessing] = useState(false);
    const [slugStatus, setSlugStatus] = useState({ checking: false, available: null });
    const slugCheckRef = useRef(null);

    // Auto-derive slug from company_name (only if user hasn't typed manually)
    const slugTouchedRef = useRef(false);
    useEffect(() => {
        if (!slugTouchedRef.current && data.company_name) {
            setData((d) => ({ ...d, slug: slugifyTenant(d.company_name) }));
        }
    }, [data.company_name]);

    // Auto-derive branch_code from branch_name
    useEffect(() => {
        if (data.branch_name) {
            setData((d) => ({ ...d, branch_code: slugifyBranchCode(d.branch_name) }));
        }
    }, [data.branch_name]);

    // Live slug availability check
    useEffect(() => {
        if (!data.slug || data.slug.length < 2) {
            setSlugStatus({ checking: false, available: null });
            return;
        }
        if (!slugCheckRef.current) {
            slugCheckRef.current = debounce(async (slug) => {
                try {
                    setSlugStatus({ checking: true, available: null });
                    const res = await fetch(`/onboarding/check-slug?slug=${encodeURIComponent(slug)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const json = await res.json();
                    setSlugStatus({ checking: false, available: !!json.available });
                } catch {
                    setSlugStatus({ checking: false, available: null });
                }
            }, 400);
        }
        slugCheckRef.current(data.slug);
    }, [data.slug]);

    const setField = (key) => (e) => {
        const value = e.target.value;
        if (key === 'slug') slugTouchedRef.current = true;
        setData((d) => ({ ...d, [key]: value }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post('/checkout', data, {
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title="Crea tu cuenta — Olinora" />
            <div style={{
                minHeight: '100vh',
                background: `linear-gradient(135deg, ${T.bg} 0%, ${T.blueLt} 100%)`,
                padding: '32px 16px',
                fontFamily: T.sans,
            }}>
                <div style={{ maxWidth: 560, margin: '0 auto' }}>

                    {/* Brand */}
                    <div style={{ textAlign: 'center', marginBottom: 24 }}>
                        <a href="/checkout" style={{ textDecoration: 'none' }}>
                            <h1 style={{
                                fontSize: 24, fontWeight: 800, color: T.blue,
                                letterSpacing: '-0.02em', margin: 0,
                            }}>Olinora</h1>
                        </a>
                    </div>

                    {/* Plan banner */}
                    {plan && price && (
                        <div style={{
                            background: T.card,
                            borderRadius: T.radius,
                            padding: 16,
                            marginBottom: 24,
                            boxShadow: T.shadow,
                            border: `1px solid ${T.blueLt}`,
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            gap: 12,
                        }}>
                            <div>
                                <div style={{ fontSize: 12, color: T.gray500, fontWeight: 600 }}>
                                    PLAN ELEGIDO
                                </div>
                                <div style={{ fontSize: 16, fontWeight: 700, color: T.gray900, marginTop: 2 }}>
                                    {plan.name}
                                    {price.amount_cents > 0 && (
                                        <span style={{ color: T.gray600, fontWeight: 500, marginLeft: 8 }}>
                                            · {formatPriceMxn(price.amount_cents)}/{price.interval === 'month' ? 'mes' : 'año'}
                                        </span>
                                    )}
                                </div>
                            </div>
                            <a href="/checkout" style={{
                                fontSize: 13, color: T.blue, fontWeight: 600,
                                textDecoration: 'none', whiteSpace: 'nowrap',
                            }}>Cambiar</a>
                        </div>
                    )}

                    {/* Form card */}
                    <form onSubmit={handleSubmit} style={{
                        background: T.card,
                        borderRadius: T.radius,
                        padding: 32,
                        boxShadow: T.shadowMd,
                        border: `1px solid ${T.gray200}`,
                    }}>
                        <h2 style={{
                            fontSize: 22, fontWeight: 700, color: T.gray900,
                            margin: 0, marginBottom: 4,
                        }}>Crea tu cuenta</h2>
                        <p style={{ color: T.gray600, fontSize: 14, margin: 0, marginBottom: 24 }}>
                            14 días de prueba gratis. Sin tarjeta.
                        </p>

                        <Section title="Tu cuenta">
                            <Field label="Nombre completo" error={errors.name}>
                                <input type="text" value={data.name} onChange={setField('name')}
                                    style={inputStyle(!!errors.name)} required />
                            </Field>
                            <Field label="Correo electrónico" error={errors.email}>
                                <input type="email" value={data.email} onChange={setField('email')}
                                    style={inputStyle(!!errors.email)} required />
                            </Field>
                            <Field label="Contraseña" error={errors.password}>
                                <input type="password" value={data.password} onChange={setField('password')}
                                    style={inputStyle(!!errors.password)} required />
                            </Field>
                            <Field label="Confirmar contraseña" error={errors.password_confirmation}>
                                <input type="password" value={data.password_confirmation}
                                    onChange={setField('password_confirmation')}
                                    style={inputStyle(!!errors.password_confirmation)} required />
                            </Field>
                        </Section>

                        <Section title="Tu empresa">
                            <Field label="Nombre de la empresa" error={errors.company_name}>
                                <input type="text" value={data.company_name} onChange={setField('company_name')}
                                    style={inputStyle(!!errors.company_name)} required />
                            </Field>
                            <Field
                                label="Identificador (slug)"
                                error={
                                    errors.slug ||
                                    (slugStatus.available === false ? 'Este identificador ya está en uso' : null)
                                }
                            >
                                <input type="text" value={data.slug} onChange={setField('slug')}
                                    style={inputStyle(!!errors.slug || slugStatus.available === false)}
                                    pattern="[a-z0-9]([a-z0-9\-]*[a-z0-9])?" required />
                                <span style={{ fontSize: 12, color: T.gray500 }}>
                                    {slugStatus.checking ? 'Verificando…' :
                                     slugStatus.available === true ? '✓ Disponible' :
                                     'tudominio.olinora.com/' + (data.slug || '...')}
                                </span>
                            </Field>
                        </Section>

                        <Section title="Tu primera sucursal">
                            <Field label="Nombre de la sucursal" error={errors.branch_name}>
                                <input type="text" value={data.branch_name} onChange={setField('branch_name')}
                                    style={inputStyle(!!errors.branch_name)}
                                    placeholder="Ej: Sucursal Centro" required />
                            </Field>
                        </Section>

                        <button type="submit" disabled={processing || slugStatus.available === false}
                            style={{
                                width: '100%',
                                padding: '14px 16px',
                                background: (processing || slugStatus.available === false) ? T.gray400 : T.blue,
                                color: '#fff', border: 'none', borderRadius: 8,
                                fontWeight: 700, fontSize: 16,
                                cursor: (processing || slugStatus.available === false) ? 'not-allowed' : 'pointer',
                                transition: 'background 0.15s',
                            }}>
                            {processing ? 'Creando cuenta…' : 'Crear cuenta y empezar prueba'}
                        </button>

                        <p style={{
                            fontSize: 12, color: T.gray500, textAlign: 'center',
                            marginTop: 16, marginBottom: 0,
                        }}>
                            Al continuar aceptas nuestros{' '}
                            <a href="/terms" style={{ color: T.blue }}>Términos</a> y{' '}
                            <a href="/privacy" style={{ color: T.blue }}>Privacidad</a>.
                        </p>
                    </form>

                    <div style={{ textAlign: 'center', marginTop: 24, color: T.gray600, fontSize: 14 }}>
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
