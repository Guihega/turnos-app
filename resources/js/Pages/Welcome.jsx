// resources/js/Pages/Welcome.jsx
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

// ── Fonts ──
const fontLink = 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap';

// ── Landing Page Tokens ──
const L = {
    navy: '#0B1121',
    dark: '#111827',
    slate: '#1E293B',
    card: '#162032',
    border: '#1F2D44',
    text: '#E4E8F1',
    textSoft: '#94A3B8',
    textMuted: '#64748B',
    blue: '#3B82F6',
    blueGlow: 'rgba(59,130,246,0.15)',
    green: '#10B981',
    greenGlow: 'rgba(16,185,129,0.15)',
    purple: '#8B5CF6',
    purpleGlow: 'rgba(139,92,246,0.15)',
    amber: '#F59E0B',
    red: '#EF4444',
    cyan: '#06B6D4',
    font: "'Outfit', -apple-system, sans-serif",
    mono: "'JetBrains Mono', monospace",
};

// ── Reusable Components ──
function Section({ children, id, style = {} }) {
    return (
        <section id={id} style={{ padding: '100px 24px', maxWidth: 1200, margin: '0 auto', ...style }}>
            {children}
        </section>
    );
}

function SectionTitle({ tag, title, subtitle }) {
    return (
        <div className="lp-fade" style={{ textAlign: 'center', marginBottom: 60 }}>
            {tag && <div style={{ display: 'inline-block', fontSize: 11, fontWeight: 700, color: L.blue, letterSpacing: '0.15em', textTransform: 'uppercase', background: L.blueGlow, padding: '6px 16px', borderRadius: 20, marginBottom: 16 }}>{tag}</div>}
            <h2 style={{ fontSize: 'clamp(28px, 5vw, 42px)', fontWeight: 900, color: L.text, letterSpacing: '-0.03em', lineHeight: 1.15, marginBottom: 14 }}>{title}</h2>
            {subtitle && <p style={{ fontSize: 16, color: L.textMuted, maxWidth: 640, margin: '0 auto', lineHeight: 1.6 }}>{subtitle}</p>}
        </div>
    );
}

function FeatureCard({ icon, title, desc, color, delay = 0 }) {
    return (
        <div className="lp-fade lp-card-hover" style={{ background: L.card, border: `1px solid ${L.border}`, borderRadius: 20, padding: '32px 28px', position: 'relative', overflow: 'hidden', animationDelay: `${delay}ms` }}>
            <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2, background: `linear-gradient(90deg, ${color}, transparent)` }} />
            <div style={{ width: 48, height: 48, borderRadius: 14, background: `${color}15`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, marginBottom: 18 }}>{icon}</div>
            <h3 style={{ fontSize: 17, fontWeight: 800, color: L.text, marginBottom: 8, letterSpacing: '-0.01em' }}>{title}</h3>
            <p style={{ fontSize: 13, color: L.textMuted, lineHeight: 1.6 }}>{desc}</p>
        </div>
    );
}

function StatCard({ value, label, color, delay = 0 }) {
    return (
        <div className="lp-fade" style={{ textAlign: 'center', padding: '28px 20px', animationDelay: `${delay}ms` }}>
            <div style={{ fontSize: 'clamp(32px, 5vw, 44px)', fontWeight: 900, color, fontFamily: L.mono, letterSpacing: '-0.03em', lineHeight: 1 }}>{value}</div>
            <div style={{ fontSize: 12, color: L.textMuted, marginTop: 8, textTransform: 'uppercase', letterSpacing: '0.1em' }}>{label}</div>
        </div>
    );
}

// ── Main Component ──
export default function Welcome({ canLogin, canResetPassword, flash }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);

    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        name: '',
        email: '',
        organization: '',
        sector: '',
        size: '',
        message: '',
        website: '', // honeypot
        utm_source: '',
        utm_medium: '',
        utm_campaign: '',
        utm_term: '',
        utm_content: '',
    });

    // Capturar UTMs de la URL al cargar la landing.
    // Si el link fue olinora.com.mx/?utm_source=facebook&utm_campaign=launch,
    // los valores quedan listos para cuando el usuario envíe el formulario.
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const utms = {};
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(key => {
            const value = params.get(key);
            if (value) {
                // Truncar a 255 por seguridad (aunque el backend también lo valida).
                utms[key] = value.slice(0, 255);
            }
        });
        if (Object.keys(utms).length > 0) {
            setData(prev => ({ ...prev, ...utms }));
        }
    }, []);

    const submitLead = (e) => {
        e.preventDefault();
        post('/leads', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    useEffect(() => {
        if (!document.getElementById('lp-fonts')) {
            const link = document.createElement('link');
            link.id = 'lp-fonts';
            link.rel = 'stylesheet';
            link.href = fontLink;
            document.head.appendChild(link);
        }
        const onScroll = () => setScrolled(window.scrollY > 40);
        window.addEventListener('scroll', onScroll);

        const observer = new IntersectionObserver(
            entries => entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('lp-visible'); observer.unobserve(e.target); } }),
            { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
        );
        document.querySelectorAll('.lp-fade').forEach(el => observer.observe(el));
        return () => { window.removeEventListener('scroll', onScroll); observer.disconnect(); };
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            const observer = new IntersectionObserver(
                entries => entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('lp-visible'); observer.unobserve(e.target); } }),
                { threshold: 0.1 }
            );
            document.querySelectorAll('.lp-fade:not(.lp-visible)').forEach(el => observer.observe(el));
            return () => observer.disconnect();
        }, 100);
        return () => clearTimeout(timer);
    });

    const navLinks = [
        { label: 'Funciones', href: '#features' },
        { label: 'Cómo funciona', href: '#how' },
        { label: 'Sectores', href: '#sectors' },
        { label: 'Empezar', href: '#empezar' },
    ];

    return (<>
        <Head title="Olinora — Sistema de Gestión de Turnos" />
        <div style={{ fontFamily: L.font, background: L.navy, color: L.text, minHeight: '100vh', overflow: 'hidden' }}>

            {/* ══════════════ NAVBAR ══════════════ */}
            <nav style={{
                position: 'fixed', top: 0, left: 0, right: 0, zIndex: 100,
                background: scrolled ? 'rgba(11,17,33,0.92)' : 'transparent',
                backdropFilter: scrolled ? 'blur(20px) saturate(180%)' : 'none',
                borderBottom: scrolled ? `1px solid ${L.border}` : '1px solid transparent',
                transition: 'all 0.3s ease',
            }}>
                <div style={{ maxWidth: 1200, margin: '0 auto', padding: '0 24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', height: 64 }}>
                    <a href="#" style={{ display: 'flex', alignItems: 'center', gap: 10, textDecoration: 'none' }}>
                        <div style={{ width: 34, height: 34, borderRadius: 9, background: `linear-gradient(135deg, ${L.blue}, ${L.purple})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 15, color: '#fff' }}>O</div>
                        <span style={{ fontSize: 18, fontWeight: 900, color: L.text, letterSpacing: '-0.02em' }}>Olinora</span>
                    </a>

                    <div className="lp-nav-desktop" style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                        {navLinks.map(n => (
                            <a key={n.href} href={n.href} style={{ padding: '8px 14px', fontSize: 13, fontWeight: 500, color: L.textSoft, textDecoration: 'none', borderRadius: 8, transition: 'color 0.2s' }}
                               onMouseEnter={e => e.currentTarget.style.color = L.text}
                               onMouseLeave={e => e.currentTarget.style.color = L.textSoft}>
                                {n.label}
                            </a>
                        ))}
                        {canLogin && (
                            <Link href={route('login')} style={{ marginLeft: 12, padding: '9px 22px', fontSize: 13, fontWeight: 700, color: '#fff', background: L.blue, borderRadius: 10, textDecoration: 'none', transition: 'transform 0.2s, box-shadow 0.2s', boxShadow: `0 4px 16px ${L.blueGlow}` }}
                                  onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-1px)'; e.currentTarget.style.boxShadow = `0 8px 24px ${L.blueGlow}`; }}
                                  onMouseLeave={e => { e.currentTarget.style.transform = 'none'; e.currentTarget.style.boxShadow = `0 4px 16px ${L.blueGlow}`; }}>
                                Iniciar sesión
                            </Link>
                        )}
                    </div>

                    <button className="lp-nav-mobile" onClick={() => setMenuOpen(!menuOpen)} style={{ background: 'none', border: 'none', color: L.textSoft, cursor: 'pointer', padding: 6, display: 'none' }}>
                        <svg width="24" height="24" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            {!menuOpen ? <path strokeLinecap="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                       : <path strokeLinecap="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />}
                        </svg>
                    </button>
                </div>

                {menuOpen && (
                    <div className="lp-nav-mobile" style={{ display: 'none', padding: '8px 24px 16px', borderTop: `1px solid ${L.border}`, background: 'rgba(11,17,33,0.96)' }}>
                        {navLinks.map(n => (
                            <a key={n.href} href={n.href} onClick={() => setMenuOpen(false)} style={{ display: 'block', padding: '12px 0', fontSize: 15, fontWeight: 600, color: L.textSoft, textDecoration: 'none' }}>{n.label}</a>
                        ))}
                        {canLogin && <Link href={route('login')} style={{ display: 'inline-block', marginTop: 8, padding: '10px 24px', fontSize: 14, fontWeight: 700, color: '#fff', background: L.blue, borderRadius: 10, textDecoration: 'none' }}>Iniciar sesión</Link>}
                    </div>
                )}
            </nav>

            {/* ══════════════ HERO ══════════════ */}
            <div style={{ position: 'relative', paddingTop: 64, overflow: 'hidden' }}>
                <div style={{ position: 'absolute', top: '-20%', right: '-15%', width: 700, height: 700, borderRadius: '50%', background: `radial-gradient(circle, ${L.blueGlow}, transparent 70%)`, filter: 'blur(100px)', pointerEvents: 'none' }} />
                <div style={{ position: 'absolute', bottom: '-10%', left: '-10%', width: 500, height: 500, borderRadius: '50%', background: `radial-gradient(circle, ${L.purpleGlow}, transparent 70%)`, filter: 'blur(80px)', pointerEvents: 'none' }} />
                <div style={{ position: 'absolute', inset: 0, backgroundImage: `linear-gradient(${L.border}40 1px, transparent 1px), linear-gradient(90deg, ${L.border}40 1px, transparent 1px)`, backgroundSize: '60px 60px', opacity: 0.3, pointerEvents: 'none', maskImage: 'linear-gradient(to bottom, black 30%, transparent 80%)' }} />

                <Section style={{ paddingTop: 80, paddingBottom: 80, position: 'relative' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 60, alignItems: 'center' }} className="lp-hero-grid">
                        <div>
                            <div className="lp-fade" style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: L.greenGlow, border: `1px solid ${L.green}25`, borderRadius: 20, padding: '6px 16px', marginBottom: 24 }}>
                                <span style={{ width: 6, height: 6, borderRadius: '50%', background: L.green, animation: 'lpPulse 2s infinite' }} />
                                <span style={{ fontSize: 11, fontWeight: 700, color: L.green, letterSpacing: '0.08em', textTransform: 'uppercase' }}>Disponible · Abriendo programa piloto</span>
                            </div>

                            <h1 className="lp-fade" style={{ fontSize: 'clamp(34px, 5.5vw, 56px)', fontWeight: 900, lineHeight: 1.08, letterSpacing: '-0.04em', marginBottom: 20, animationDelay: '100ms' }}>
                                Adiós a las filas.<br />
                                <span style={{ color: L.blue }}>Hola, Olinora.</span>
                            </h1>

                            <p className="lp-fade" style={{ fontSize: 17, color: L.textSoft, lineHeight: 1.7, marginBottom: 32, maxWidth: 480, animationDelay: '200ms' }}>
                                Sistema de gestión de turnos para clínicas, bancos y oficinas de gobierno. Sus clientes escanean un código QR, toman turno desde su celular y siguen su posición en tiempo real. Sin aplicación. Sin registro.
                            </p>

                            <div className="lp-fade" style={{ display: 'flex', gap: 12, flexWrap: 'wrap', animationDelay: '300ms' }}>
                                <Link href="/onboarding" style={{ padding: '14px 32px', fontSize: 15, fontWeight: 700, color: '#fff', background: `linear-gradient(135deg, ${L.blue}, ${L.purple})`, borderRadius: 12, textDecoration: 'none', boxShadow: `0 8px 32px ${L.blueGlow}`, transition: 'transform 0.2s' }}
                                   onMouseEnter={e => e.currentTarget.style.transform = 'translateY(-2px)'}
                                   onMouseLeave={e => e.currentTarget.style.transform = 'none'}>
                                    Crear cuenta gratuita
                                </Link>
                                <a href="#empezar" style={{ padding: '14px 28px', fontSize: 15, fontWeight: 700, color: L.text, background: L.card, border: `1px solid ${L.blue}40`, borderRadius: 12, textDecoration: 'none', transition: 'all 0.2s' }}
                                   onMouseEnter={e => { e.currentTarget.style.borderColor = L.blue; e.currentTarget.style.transform = 'translateY(-2px)'; }}
                                   onMouseLeave={e => { e.currentTarget.style.borderColor = `${L.blue}40`; e.currentTarget.style.transform = 'none'; }}>
                                    Hablar con el equipo
                                </a>
                            </div>

                            <p className="lp-fade" style={{ fontSize: 12, color: L.textMuted, marginTop: 16, animationDelay: '400ms' }}>
                                Sin tarjeta de crédito. Configuración en minutos.
                            </p>
                        </div>

                        {/* Right: Dashboard visual */}
                        <div className="lp-hero-visual" style={{ position: 'relative' }}>
                            <div style={{ background: L.card, border: `1px solid ${L.border}`, borderRadius: 16, padding: 20, boxShadow: `0 24px 80px rgba(0,0,0,0.4)` }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 16 }}>
                                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: '#FF5F57' }} />
                                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: '#FEBC2E' }} />
                                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: '#28C840' }} />
                                    <span style={{ fontSize: 10, color: L.textMuted, fontFamily: L.mono, marginLeft: 10 }}>olinora.com.mx/dashboard</span>
                                </div>

                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginBottom: 16 }}>
                                    <div style={{ background: L.dark, border: `1px solid ${L.border}`, borderRadius: 10, padding: 14, textAlign: 'center' }}>
                                        <div style={{ fontSize: 24, fontWeight: 900, color: L.blue, fontFamily: L.mono }}>127</div>
                                        <div style={{ fontSize: 9, color: L.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginTop: 4 }}>Turnos hoy</div>
                                    </div>
                                    <div style={{ background: L.dark, border: `1px solid ${L.border}`, borderRadius: 10, padding: 14, textAlign: 'center' }}>
                                        <div style={{ fontSize: 24, fontWeight: 900, color: L.green, fontFamily: L.mono }}>3.2<span style={{ fontSize: 12 }}>m</span></div>
                                        <div style={{ fontSize: 9, color: L.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginTop: 4 }}>Espera prom.</div>
                                    </div>
                                    <div style={{ background: L.dark, border: `1px solid ${L.border}`, borderRadius: 10, padding: 14, textAlign: 'center' }}>
                                        <div style={{ fontSize: 24, fontWeight: 900, color: L.purple, fontFamily: L.mono }}>8</div>
                                        <div style={{ fontSize: 9, color: L.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginTop: 4 }}>Operadores</div>
                                    </div>
                                </div>

                                <div style={{ display: 'flex', alignItems: 'flex-end', gap: 4, height: 80, padding: '0 8px' }}>
                                    {[40, 55, 45, 70, 85, 60, 90, 75, 95, 80, 65, 88].map((h, i) => (
                                        <div key={i} style={{ flex: 1, height: `${h}%`, borderRadius: '4px 4px 0 0', background: `linear-gradient(to top, ${L.blue}, ${L.purple})`, opacity: 0.6 + (h / 250), transition: 'height 0.5s ease' }} />
                                    ))}
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 6, padding: '0 8px' }}>
                                    <span style={{ fontSize: 8, color: L.textMuted, fontFamily: L.mono }}>8:00</span>
                                    <span style={{ fontSize: 8, color: L.textMuted, fontFamily: L.mono }}>20:00</span>
                                </div>

                                <div style={{ marginTop: 12, textAlign: 'center', fontSize: 9, color: L.textMuted, fontStyle: 'italic' }}>
                                    Vista ilustrativa del panel administrativo
                                </div>
                            </div>

                            <div style={{ position: 'absolute', bottom: -20, left: -30, background: L.card, border: `1px solid ${L.green}30`, borderRadius: 14, padding: '12px 18px', boxShadow: `0 12px 40px rgba(0,0,0,0.4)`, animation: 'lpFloat 3s ease-in-out infinite' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                    <div style={{ width: 32, height: 32, borderRadius: 8, background: L.greenGlow, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16 }}>✓</div>
                                    <div>
                                        <div style={{ fontSize: 11, fontWeight: 700, color: L.green }}>Turno A-024 llamado</div>
                                        <div style={{ fontSize: 9, color: L.textMuted }}>Ventanilla 3 · Ahora</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="lp-fade" style={{ display: 'flex', justifyContent: 'center', gap: 40, marginTop: 80, flexWrap: 'wrap', animationDelay: '400ms' }}>
                        {['Multi-sucursal', 'Tiempo real', 'QR + Móvil', 'White-label', 'Analítica'].map(t => (
                            <span key={t} style={{ fontSize: 12, fontWeight: 600, color: L.textMuted, display: 'flex', alignItems: 'center', gap: 6 }}>
                                <span style={{ width: 5, height: 5, borderRadius: '50%', background: L.blue }} /> {t}
                            </span>
                        ))}
                    </div>
                </Section>
            </div>

            {/* ══════════════ CREDENTIALS BAR ══════════════ */}
            <div style={{ background: L.slate, borderTop: `1px solid ${L.border}`, borderBottom: `1px solid ${L.border}` }}>
                <div style={{ maxWidth: 1200, margin: '0 auto', padding: '28px 24px', display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 20 }} className="lp-stats-grid">
                    <StatCard value="AES-256" label="Cifrado de datos personales" color={L.green} delay={0} />
                    <StatCard value="2FA" label="Obligatorio para administradores" color={L.blue} delay={100} />
                    <StatCard value="PII" label="Cifrado a nivel de campo" color={L.purple} delay={200} />
                    <StatCard value="99.9%" label="Objetivo de disponibilidad" color={L.amber} delay={300} />
                </div>
            </div>

            {/* ══════════════ FEATURES ══════════════ */}
            <div id="features" style={{ background: L.navy }}>
                <Section>
                    <SectionTitle tag="Funciones" title="Todo lo que necesita para eliminar las filas" subtitle="Una plataforma completa que digitaliza toda la experiencia de espera" />
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }} className="lp-features-grid">
                        <FeatureCard icon="📱" title="Kiosco móvil por QR" desc="El cliente escanea un código QR y toma turno al instante desde su celular. Sin aplicación, sin registro." color={L.purple} delay={0} />
                        <FeatureCard icon="📺" title="Pantalla en vivo" desc="Display para TV en sala de espera con turnos actualizándose en tiempo real vía WebSocket." color={L.cyan} delay={100} />
                        <FeatureCard icon="📊" title="Analítica operativa" desc="Métricas en tiempo real, tendencias diarias, horarios pico y desempeño por operador." color={L.amber} delay={200} />
                        <FeatureCard icon="🎨" title="Su marca, su sistema" desc="Personalice colores, logotipo, textos y configuración. Sus clientes ven su marca, no la nuestra." color={L.red} delay={300} />
                        <FeatureCard icon="🏢" title="Multi-sucursal" desc="Gestione todas sus ubicaciones desde un solo panel. Compare desempeño entre sucursales." color={L.blue} delay={400} />
                        <FeatureCard icon="⚡" title="Estado en tiempo real" desc="El cliente sigue su posición desde su celular. Alerta automática cuando es su turno." color={L.green} delay={500} />
                    </div>
                </Section>
            </div>

            {/* ══════════════ HOW IT WORKS ══════════════ */}
            <div id="how" style={{ background: L.dark }}>
                <Section>
                    <SectionTitle tag="Proceso" title="Tres pasos. Cero complicaciones." subtitle="Así de sencillo es para sus clientes" />
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 32, position: 'relative' }} className="lp-steps-grid">
                        <div className="lp-step-line" style={{ position: 'absolute', top: 50, left: '20%', right: '20%', height: 2, background: `linear-gradient(90deg, ${L.blue}, ${L.green}, ${L.purple})`, opacity: 0.3 }} />

                        {[
                            { num: '1', title: 'Escanea', desc: 'El cliente escanea el código QR con la cámara de su celular y accede al kiosco digital.', color: L.blue },
                            { num: '2', title: 'Elige', desc: 'Selecciona el servicio que necesita y recibe su número de turno al instante.', color: L.green },
                            { num: '3', title: 'Espera libre', desc: 'Sigue su posición en tiempo real y recibe una alerta cuando es su turno.', color: L.purple },
                        ].map((step, i) => (
                            <div key={i} className="lp-fade" style={{ textAlign: 'center', position: 'relative', zIndex: 1, animationDelay: `${i * 150}ms` }}>
                                <div style={{ width: 64, height: 64, borderRadius: '50%', background: `${step.color}15`, border: `2px solid ${step.color}40`, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 20px', fontSize: 26, fontWeight: 900, color: step.color, fontFamily: L.mono }}>{step.num}</div>
                                <h3 style={{ fontSize: 20, fontWeight: 800, color: L.text, marginBottom: 10 }}>{step.title}</h3>
                                <p style={{ fontSize: 14, color: L.textMuted, lineHeight: 1.6, maxWidth: 280, margin: '0 auto' }}>{step.desc}</p>
                            </div>
                        ))}
                    </div>

                    <div className="lp-fade" style={{ marginTop: 60, background: L.blueGlow, border: `1px solid ${L.blue}20`, borderRadius: 16, padding: '20px 32px', textAlign: 'center' }}>
                        <span style={{ fontSize: 15, fontWeight: 700, color: L.text }}>Sin aplicación. Sin registro. Sin complicaciones. Solo escanear y listo.</span>
                    </div>
                </Section>
            </div>

            {/* ══════════════ SECTORS ══════════════ */}
            <div id="sectors" style={{ background: L.navy }}>
                <Section>
                    <SectionTitle tag="Sectores" title="Diseñado para su operación" subtitle="Funciona para cualquier negocio con atención presencial" />
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16 }} className="lp-sectors-grid">
                        {[
                            { icon: '🏥', title: 'Salud', items: 'Clínicas, hospitales, laboratorios, consultorios', color: L.green },
                            { icon: '🏛️', title: 'Gobierno', items: 'Oficinas públicas, trámites, registro civil, municipios', color: L.purple },
                            { icon: '🏦', title: 'Finanzas', items: 'Bancos, aseguradoras, cajas de ahorro, AFORE', color: L.blue },
                            { icon: '🏪', title: 'Comercio', items: 'Telecomunicaciones, restaurantes, tiendas de servicio', color: L.amber },
                        ].map((s, i) => (
                            <div key={i} className="lp-fade lp-card-hover" style={{ background: L.card, border: `1px solid ${L.border}`, borderRadius: 20, padding: '36px 24px', textAlign: 'center', animationDelay: `${i * 100}ms` }}>
                                <div style={{ fontSize: 40, marginBottom: 16 }}>{s.icon}</div>
                                <h3 style={{ fontSize: 18, fontWeight: 800, color: L.text, marginBottom: 10 }}>{s.title}</h3>
                                <p style={{ fontSize: 12, color: L.textMuted, lineHeight: 1.6 }}>{s.items}</p>
                            </div>
                        ))}
                    </div>
                </Section>
            </div>

            {/* ══════════════ CÓMO EMPEZAR — Dos caminos diferenciados ══════════════ */}
            <div id="empezar" style={{ background: L.dark, position: 'relative', overflow: 'hidden' }}>
                <div style={{ position: 'absolute', top: '50%', left: '50%', transform: 'translate(-50%, -50%)', width: 800, height: 600, borderRadius: '50%', background: `radial-gradient(circle, ${L.blueGlow}, transparent 70%)`, filter: 'blur(100px)', pointerEvents: 'none' }} />

                <Section style={{ position: 'relative' }}>
                    <SectionTitle
                        tag="Empezar"
                        title="Dos formas de conocer Olinora"
                        subtitle="Elija el camino que mejor se ajuste a su organización. Ambos son gratuitos."
                    />

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, maxWidth: 960, margin: '0 auto' }} className="lp-paths-grid">

                        {/* ── Camino 1: Self-serve ── */}
                        <div className="lp-fade lp-card-hover" style={{ background: L.card, border: `1px solid ${L.blue}30`, borderRadius: 20, padding: '36px 32px', position: 'relative', overflow: 'hidden' }}>
                            <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 3, background: `linear-gradient(90deg, ${L.blue}, transparent)` }} />

                            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: L.blueGlow, borderRadius: 20, padding: '5px 14px', marginBottom: 20, fontSize: 10, fontWeight: 700, color: L.blue, letterSpacing: '0.1em', textTransform: 'uppercase' }}>
                                Opción rápida
                            </div>

                            <h3 style={{ fontSize: 24, fontWeight: 900, color: L.text, marginBottom: 12, letterSpacing: '-0.02em' }}>
                                Probar por su cuenta
                            </h3>

                            <p style={{ fontSize: 14, color: L.textSoft, lineHeight: 1.6, marginBottom: 24 }}>
                                Cree una cuenta gratuita, configure su primera sucursal y empiece a emitir turnos en minutos. Sin llamadas. Sin formularios largos.
                            </p>

                            <ul style={{ listStyle: 'none', padding: 0, margin: '0 0 32px 0' }}>
                                {[
                                    'Acceso inmediato al sistema completo',
                                    'Configuración guiada paso a paso',
                                    'Sin tarjeta de crédito',
                                    'Su información permanece privada',
                                ].map((item, i) => (
                                    <li key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: 10, marginBottom: 10, fontSize: 13, color: L.textSoft, lineHeight: 1.5 }}>
                                        <span style={{ color: L.blue, fontWeight: 900, flexShrink: 0, marginTop: 1 }}>✓</span>
                                        {item}
                                    </li>
                                ))}
                            </ul>

                            <Link href="/onboarding" style={{ display: 'block', textAlign: 'center', padding: '14px 24px', fontSize: 15, fontWeight: 700, color: '#fff', background: `linear-gradient(135deg, ${L.blue}, ${L.purple})`, borderRadius: 12, textDecoration: 'none', boxShadow: `0 8px 32px ${L.blueGlow}`, transition: 'transform 0.2s' }}
                                  onMouseEnter={e => e.currentTarget.style.transform = 'translateY(-2px)'}
                                  onMouseLeave={e => e.currentTarget.style.transform = 'none'}>
                                Crear cuenta gratuita
                            </Link>

                            <p style={{ fontSize: 11, color: L.textMuted, textAlign: 'center', marginTop: 12 }}>
                                Recomendado para una sola sucursal o consultorio individual.
                            </p>
                        </div>

                        {/* ── Camino 2: Sales-led ── */}
                        <div className="lp-fade lp-card-hover" style={{ background: L.card, border: `1px solid ${L.purple}30`, borderRadius: 20, padding: '36px 32px', position: 'relative', overflow: 'hidden', animationDelay: '150ms' }}>
                            <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 3, background: `linear-gradient(90deg, ${L.purple}, transparent)` }} />

                            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: L.purpleGlow, borderRadius: 20, padding: '5px 14px', marginBottom: 20, fontSize: 10, fontWeight: 700, color: L.purple, letterSpacing: '0.1em', textTransform: 'uppercase' }}>
                                Opción guiada
                            </div>

                            <h3 style={{ fontSize: 24, fontWeight: 900, color: L.text, marginBottom: 12, letterSpacing: '-0.02em' }}>
                                Demostración guiada
                            </h3>

                            <p style={{ fontSize: 14, color: L.textSoft, lineHeight: 1.6, marginBottom: 24 }}>
                                Una sesión de veinte minutos en la que le mostramos el sistema funcionando, respondemos sus preguntas específicas, y le ayudamos a planear la implementación.
                            </p>

                            <ul style={{ listStyle: 'none', padding: 0, margin: '0 0 32px 0' }}>
                                {[
                                    'Ideal para operaciones multi-sucursal',
                                    'Útil si requiere aprobación interna o comité',
                                    'Resolvemos dudas de integración y compliance',
                                    'Acompañamiento durante el piloto',
                                ].map((item, i) => (
                                    <li key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: 10, marginBottom: 10, fontSize: 13, color: L.textSoft, lineHeight: 1.5 }}>
                                        <span style={{ color: L.purple, fontWeight: 900, flexShrink: 0, marginTop: 1 }}>✓</span>
                                        {item}
                                    </li>
                                ))}
                            </ul>

                            <a href="#demo-form" style={{ display: 'block', textAlign: 'center', padding: '14px 24px', fontSize: 15, fontWeight: 700, color: L.text, background: L.dark, border: `1px solid ${L.purple}60`, borderRadius: 12, textDecoration: 'none', transition: 'all 0.2s' }}
                               onMouseEnter={e => { e.currentTarget.style.borderColor = L.purple; e.currentTarget.style.transform = 'translateY(-2px)'; }}
                               onMouseLeave={e => { e.currentTarget.style.borderColor = `${L.purple}60`; e.currentTarget.style.transform = 'none'; }}>
                                Agendar demostración
                            </a>

                            <p style={{ fontSize: 11, color: L.textMuted, textAlign: 'center', marginTop: 12 }}>
                                Recomendado para bancos, gobierno y redes de clínicas.
                            </p>
                        </div>
                    </div>
                </Section>
            </div>

            {/* ══════════════ LEAD FORM ══════════════ */}
            <div id="demo-form" style={{ background: L.navy, position: 'relative', overflow: 'hidden' }}>
                <div style={{ position: 'absolute', top: '30%', left: '-10%', width: 500, height: 500, borderRadius: '50%', background: `radial-gradient(circle, ${L.purpleGlow}, transparent 70%)`, filter: 'blur(80px)', pointerEvents: 'none' }} />

                <Section style={{ position: 'relative', maxWidth: 720 }}>
                    <SectionTitle
                        tag="Demostración"
                        title="Cuéntenos sobre su operación"
                        subtitle="Con los datos que comparta, preparamos la demostración con ejemplos relevantes para su sector. Le respondemos en menos de 24 horas hábiles."
                    />

                    {wasSuccessful && (
                        <div className="lp-fade" style={{ background: L.greenGlow, border: `1px solid ${L.green}40`, borderRadius: 14, padding: '24px 28px', textAlign: 'center' }}>
                            <div style={{ fontSize: 40, marginBottom: 12 }}>✓</div>
                            <div style={{ fontSize: 18, fontWeight: 800, color: L.green, marginBottom: 8 }}>Solicitud recibida</div>
                            <div style={{ fontSize: 14, color: L.textSoft, lineHeight: 1.6, maxWidth: 440, margin: '0 auto' }}>
                                Gracias por su interés. Le contactaremos en menos de 24 horas hábiles para agendar la sesión. Si no llega el correo, revise su carpeta de promociones.
                            </div>
                        </div>
                    )}

                    {!wasSuccessful && (
                        <form onSubmit={submitLead} className="lp-fade" style={{ background: L.card, border: `1px solid ${L.border}`, borderRadius: 20, padding: 32 }}>
                            <input type="text" name="website" value={data.website} onChange={e => setData('website', e.target.value)} tabIndex="-1" autoComplete="off"
                                style={{ position: 'absolute', left: '-9999px', opacity: 0, pointerEvents: 'none' }} aria-hidden="true" />

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }} className="lp-form-grid">
                                <FormField label="Nombre completo" error={errors.name}>
                                    <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} required
                                        style={inputStyle} placeholder="Juan Pérez" />
                                </FormField>

                                <FormField label="Correo corporativo" error={errors.email}>
                                    <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} required
                                        style={inputStyle} placeholder="juan@organizacion.com" />
                                </FormField>
                            </div>

                            <FormField label="Organización" error={errors.organization} style={{ marginBottom: 16 }}>
                                <input type="text" value={data.organization} onChange={e => setData('organization', e.target.value)} required
                                    style={inputStyle} placeholder="Clínica San Rafael" />
                            </FormField>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }} className="lp-form-grid">
                                <FormField label="Sector" error={errors.sector}>
                                    <select value={data.sector} onChange={e => setData('sector', e.target.value)} required style={inputStyle}>
                                        <option value="">Seleccione...</option>
                                        <option value="salud">Clínica u hospital</option>
                                        <option value="finanzas">Banco o institución financiera</option>
                                        <option value="gobierno">Oficina de gobierno</option>
                                        <option value="comercio">Comercio o servicios</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </FormField>

                                <FormField label="Tamaño de operación" error={errors.size}>
                                    <select value={data.size} onChange={e => setData('size', e.target.value)} required style={inputStyle}>
                                        <option value="">Seleccione...</option>
                                        <option value="1">Una sucursal o ubicación</option>
                                        <option value="2-5">De 2 a 5 sucursales</option>
                                        <option value="6-20">De 6 a 20 sucursales</option>
                                        <option value="20+">Más de 20 sucursales</option>
                                    </select>
                                </FormField>
                            </div>

                            <FormField label="¿Qué le gustaría resolver con Olinora? (opcional)" error={errors.message} style={{ marginBottom: 20 }}>
                                <textarea value={data.message} onChange={e => setData('message', e.target.value)} rows={3} maxLength={500}
                                    style={{ ...inputStyle, resize: 'vertical', minHeight: 80 }}
                                    placeholder="Cuéntenos brevemente qué sistema usa actualmente, cuántas personas atienden al día, o qué le preocupa de su operación." />
                            </FormField>

                            <button type="submit" disabled={processing}
                                style={{ width: '100%', padding: '16px 24px', fontSize: 15, fontWeight: 700, color: '#fff', background: processing ? L.textMuted : `linear-gradient(135deg, ${L.blue}, ${L.purple})`, borderRadius: 12, border: 'none', cursor: processing ? 'not-allowed' : 'pointer', boxShadow: `0 8px 32px ${L.blueGlow}`, transition: 'transform 0.2s' }}
                                onMouseEnter={e => { if (!processing) e.currentTarget.style.transform = 'translateY(-2px)'; }}
                                onMouseLeave={e => e.currentTarget.style.transform = 'none'}>
                                {processing ? 'Enviando...' : 'Agendar demostración'}
                            </button>

                            <p style={{ fontSize: 11, color: L.textMuted, textAlign: 'center', marginTop: 16, lineHeight: 1.5 }}>
                                Al enviar este formulario usted acepta nuestro <a href="/privacidad" style={{ color: L.textSoft, textDecoration: 'underline' }}>Aviso de Privacidad</a>. No compartimos sus datos con terceros.
                            </p>
                        </form>
                    )}
                </Section>
            </div>

            {/* ══════════════ FOOTER ══════════════ */}
            <footer style={{ background: L.dark, borderTop: `1px solid ${L.border}`, padding: '40px 24px' }}>
                <div style={{ maxWidth: 1200, margin: '0 auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 20 }} className="lp-footer">
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <div style={{ width: 28, height: 28, borderRadius: 7, background: `linear-gradient(135deg, ${L.blue}, ${L.purple})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 12, color: '#fff' }}>O</div>
                        <span style={{ fontSize: 13, color: L.textMuted }}>Olinora · Gestión de turnos · Hecho en México</span>
                    </div>

                    <div style={{ display: 'flex', gap: 24, alignItems: 'center', flexWrap: 'wrap' }}>
                        <a href="/privacidad" style={{ fontSize: 12, color: L.textMuted, textDecoration: 'none', transition: 'color 0.2s' }}
                            onMouseEnter={e => e.currentTarget.style.color = L.text}
                            onMouseLeave={e => e.currentTarget.style.color = L.textMuted}>Aviso de privacidad</a>
                        <a href="/terminos" style={{ fontSize: 12, color: L.textMuted, textDecoration: 'none', transition: 'color 0.2s' }}
                            onMouseEnter={e => e.currentTarget.style.color = L.text}
                            onMouseLeave={e => e.currentTarget.style.color = L.textMuted}>Términos y condiciones</a>
                        <a href="mailto:hola@olinora.com.mx" style={{ fontSize: 12, color: L.textMuted, textDecoration: 'none', transition: 'color 0.2s' }}
                            onMouseEnter={e => e.currentTarget.style.color = L.text}
                            onMouseLeave={e => e.currentTarget.style.color = L.textMuted}>hola@olinora.com.mx</a>
                        <span style={{ fontSize: 12, color: L.textMuted }}>© {new Date().getFullYear()} Olinora</span>
                    </div>
                </div>
            </footer>

            {/* ══════════════ STYLES ══════════════ */}
            <style>{`
                @keyframes lpPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
                @keyframes lpFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

                .lp-fade { opacity: 0; transform: translateY(20px); transition: opacity 0.6s ease, transform 0.6s ease; }
                .lp-visible { opacity: 1 !important; transform: translateY(0) !important; }

                .lp-card-hover { transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }
                .lp-card-hover:hover { transform: translateY(-4px); box-shadow: 0 16px 48px rgba(0,0,0,0.3); }

                .lp-nav-mobile { display: none !important; }

                input:focus, select:focus, textarea:focus {
                    outline: none !important;
                    border-color: ${L.blue} !important;
                    box-shadow: 0 0 0 3px ${L.blueGlow} !important;
                }

                @media (max-width: 900px) {
                    .lp-hero-grid { grid-template-columns: 1fr !important; }
                    .lp-hero-visual { display: none !important; }
                    .lp-features-grid { grid-template-columns: 1fr !important; }
                    .lp-steps-grid { grid-template-columns: 1fr !important; }
                    .lp-step-line { display: none !important; }
                    .lp-sectors-grid { grid-template-columns: repeat(2, 1fr) !important; }
                    .lp-stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
                    .lp-form-grid { grid-template-columns: 1fr !important; }
                    .lp-paths-grid { grid-template-columns: 1fr !important; }
                    .lp-footer { flex-direction: column !important; text-align: center !important; }
                    .lp-nav-desktop { display: none !important; }
                    .lp-nav-mobile { display: flex !important; }
                }

                @media (max-width: 500px) {
                    .lp-sectors-grid { grid-template-columns: 1fr !important; }
                    .lp-stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
                }

                html { scroll-behavior: smooth; }
            `}</style>
        </div>
    </>);
}

// ── Helper components ──
const inputStyle = {
    width: '100%',
    padding: '12px 14px',
    fontSize: 14,
    color: '#E4E8F1',
    background: '#0B1121',
    border: '1px solid #1F2D44',
    borderRadius: 10,
    fontFamily: "'Outfit', -apple-system, sans-serif",
    transition: 'border-color 0.2s, box-shadow 0.2s',
};

function FormField({ label, error, children, style = {} }) {
    return (
        <div style={style}>
            <label style={{ display: 'block', fontSize: 12, fontWeight: 600, color: '#94A3B8', marginBottom: 6, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                {label}
            </label>
            {children}
            {error && <div style={{ fontSize: 12, color: '#EF4444', marginTop: 6 }}>{error}</div>}
        </div>
    );
}
