// resources/js/Pages/Auth/Login.jsx
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { T } from '@/Components/TurnosUI';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: 'admin@empresa.com', password: 'Olinora2026!', remember: false,
    });
    const [focused, setFocused] = useState(null);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (<>
        <Head title="Iniciar Sesión" />
        <div style={{
            fontFamily: T.font, background: T.bg, color: T.text,
            minHeight: '100vh', display: 'flex', justifyContent: 'center',
            position: 'relative', overflow: 'hidden',
        }}>
            {/* Inner container — caps width on ultrawide */}
            <div style={{
                display: 'flex', width: '100%', maxWidth: 1280,
                margin: '0 auto', position: 'relative',
            }}>

            {/* Background ambient elements */}
            <div style={{
                position: 'absolute', top: '-20%', right: '-10%', width: 600, height: 600,
                borderRadius: '50%', background: `radial-gradient(circle, color-mix(in srgb, ${T.blue} 6%, transparent), transparent 70%)`,
                filter: 'blur(80px)', pointerEvents: 'none',
            }} />
            <div style={{
                position: 'absolute', bottom: '-15%', left: '-5%', width: 500, height: 500,
                borderRadius: '50%', background: `radial-gradient(circle, color-mix(in srgb, ${T.purple} 5%, transparent), transparent 70%)`,
                filter: 'blur(80px)', pointerEvents: 'none',
            }} />

            {/* Left panel — Branding */}
            <div className="login-brand-panel" style={{
                flex: 1, display: 'flex', flexDirection: 'column', justifyContent: 'center',
                padding: '60px 80px', position: 'relative', minWidth: 0,
            }}>
                <div style={{ maxWidth: 480 }}>
                    {/* Logo */}
                    <div className="login-anim login-anim-1" style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 48 }}>
                        <div style={{
                            width: 52, height: 52, borderRadius: 14,
                            background: `linear-gradient(135deg, ${T.blue}, ${T.purple})`,
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontWeight: 900, fontSize: 22, color: '#fff',
                            boxShadow: `0 8px 32px color-mix(in srgb, ${T.blue} 30%, transparent)`,
                        }}>O</div>
                        <div>
                            <div style={{ fontSize: 22, fontWeight: 900, letterSpacing: '-0.03em' }}>Olinora</div>
                            <div style={{ fontSize: 10, color: T.textMuted, letterSpacing: '0.15em', textTransform: 'uppercase' }}>Sistema de Gestión</div>
                        </div>
                    </div>

                    {/* Headline */}
                    <h1 className="login-anim login-anim-2" style={{
                        fontSize: 44, fontWeight: 900, lineHeight: 1.1,
                        letterSpacing: '-0.04em', marginBottom: 20,
                    }}>
                        Gestión de turnos<br />
                        <span style={{ color: T.blue }}>inteligente</span> y<br />
                        <span style={{ color: T.green }}>eficiente</span>
                    </h1>

                    <p className="login-anim login-anim-3" style={{ fontSize: 15, color: T.textSoft, lineHeight: 1.7, marginBottom: 40, maxWidth: 380 }}>
                        Optimiza la atención al cliente con colas inteligentes, métricas en tiempo real y una experiencia excepcional.
                    </p>

                    {/* Feature pills */}
                    <div className="login-anim login-anim-4" style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                        {[
                            { icon: '◈', label: 'Multi-sucursal', color: T.blue },
                            { icon: '◉', label: 'Tiempo real', color: T.green },
                            { icon: '◆', label: 'Métricas', color: T.amber },
                            { icon: '⬡', label: 'Kiosco público', color: T.purple },
                        ].map(f => (
                            <span key={f.label} style={{
                                display: 'inline-flex', alignItems: 'center', gap: 6,
                                padding: '6px 14px', borderRadius: 20,
                                background: `color-mix(in srgb, ${f.color} 8%, transparent)`,
                                border: `1px solid color-mix(in srgb, ${f.color} 15%, transparent)`,
                                fontSize: 11, fontWeight: 600, color: f.color,
                            }}>
                                <span style={{ fontSize: 10 }}>{f.icon}</span> {f.label}
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            {/* Right panel — Login form */}
            <div style={{
                width: '100%', maxWidth: 480, minWidth: 320,
                display: 'flex', flexDirection: 'column', justifyContent: 'center',
                padding: '40px 48px', position: 'relative',
            }}>
                <div className="login-anim login-anim-2" style={{
                    background: T.card, borderRadius: 24, padding: '40px 36px',
                    border: `1px solid ${T.border}`,
                    boxShadow: `0 24px 80px color-mix(in srgb, ${T.blue} 5%, transparent)`,
                }}>
                    <div style={{ textAlign: 'center', marginBottom: 32 }}>
                        {/* Mobile logo (hidden on desktop) */}
                        <div className="login-mobile-logo" style={{
                            width: 44, height: 44, borderRadius: 12, margin: '0 auto 16px',
                            background: `linear-gradient(135deg, ${T.blue}, ${T.purple})`,
                            display: 'none', alignItems: 'center', justifyContent: 'center',
                            fontWeight: 900, fontSize: 18, color: '#fff',
                        }}>O</div>
                        <h2 style={{ fontSize: 22, fontWeight: 800, letterSpacing: '-0.02em', marginBottom: 4 }}>Iniciar Sesión</h2>
                        <p style={{ fontSize: 13, color: T.textMuted }}>Ingresa tus credenciales</p>
                    </div>

                    {status && (
                        <div style={{
                            background: `color-mix(in srgb, ${T.green} 8%, transparent)`,
                            border: `1px solid color-mix(in srgb, ${T.green} 20%, transparent)`,
                            borderRadius: 10, padding: '10px 14px', marginBottom: 20,
                            fontSize: 13, color: T.green, fontWeight: 500,
                        }}>
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit}>
                        {/* Email */}
                        <div style={{ marginBottom: 20 }}>
                            <label style={{
                                fontSize: 11, fontWeight: 700, color: T.textMuted,
                                textTransform: 'uppercase', letterSpacing: '0.08em',
                                display: 'block', marginBottom: 6,
                            }}>Email</label>
                            <input
                                type="email" value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                onFocus={() => setFocused('email')}
                                onBlur={() => setFocused(null)}
                                autoComplete="username" autoFocus
                                placeholder="tu@email.com"
                                style={{
                                    width: '100%', background: T.surface, color: T.text,
                                    border: `1px solid ${focused === 'email' ? T.blue : errors.email ? T.red : T.border}`,
                                    borderRadius: 10, padding: '13px 16px', fontSize: 14,
                                    outline: 'none', fontFamily: T.font,
                                    boxShadow: focused === 'email' ? `0 0 0 3px color-mix(in srgb, ${T.blue} 15%, transparent)` : 'none',
                                    transition: 'border-color 0.2s, box-shadow 0.2s',
                                }}
                            />
                            {errors.email && <div style={{ fontSize: 11, color: T.red, marginTop: 6 }}>{errors.email}</div>}
                        </div>

                        {/* Password */}
                        <div style={{ marginBottom: 20 }}>
                            <label style={{
                                fontSize: 11, fontWeight: 700, color: T.textMuted,
                                textTransform: 'uppercase', letterSpacing: '0.08em',
                                display: 'block', marginBottom: 6,
                            }}>Contraseña</label>
                            <input
                                type="password" value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                onFocus={() => setFocused('password')}
                                onBlur={() => setFocused(null)}
                                autoComplete="current-password"
                                placeholder="••••••••"
                                style={{
                                    width: '100%', background: T.surface, color: T.text,
                                    border: `1px solid ${focused === 'password' ? T.blue : errors.password ? T.red : T.border}`,
                                    borderRadius: 10, padding: '13px 16px', fontSize: 14,
                                    outline: 'none', fontFamily: T.font,
                                    boxShadow: focused === 'password' ? `0 0 0 3px color-mix(in srgb, ${T.blue} 15%, transparent)` : 'none',
                                    transition: 'border-color 0.2s, box-shadow 0.2s',
                                }}
                            />
                            {errors.password && <div style={{ fontSize: 11, color: T.red, marginTop: 6 }}>{errors.password}</div>}
                        </div>

                        {/* Remember + Forgot */}
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 28 }}>
                            <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                                <input type="checkbox" checked={data.remember}
                                    onChange={e => setData('remember', e.target.checked)}
                                    style={{ accentColor: `var(--t-blue)`, width: 16, height: 16 }} />
                                <span style={{ fontSize: 13, color: T.textSoft }}>Recordarme</span>
                            </label>
                            {canResetPassword && (
                                <Link href={route('password.request')} style={{
                                    fontSize: 12, color: T.blue, textDecoration: 'none', fontWeight: 600,
                                }}>
                                    ¿Olvidaste tu contraseña?
                                </Link>
                            )}
                        </div>

                        {/* Submit */}
                        <button type="submit" disabled={processing} style={{
                            width: '100%', padding: 15, borderRadius: 12, border: 'none',
                            background: `linear-gradient(135deg, ${T.blue}, color-mix(in srgb, ${T.blue} 80%, black))`,
                            color: '#fff', fontSize: 15, fontWeight: 700, fontFamily: T.font,
                            cursor: processing ? 'wait' : 'pointer',
                            opacity: processing ? 0.6 : 1,
                            boxShadow: `0 6px 24px color-mix(in srgb, ${T.blue} 25%, transparent)`,
                            transition: 'all 0.3s',
                            letterSpacing: '0.01em',
                        }}
                        onMouseEnter={e => { if (!processing) e.currentTarget.style.transform = 'translateY(-1px)'; }}
                        onMouseLeave={e => { e.currentTarget.style.transform = 'none'; }}>
                            {processing ? 'Ingresando...' : 'Iniciar Sesión'}
                        </button>
                    </form>
                </div>

                {/* Footer */}
                <div className="login-anim login-anim-4" style={{ textAlign: 'center', marginTop: 24 }}>
                    <span style={{ fontSize: 11, color: T.textMuted }}>
                        Olinora · Sistema Inteligente de Gestión de Turnos
                    </span>
                </div>
            </div>

            </div>{/* end inner container */}

            {/* Animations + responsive */}
            <style>{`
                @keyframes loginFadeIn {
                    from { opacity: 0; transform: translateY(16px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .login-anim { animation: loginFadeIn 0.6s ease both; }
                .login-anim-1 { animation-delay: 0.1s; }
                .login-anim-2 { animation-delay: 0.2s; }
                .login-anim-3 { animation-delay: 0.3s; }
                .login-anim-4 { animation-delay: 0.4s; }

                @media (max-width: 900px) {
                    .login-brand-panel { display: none !important; }
                    .login-mobile-logo { display: flex !important; }
                }
            `}</style>
        </div>
    </>);
}
