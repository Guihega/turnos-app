// resources/js/Pages/Welcome.jsx
import { Head, Link } from '@inertiajs/react';
import { T } from '@/Components/TurnosUI';

export default function Welcome({ auth }) {
    const V = (name) => `var(${name})`;

    const features = [
        { icon: '◈', title: 'Multi-Sucursal', desc: 'Gestiona múltiples sucursales desde un solo panel. Cada una con su configuración, colas y operadores.', color: V('--t-blue') },
        { icon: '◉', title: 'Tiempo Real', desc: 'Monitorea turnos, tiempos de espera y operadores en vivo con actualización automática.', color: V('--t-green') },
        { icon: '◆', title: 'Métricas Avanzadas', desc: 'Reportes detallados, KPIs por operador, tendencias y análisis de rendimiento.', color: V('--t-amber') },
        { icon: '⬡', title: 'Kiosco Público', desc: 'Tus clientes toman turno desde un kiosco táctil o escaneando un código QR.', color: V('--t-purple') },
        { icon: '▣', title: 'Pantalla TV', desc: 'Muestra turnos en pantallas de sala de espera con actualización automática.', color: V('--t-cyan') },
        { icon: '↻', title: 'Transferencias', desc: 'Transfiere turnos entre colas con seguimiento completo y prioridad automática.', color: V('--t-red') },
    ];

    return (<>
        <Head title="TurnosPro — Sistema de Gestión de Turnos" />
        <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100vh', overflow: 'hidden' }}>

            {/* ── Nav ── */}
            <nav style={{
                padding: '16px 32px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                borderBottom: `1px solid ${T.border}`, position: 'relative', zIndex: 10,
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <div style={{
                        width: 38, height: 38, borderRadius: 11,
                        background: `linear-gradient(135deg, ${T.blue}, ${T.purple})`,
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        fontWeight: 900, fontSize: 16, color: '#fff',
                    }}>T</div>
                    <span style={{ fontSize: 18, fontWeight: 900, letterSpacing: '-0.03em' }}>TurnosPro</span>
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                    {auth.user ? (
                        <Link href={route('dashboard')} style={{
                            padding: '9px 22px', borderRadius: 10, fontSize: 13, fontWeight: 700,
                            textDecoration: 'none', color: '#fff',
                            background: `linear-gradient(135deg, ${T.blue}, color-mix(in srgb, ${T.blue} 80%, black))`,
                        }}>Dashboard</Link>
                    ) : (
                        <Link href={route('login')} style={{
                            padding: '9px 22px', borderRadius: 10, fontSize: 13, fontWeight: 700,
                            textDecoration: 'none', color: '#fff',
                            background: `linear-gradient(135deg, ${T.blue}, color-mix(in srgb, ${T.blue} 80%, black))`,
                            boxShadow: `0 4px 16px color-mix(in srgb, ${T.blue} 20%, transparent)`,
                        }}>Iniciar Sesión</Link>
                    )}
                </div>
            </nav>

            {/* ── Hero ── */}
            <section style={{
                padding: '100px 32px 80px', textAlign: 'center', position: 'relative',
                maxWidth: 800, margin: '0 auto',
            }}>
                {/* Ambient glow */}
                <div style={{
                    position: 'absolute', top: '-40%', left: '50%', transform: 'translateX(-50%)',
                    width: 700, height: 500,
                    background: `radial-gradient(ellipse, color-mix(in srgb, ${T.blue} 8%, transparent), transparent 70%)`,
                    filter: 'blur(60px)', pointerEvents: 'none',
                }} />

                <div className="welcome-anim welcome-anim-1" style={{
                    display: 'inline-flex', alignItems: 'center', gap: 8, padding: '6px 16px',
                    borderRadius: 20, border: `1px solid ${T.border}`, marginBottom: 28,
                    fontSize: 11, fontWeight: 600, color: T.textSoft,
                    background: `color-mix(in srgb, ${T.blue} 5%, transparent)`,
                }}>
                    <span style={{ width: 6, height: 6, borderRadius: '50%', background: T.green, boxShadow: `0 0 8px ${T.green}` }} />
                    Sistema en producción · v1.0
                </div>

                <h1 className="welcome-anim welcome-anim-2" style={{
                    fontSize: 56, fontWeight: 900, lineHeight: 1.08, letterSpacing: '-0.04em',
                    marginBottom: 20, position: 'relative',
                }}>
                    Gestión de turnos<br />
                    para el <span style={{ color: T.blue }}>mundo real</span>
                </h1>

                <p className="welcome-anim welcome-anim-3" style={{
                    fontSize: 17, color: T.textSoft, lineHeight: 1.7, maxWidth: 520, margin: '0 auto 40px',
                }}>
                    TurnosPro organiza la atención al cliente con colas inteligentes, kioscos públicos, pantallas de sala de espera y métricas en tiempo real.
                </p>

                <div className="welcome-anim welcome-anim-4" style={{ display: 'flex', gap: 12, justifyContent: 'center' }}>
                    <Link href={auth.user ? route('dashboard') : route('login')} style={{
                        padding: '14px 32px', borderRadius: 12, fontSize: 15, fontWeight: 800,
                        textDecoration: 'none', color: '#fff',
                        background: `linear-gradient(135deg, ${T.blue}, color-mix(in srgb, ${T.blue} 75%, black))`,
                        boxShadow: `0 8px 32px color-mix(in srgb, ${T.blue} 25%, transparent)`,
                        transition: 'all 0.3s', display: 'inline-block',
                    }}>
                        {auth.user ? 'Ir al Dashboard' : 'Comenzar Ahora'}
                    </Link>
                </div>
            </section>

            {/* ── Features ── */}
            <section style={{
                padding: '60px 32px 80px', maxWidth: 1000, margin: '0 auto',
            }}>
                <div className="welcome-anim welcome-anim-5" style={{ textAlign: 'center', marginBottom: 48 }}>
                    <h2 style={{ fontSize: 28, fontWeight: 900, letterSpacing: '-0.03em', marginBottom: 8 }}>
                        Todo lo que necesitas
                    </h2>
                    <p style={{ fontSize: 14, color: T.textMuted }}>
                        Un sistema completo para optimizar la atención en tu organización
                    </p>
                </div>

                <div style={{
                    display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
                    gap: 16,
                }}>
                    {features.map((f, i) => (
                        <div key={f.title} className={`welcome-anim welcome-anim-${Math.min(i + 5, 10)}`}
                            style={{
                                background: T.card, borderRadius: 18, padding: '28px 24px',
                                border: `1px solid ${T.border}`, position: 'relative', overflow: 'hidden',
                                transition: 'all 0.3s cubic-bezier(0.4,0,0.2,1)', cursor: 'default',
                            }}
                            onMouseEnter={e => {
                                e.currentTarget.style.borderColor = `color-mix(in srgb, ${f.color} 40%, transparent)`;
                                e.currentTarget.style.transform = 'translateY(-3px)';
                            }}
                            onMouseLeave={e => {
                                e.currentTarget.style.borderColor = T.border;
                                e.currentTarget.style.transform = 'none';
                            }}
                        >
                            <div style={{
                                position: 'absolute', top: 0, left: 0, right: 0, height: 2,
                                background: `linear-gradient(90deg, ${f.color}, transparent)`,
                            }} />
                            <div style={{
                                width: 42, height: 42, borderRadius: 12,
                                background: `color-mix(in srgb, ${f.color} 10%, transparent)`,
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: 20, color: f.color, marginBottom: 16,
                            }}>{f.icon}</div>
                            <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 6 }}>{f.title}</div>
                            <div style={{ fontSize: 13, color: T.textSoft, lineHeight: 1.6 }}>{f.desc}</div>
                        </div>
                    ))}
                </div>
            </section>

            {/* ── Footer ── */}
            <footer style={{
                padding: '32px', textAlign: 'center', borderTop: `1px solid ${T.border}`,
            }}>
                <span style={{ fontSize: 12, color: T.textMuted }}>
                    TurnosPro · Sistema de Gestión de Turnos Multi-Sucursal · {new Date().getFullYear()}
                </span>
            </footer>

            {/* Animations */}
            <style>{`
                @keyframes welcomeFadeIn {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .welcome-anim { animation: welcomeFadeIn 0.7s ease both; }
                .welcome-anim-1 { animation-delay: 0.1s; }
                .welcome-anim-2 { animation-delay: 0.2s; }
                .welcome-anim-3 { animation-delay: 0.3s; }
                .welcome-anim-4 { animation-delay: 0.4s; }
                .welcome-anim-5 { animation-delay: 0.5s; }
                .welcome-anim-6 { animation-delay: 0.55s; }
                .welcome-anim-7 { animation-delay: 0.6s; }
                .welcome-anim-8 { animation-delay: 0.65s; }
                .welcome-anim-9 { animation-delay: 0.7s; }
                .welcome-anim-10 { animation-delay: 0.75s; }
            `}</style>
        </div>
    </>);
}
