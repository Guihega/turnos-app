import { useState, useCallback, useEffect, useRef } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import SocialAuthButtons from '@/Components/SocialAuthButtons';

// Debounce simple sin dependencia de lodash
function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

// ─── Design Tokens (from TurnosUI v4) ───
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
    mono:    "'JetBrains Mono', 'Fira Code', monospace",
    sans:    "'Inter', system-ui, -apple-system, sans-serif",
    radius:  '12px',
    shadow:  '0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)',
    shadowMd:'0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06)',
};

// ─── Step Indicator ───
function StepIndicator({ current, steps }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, marginBottom: 32 }}>
            {steps.map((label, i) => {
                const step = i + 1;
                const isActive = step === current;
                const isDone = step < current;
                return (
                    <div key={step} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <div style={{
                            width: 36, height: 36, borderRadius: '50%',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontSize: 14, fontWeight: 600,
                            background: isDone ? T.green : isActive ? T.blue : T.gray200,
                            color: isDone || isActive ? '#fff' : T.gray500,
                            transition: 'all 0.3s ease',
                        }}>
                            {isDone ? '✓' : step}
                        </div>
                        <span style={{
                            fontSize: 13, fontWeight: isActive ? 600 : 400,
                            color: isActive ? T.gray900 : T.gray500,
                            display: 'none',
                            ...(typeof window !== 'undefined' && window.innerWidth > 640 ? { display: 'inline' } : {}),
                        }}>{label}</span>
                        {i < steps.length - 1 && (
                            <div style={{
                                width: 40, height: 2,
                                background: isDone ? T.green : T.gray200,
                                borderRadius: 1,
                                transition: 'background 0.3s ease',
                            }} />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

// ─── Input Field ───
function Field({ label, name, type = 'text', value, onChange, error, hint, suffix, disabled, autoFocus, maxLength, readOnly }) {
    return (
        <div style={{ marginBottom: 20 }}>
            <label style={{ display: 'block', fontSize: 13, fontWeight: 600, color: T.gray700, marginBottom: 6 }}>
                {label}
            </label>
            <div style={{ position: 'relative' }}>
                <input
                    type={type}
                    name={name}
                    value={value}
                    onChange={onChange}
                    disabled={disabled}
                    readOnly={readOnly}
                    autoFocus={autoFocus}
                    maxLength={maxLength}
                    autoComplete={type === 'password' ? 'new-password' : name}
                    style={{
                        width: '100%',
                        padding: '10px 14px',
                        paddingRight: suffix ? 140 : 14,
                        fontSize: 15,
                        border: `1.5px solid ${error ? T.red : T.gray300}`,
                        borderRadius: 8,
                        outline: 'none',
                        transition: 'border-color 0.2s',
                        fontFamily: T.sans,
                        color: T.gray900,
                        background: disabled || readOnly ? T.gray100 : '#fff',
                        cursor: readOnly ? 'not-allowed' : 'text',
                        boxSizing: 'border-box',
                    }}
                    onFocus={(e) => { if (!readOnly) e.target.style.borderColor = error ? T.red : T.blue; }}
                    onBlur={(e) => e.target.style.borderColor = error ? T.red : T.gray300}
                />
                {suffix && (
                    <span style={{
                        position: 'absolute', right: 14, top: '50%', transform: 'translateY(-50%)',
                        fontSize: 13, color: T.gray400, fontFamily: T.mono,
                    }}>{suffix}</span>
                )}
            </div>
            {hint && !error && (
                <p style={{ fontSize: 12, color: T.gray500, marginTop: 4, marginBottom: 0 }}>{hint}</p>
            )}
            {error && (
                <p style={{ fontSize: 12, color: T.red, marginTop: 4, marginBottom: 0 }}>{error}</p>
            )}
        </div>
    );
}

// ─── Step 1: Account ───
function StepAccount({ data, setData, errors, socialData }) {
    const isSocial = !!socialData;

    return (
        <div>
            <h2 style={{ fontSize: 22, fontWeight: 700, color: T.gray900, margin: '0 0 4px' }}>
                Crea tu cuenta
            </h2>
            <p style={{ fontSize: 14, color: T.gray500, margin: '0 0 24px' }}>
                Serás el administrador de tu organización.
            </p>

            {/* Social auth buttons — solo si NO viene de OAuth */}
            {!isSocial && (
                <SocialAuthButtons action="onboarding" />
            )}

            {/* Badge de provider si viene de OAuth */}
            {isSocial && (
                <div style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    padding: '10px 14px',
                    borderRadius: 8,
                    background: '#f0fdf4',
                    border: '1px solid #bbf7d0',
                    color: '#16a34a',
                    fontSize: 13,
                    fontWeight: 500,
                    marginBottom: 20,
                }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M9 12l2 2 4-4"/>
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                    Conectado con {socialData.provider === 'google' ? 'Google' : 'Facebook'}
                    {socialData.avatar && (
                        <img
                            src={socialData.avatar}
                            alt=""
                            style={{ width: 20, height: 20, borderRadius: '50%', marginLeft: 'auto' }}
                        />
                    )}
                </div>
            )}

            <Field
                label="Nombre completo"
                name="name"
                value={data.name}
                onChange={e => setData('name', e.target.value)}
                error={errors.name}
                autoFocus={!isSocial}
            />
            <Field
                label="Correo electrónico"
                name="email"
                type="email"
                value={data.email}
                onChange={e => setData('email', e.target.value)}
                error={errors.email}
                readOnly={isSocial}
            />
            {!isSocial && (
                <>
                    <Field
                        label="Contraseña"
                        name="password"
                        type="password"
                        value={data.password}
                        onChange={e => setData('password', e.target.value)}
                        error={errors.password}
                        hint="Mínimo 8 caracteres"
                    />
                    <Field
                        label="Confirmar contraseña"
                        name="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={e => setData('password_confirmation', e.target.value)}
                        error={errors.password_confirmation}
                    />
                </>
            )}
        </div>
    );
}

// ─── Step 2: Company ───
function StepCompany({ data, setData, errors, slugStatus }) {
    const generateSlug = (name) => {
        return name
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '')
            .substring(0, 63);
    };

    const handleCompanyName = (e) => {
        const name = e.target.value;
        setData('company_name', name);
        if (!data._slugManuallyEdited) {
            setData('slug', generateSlug(name));
        }
    };

    const handleSlugChange = (e) => {
        const raw = e.target.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');
        setData('slug', raw);
        setData('_slugManuallyEdited', true);
    };

    const slugHint = slugStatus === 'checking' ? '⏳ Verificando disponibilidad...'
        : slugStatus === 'available' ? '✅ Disponible'
        : slugStatus === 'taken' ? null // handled as error
        : data.slug ? `Tu kiosco será: olinora.com.mx/t/${data.slug}` : null;

    const slugError = errors.slug || (slugStatus === 'taken' ? 'Este identificador ya está en uso' : null);

    return (
        <div>
            <h2 style={{ fontSize: 22, fontWeight: 700, color: T.gray900, margin: '0 0 4px' }}>
                Tu empresa
            </h2>
            <p style={{ fontSize: 14, color: T.gray500, margin: '0 0 24px' }}>
                Configura tu espacio en Olinora.
            </p>
            <Field
                label="Nombre de la empresa"
                name="company_name"
                value={data.company_name}
                onChange={handleCompanyName}
                error={errors.company_name}
                autoFocus
            />
            <Field
                label="Identificador (slug)"
                name="slug"
                value={data.slug}
                onChange={handleSlugChange}
                error={slugError}
                hint={slugHint}
                maxLength={63}
            />
        </div>
    );
}

// ─── Step 3: Branch ───
function StepBranch({ data, setData, errors }) {
    return (
        <div>
            <h2 style={{ fontSize: 22, fontWeight: 700, color: T.gray900, margin: '0 0 4px' }}>
                Primera sucursal
            </h2>
            <p style={{ fontSize: 14, color: T.gray500, margin: '0 0 24px' }}>
                Puedes agregar más sucursales después.
            </p>
            <Field
                label="Nombre de la sucursal"
                name="branch_name"
                value={data.branch_name}
                onChange={e => setData('branch_name', e.target.value)}
                error={errors.branch_name}
                hint='Ej: "Sucursal Centro", "Matriz", "Oficina Principal"'
                autoFocus
            />
            <Field
                label="Código"
                name="branch_code"
                value={data.branch_code}
                onChange={e => setData('branch_code', e.target.value.toUpperCase().replace(/[^A-Z0-9\-]/g, ''))}
                error={errors.branch_code}
                hint='Código corto para identificar la sucursal. Ej: "CTR", "MAT-01"'
                maxLength={10}
            />
        </div>
    );
}

// ─── Main Wizard ───
export default function Register({ socialData }) {
    const { errors } = usePage().props;
    const [step, setStep] = useState(1);
    const [processing, setProcessing] = useState(false);
    const [slugStatus, setSlugStatus] = useState(null); // null | 'checking' | 'available' | 'taken'

    const isSocial = !!socialData;

    const [data, _setData] = useState({
        // Step 1
        name: socialData?.name || '',
        email: socialData?.email || '',
        password: '',
        password_confirmation: '',
        // Step 2
        company_name: '',
        slug: '',
        _slugManuallyEdited: false,
        // Step 3
        branch_name: '',
        branch_code: '',
    });

    const setData = (key, value) => {
        _setData(prev => ({ ...prev, [key]: value }));
    };

    // Debounced slug availability check
    const checkSlug = useCallback(
        debounce(async (slug) => {
            if (!slug || slug.length < 2) {
                setSlugStatus(null);
                return;
            }
            setSlugStatus('checking');
            try {
                const response = await fetch(`/onboarding/check-slug?slug=${encodeURIComponent(slug)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (response.ok) {
                    const result = await response.json();
                    setSlugStatus(result.available ? 'available' : 'taken');
                } else {
                    setSlugStatus(null);
                }
            } catch {
                setSlugStatus(null);
            }
        }, 500),
        []
    );

    useEffect(() => {
        if (step === 2 && data.slug) {
            checkSlug(data.slug);
        }
    }, [data.slug, step]);

    // Client-side step validation
    const canProceed = () => {
        if (step === 1) {
            if (isSocial) {
                // Si viene de social, solo necesita nombre y email
                return data.name.trim() && data.email.trim();
            }
            return data.name.trim() && data.email.trim() && data.password.length >= 8 && data.password === data.password_confirmation;
        }
        if (step === 2) {
            return data.company_name.trim() && data.slug.trim() && data.slug.length >= 2 && slugStatus !== 'taken';
        }
        if (step === 3) {
            return data.branch_name.trim() && data.branch_code.trim();
        }
        return false;
    };

    const next = () => {
        if (step < 3 && canProceed()) setStep(step + 1);
    };

    const prev = () => {
        if (step > 1) setStep(step - 1);
    };

    const submit = () => {
        if (!canProceed() || processing) return;
        setProcessing(true);

        // Preparar datos para enviar
        const submitData = {
            name: data.name,
            email: data.email,
            company_name: data.company_name,
            slug: data.slug,
            branch_name: data.branch_name,
            branch_code: data.branch_code,
        };

        // Solo incluir password si no es social o si el usuario lo llenó
        if (!isSocial || data.password) {
            submitData.password = data.password;
            submitData.password_confirmation = data.password_confirmation;
        }

        // Submit all data via Inertia
        router.post('/onboarding', submitData, {
            onFinish: () => setProcessing(false),
            onError: (errors) => {
                // Navigate to the step that has errors
                if (errors.name || errors.email || errors.password || errors.password_confirmation) {
                    setStep(1);
                } else if (errors.company_name || errors.slug) {
                    setStep(2);
                } else if (errors.branch_name || errors.branch_code) {
                    setStep(3);
                }
            },
        });
    };

    const steps = ['Cuenta', 'Empresa', 'Sucursal'];

    return (
        <>
            <Head title="Crear cuenta — Olinora" />
            <div style={{
                minHeight: '100vh',
                background: `linear-gradient(135deg, ${T.bg} 0%, ${T.blueLt} 100%)`,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '24px 16px',
                fontFamily: T.sans,
            }}>
                <div style={{ width: '100%', maxWidth: 480 }}>
                    {/* Logo / Brand */}
                    <div style={{ textAlign: 'center', marginBottom: 32 }}>
                        <a href="/" style={{ textDecoration: 'none' }}>
                            <h1 style={{
                                fontSize: 28, fontWeight: 800, color: T.blue,
                                letterSpacing: '-0.02em', margin: 0,
                            }}>
                                Olinora
                            </h1>
                        </a>
                        <p style={{ fontSize: 14, color: T.gray500, marginTop: 4 }}>
                            Gestión de turnos inteligente
                        </p>
                    </div>

                    {/* Card */}
                    <div style={{
                        background: T.card,
                        borderRadius: T.radius,
                        boxShadow: T.shadowMd,
                        padding: '32px 28px',
                        border: `1px solid ${T.gray200}`,
                    }}>
                        <StepIndicator current={step} steps={steps} />

                        {/* Step Content */}
                        {step === 1 && <StepAccount data={data} setData={setData} errors={errors} socialData={socialData} />}
                        {step === 2 && <StepCompany data={data} setData={setData} errors={errors} slugStatus={slugStatus} />}
                        {step === 3 && <StepBranch data={data} setData={setData} errors={errors} />}

                        {/* Navigation */}
                        <div style={{
                            display: 'flex',
                            justifyContent: step > 1 ? 'space-between' : 'flex-end',
                            marginTop: 28,
                            gap: 12,
                        }}>
                            {step > 1 && (
                                <button
                                    type="button"
                                    onClick={prev}
                                    style={{
                                        padding: '10px 20px',
                                        fontSize: 14, fontWeight: 600,
                                        color: T.gray600,
                                        background: T.gray100,
                                        border: `1px solid ${T.gray300}`,
                                        borderRadius: 8,
                                        cursor: 'pointer',
                                        transition: 'all 0.2s',
                                        fontFamily: T.sans,
                                    }}
                                    onMouseEnter={e => e.target.style.background = T.gray200}
                                    onMouseLeave={e => e.target.style.background = T.gray100}
                                >
                                    ← Atrás
                                </button>
                            )}
                            {step < 3 ? (
                                <button
                                    type="button"
                                    onClick={next}
                                    disabled={!canProceed()}
                                    style={{
                                        padding: '10px 24px',
                                        fontSize: 14, fontWeight: 600,
                                        color: '#fff',
                                        background: canProceed() ? T.blue : T.gray300,
                                        border: 'none',
                                        borderRadius: 8,
                                        cursor: canProceed() ? 'pointer' : 'not-allowed',
                                        transition: 'all 0.2s',
                                        fontFamily: T.sans,
                                    }}
                                    onMouseEnter={e => { if (canProceed()) e.target.style.background = T.blueDk; }}
                                    onMouseLeave={e => { if (canProceed()) e.target.style.background = T.blue; }}
                                >
                                    Siguiente →
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={submit}
                                    disabled={!canProceed() || processing}
                                    style={{
                                        padding: '10px 28px',
                                        fontSize: 14, fontWeight: 600,
                                        color: '#fff',
                                        background: canProceed() && !processing ? T.green : T.gray300,
                                        border: 'none',
                                        borderRadius: 8,
                                        cursor: canProceed() && !processing ? 'pointer' : 'not-allowed',
                                        transition: 'all 0.2s',
                                        fontFamily: T.sans,
                                    }}
                                >
                                    {processing ? 'Creando...' : '🚀 Crear mi cuenta'}
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Footer link */}
                    <p style={{ textAlign: 'center', fontSize: 13, color: T.gray500, marginTop: 20 }}>
                        ¿Ya tienes cuenta?{' '}
                        <a href="/login" style={{ color: T.blue, textDecoration: 'none', fontWeight: 600 }}>
                            Inicia sesión
                        </a>
                    </p>
                </div>
            </div>
        </>
    );
}
