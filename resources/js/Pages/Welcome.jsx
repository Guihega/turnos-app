// resources/js/Pages/Welcome.jsx
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

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
            {subtitle && <p style={{ fontSize: 16, color: L.textMuted, maxWidth: 560, margin: '0 auto', lineHeight: 1.6 }}>{subtitle}</p>}
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
            <div style={{ fontSize: 'clamp(36px, 6vw, 52px)', fontWeight: 900, color, fontFamily: L.mono, letterSpacing: '-0.03em', lineHeight: 1 }}>{value}</div>
            <div style={{ fontSize: 12, color: L.textMuted, marginTop: 8, textTransform: 'uppercase', letterSpacing: '0.1em' }}>{label}</div>
        </div>
    );
}

// ── Main Component ──
export default function Welcome({ canLogin, canResetPassword }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        // Font injection
        if (!document.getElementById('lp-fonts')) {
            const link = document.createElement('link');
            link.id = 'lp-fonts';
            link.rel = 'stylesheet';
            link.href = fontLink;
            document.head.appendChild(link);
        }
        // Scroll listener for nav
        const onScroll = () => setScrolled(window.scrollY > 40);
        window.addEventListener('scroll', onScroll);

        // Intersection Observer for fade-in animations
        const observer = new IntersectionObserver(
            entries => entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('lp-visible'); observer.unobserve(e.target); } }),
            { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
        );
        document.querySelectorAll('.lp-fade').forEach(el => observer.observe(el));
        return () => { window.removeEventListener('scroll', onScroll); observer.disconnect(); };
    }, []);

    // Re-observe after route changes
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
        { label: 'Resultados', href: '#results' },
    ];

    return (<>
        <Head title="Olinora — Sistema Inteligente de Gestión de Turnos" />
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
                    {/* Logo */}
                    <a href="#" style={{ display: 'flex', alignItems: 'center', gap: 10, textDecoration: 'none' }}>
                        <div style={{ width: 34, height: 34, borderRadius: 9, background: `linear-gradient(135deg, ${L.blue}, ${L.purple})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 15, color: '#fff' }}>O</div>
                        <span style={{ fontSize: 18, fontWeight: 900, color: L.text, letterSpacing: '-0.02em' }}>Olinora</span>
                    </a>

                    {/* Desktop nav */}
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
                                Iniciar Sesión
                            </Link>
                        )}
                    </div>

                    {/* Mobile hamburger */}
                    <button className="lp-nav-mobile" onClick={() => setMenuOpen(!menuOpen)} style={{ background: 'none', border: 'none', color: L.textSoft, cursor: 'pointer', padding: 6, display: 'none' }}>
                        <svg width="24" height="24" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            {!menuOpen ? <path strokeLinecap="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                       : <path strokeLinecap="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />}
                        </svg>
                    </button>
                </div>

                {/* Mobile menu */}
                {menuOpen && (
                    <div className="lp-nav-mobile" style={{ display: 'none', padding: '8px 24px 16px', borderTop: `1px solid ${L.border}`, background: 'rgba(11,17,33,0.96)' }}>
                        {navLinks.map(n => (
                            <a key={n.href} href={n.href} onClick={() => setMenuOpen(false)} style={{ display: 'block', padding: '12px 0', fontSize: 15, fontWeight: 600, color: L.textSoft, textDecoration: 'none' }}>{n.label}</a>
                        ))}
                        {canLogin && <Link href={route('login')} style={{ display: 'inline-block', marginTop: 8, padding: '10px 24px', fontSize: 14, fontWeight: 700, color: '#fff', background: L.blue, borderRadius: 10, textDecoration: 'none' }}>Iniciar Sesión</Link>}
                    </div>
                )}
            </nav>

            {/* ══════════════ HERO ══════════════ */}
            <div style={{ position: 'relative', paddingTop: 64, overflow: 'hidden' }}>
                {/* Background elements */}
                <div style={{ position: 'absolute', top: '-20%', right: '-15%', width: 700, height: 700, borderRadius: '50%', background: `radial-gradient(circle, ${L.blueGlow}, transparent 70%)`, filter: 'blur(100px)', pointerEvents: 'none' }} />
                <div style={{ position: 'absolute', bottom: '-10%', left: '-10%', width: 500, height: 500, borderRadius: '50%', background: `radial-gradient(circle, ${L.purpleGlow}, transparent 70%)`, filter: 'blur(80px)', pointerEvents: 'none' }} />
                {/* Grid pattern overlay */}
                <div style={{ position: 'absolute', inset: 0, backgroundImage: `linear-gradient(${L.border}40 1px, transparent 1px), linear-gradient(90deg, ${L.border}40 1px, transparent 1px)`, backgroundSize: '60px 60px', opacity: 0.3, pointerEvents: 'none', maskImage: 'linear-gradient(to bottom, black 30%, transparent 80%)' }} />

                <Section style={{ paddingTop: 80, paddingBottom: 80, position: 'relative' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 60, alignItems: 'center' }} className="lp-hero-grid">
                        {/* Left: Copy */}
                        <div>
                            <div className="lp-fade" style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: L.greenGlow, border: `1px solid ${L.green}25`, borderRadius: 20, padding: '6px 16px', marginBottom: 24 }}>
                                <span style={{ width: 6, height: 6, borderRadius: '50%', background: L.green, animation: 'lpPulse 2s infinite' }} />
                                <span style={{ fontSize: 11, fontWeight: 700, color: L.green, letterSpacing: '0.08em', textTransform: 'uppercase' }}>En vivo — Fase piloto</span>
                            </div>

                            <h1 className="lp-fade" style={{ fontSize: 'clamp(34px, 5.5vw, 56px)', fontWeight: 900, lineHeight: 1.08, letterSpacing: '-0.04em', marginBottom: 20, animationDelay: '100ms' }}>
                                Adiós a las filas.<br />
                                <span style={{ color: L.blue }}>Hola, Olinora.</span>
                            </h1>

                            <p className="lp-fade" style={{ fontSize: 17, color: L.textSoft, lineHeight: 1.7, marginBottom: 32, maxWidth: 480, animationDelay: '200ms' }}>
                                Sistema inteligente de gestión de turnos. Tus clientes escanean un QR, toman turno desde su celular y siguen su posición en tiempo real. Sin app. Sin registro.
                            </p>

                            <div className="lp-fade" style={{ display: 'flex', gap: 12, flexWrap: 'wrap', animationDelay: '300ms' }}>
                                <Link href="/onboarding" style={{ padding: '14px 32px', fontSize: 15, fontWeight: 700, color: '#fff', background: `linear-gradient(135deg, ${L.blue}, ${L.purple})`, borderRadius: 12, textDecoration: 'none', boxShadow: `0 8px 32px ${L.blueGlow}`, transition: 'transform 0.2s' }}
                                   onMouseEnter={e => e.currentTarget.style.transform = 'translateY(-2px)'}
                                   onMouseLeave={e => e.currentTarget.style.transform = 'none'}>
                                    Probar Olinora Ahora
                                </Link>
                                <a href="#how" style={{ padding: '14px 28px', fontSize: 15, fontWeight: 600, color: L.textSoft, border: `1px solid ${L.border}`, borderRadius: 12, textDecoration: 'none', transition: 'all 0.2s' }}
                                   onMouseEnter={e => { e.currentTarget.style.borderColor = L.blue; e.currentTarget.style.color = L.text; }}
                                   onMouseLeave={e => { e.currentTarget.style.borderColor = L.border; e.currentTarget.style.color = L.textSoft; }}>
                                    ¿Cómo funciona?
                                </a>
                            </div>
                        </div>

                        {/* Right: Visual */}
                        <div className="lp-fade lp-hero-visual" style={{ position: 'relative', animationDelay: '200ms' }}>
                            {/* Mock dashboard card */}
                            <div style={{ background: L.card, border: `1px solid ${L.border}`, borderRadius: 20, padding: 28, boxShadow: `0 24px 80px rgba(0,0,0,0.4)` }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20 }}>
                                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: L.red }} />
                                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: L.amber }} />
                                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: L.green }} />
                                    <span style={{ fontSize: 11, color: L.textMuted, marginLeft: 8, fontFamily: L.mono }}>olinora.com.mx/dashboard</span>
                                </div>
                                {/* Mini KPIs */}
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginBottom: 16 }}>
                                    {[{ v: '127', l: 'Turnos hoy', c: L.blue }, { v: '3.2 min', l: 'Espera prom.', c: L.green }, { v: '98%', l: 'Satisfacción', c: L.purple }].map(k => (
                                        <div key={k.l} style={{ background: L.slate, borderRadius: 12, padding: '14px 12px', textAlign: 'center' }}>
                                            <div style={{ fontSize: 22, fontWeight: 900, color: k.c, fontFamily: L.mono }}>{k.v}</div>
                                            <div style={{ fontSize: 9, color: L.textMuted, marginTop: 4, textTransform: 'uppercase', letterSpacing: '0.08em' }}>{k.l}</div>
                                        </div>
                                    ))}
                                </div>
                                {/* Mini chart bars */}
                                <div style={{ display: 'flex', alignItems: 'flex-end', gap: 4, height: 80, padding: '0 8px' }}>
                                    {[40, 55, 45, 70, 85, 60, 90, 75, 95, 80, 65, 88].map((h, i) => (
                                        <div key={i} style={{ flex: 1, height: `${h}%`, borderRadius: '4px 4px 0 0', background: `linear-gradient(to top, ${L.blue}, ${L.purple})`, opacity: 0.6 + (h / 250), transition: 'height 0.5s ease' }} />
                                    ))}
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 6, padding: '0 8px' }}>
                                    <span style={{ fontSize: 8, color: L.textMuted, fontFamily: L.mono }}>8:00</span>
                                    <span style={{ fontSize: 8, color: L.textMuted, fontFamily: L.mono }}>20:00</span>
                                </div>
                            </div>

                            {/* Floating ticket notification */}
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

                    {/* Trust bar */}
                    <div className="lp-fade" style={{ display: 'flex', justifyContent: 'center', gap: 40, marginTop: 80, flexWrap: 'wrap', animationDelay: '400ms' }}>
                        {['Multi-sucursal', 'Tiempo real', 'QR + Móvil', 'White-label', 'Analytics'].map(t => (
                            <span key={t} style={{ fontSize: 12, fontWeight: 600, color: L.textMuted, display: 'flex', alignItems: 'center', gap: 6 }}>
                                <span style={{ width: 5, height: 5, borderRadius: '50%', background: L.blue }} /> {t}
                            </span>
                        ))}
                    </div>
                </Section>
            </div>

            {/* ══════════════ STATS BAR ══════════════ */}
            <div style={{ background: L.slate, borderTop: `1px solid ${L.border}`, borderBottom: `1px solid ${L.border}` }}>
                <div style={{ maxWidth: 1200, margin: '0 auto', padding: '20px 24px', display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 20 }} className="lp-stats-grid">
                    <StatCard value="-60%" label="Tiempo de espera" color={L.green} delay={0} />
                    <StatCard value="+35%" label="Satisfacción" color={L.blue} delay={100} />
                    <StatCard value="-40%" label="Carga operativa" color={L.purple} delay={200} />
                    <StatCard value="100%" label="Visibilidad" color={L.amber} delay={300} />
                </div>
            </div>

            {/* ══════════════ FEATURES ══════════════ */}
            <div id="features" style={{ background: L.navy }}>
                <Section>
                    <SectionTitle tag="Funciones" title="Todo lo que necesitas para eliminar las filas" subtitle="Una plataforma completa que digitaliza toda la experiencia de espera" />
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }} className="lp-features-grid">
                        <FeatureCard icon="📱" title="Kiosco Móvil QR" desc="El cliente escanea un código QR y toma turno al instante desde su celular. Sin app, sin registro." color={L.purple} delay={0} />
                        <FeatureCard icon="📺" title="Pantalla en Vivo" desc="Display para TV en sala de espera con turnos actualizándose en tiempo real vía WebSocket." color={L.cyan} delay={100} />
                        <FeatureCard icon="📊" title="Analytics Avanzado" desc="Métricas en tiempo real, tendencias diarias, heatmaps de horarios pico y ranking de operadores." color={L.amber} delay={200} />
                        <FeatureCard icon="🎨" title="Tu Marca, Tu Sistema" desc="Personaliza colores, logo, textos y configuración. Tus clientes ven tu marca, no la nuestra." color={L.red} delay={300} />
                        <FeatureCard icon="🏢" title="Multi-Sucursal" desc="Gestiona todas tus ubicaciones desde un solo panel. Compara rendimiento entre sucursales." color={L.blue} delay={400} />
                        <FeatureCard icon="⚡" title="Status en Tiempo Real" desc="El cliente sigue su posición desde su celular. Vibración y alerta cuando es su turno." color={L.green} delay={500} />
                    </div>
                </Section>
            </div>

            {/* ══════════════ HOW IT WORKS ══════════════ */}
            <div id="how" style={{ background: L.dark }}>
                <Section>
                    <SectionTitle tag="Proceso" title="3 pasos. Cero complicaciones." subtitle="Así de fácil es para tus clientes" />
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 32, position: 'relative' }} className="lp-steps-grid">
                        {/* Connector line */}
                        <div className="lp-step-line" style={{ position: 'absolute', top: 50, left: '20%', right: '20%', height: 2, background: `linear-gradient(90deg, ${L.blue}, ${L.green}, ${L.purple})`, opacity: 0.3 }} />

                        {[
                            { num: '1', title: 'Escanea', desc: 'El cliente escanea el código QR con la cámara de su celular y accede al kiosco digital.', color: L.blue },
                            { num: '2', title: 'Elige', desc: 'Selecciona el servicio que necesita y recibe su número de turno al instante.', color: L.green },
                            { num: '3', title: 'Espera Libre', desc: 'Sigue su posición en tiempo real. Recibe una alerta cuando es su turno.', color: L.purple },
                        ].map((step, i) => (
                            <div key={i} className="lp-fade" style={{ textAlign: 'center', position: 'relative', zIndex: 1, animationDelay: `${i * 150}ms` }}>
                                <div style={{ width: 64, height: 64, borderRadius: '50%', background: `${step.color}15`, border: `2px solid ${step.color}40`, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 20px', fontSize: 26, fontWeight: 900, color: step.color, fontFamily: L.mono }}>{step.num}</div>
                                <h3 style={{ fontSize: 20, fontWeight: 800, color: L.text, marginBottom: 10 }}>{step.title}</h3>
                                <p style={{ fontSize: 14, color: L.textMuted, lineHeight: 1.6, maxWidth: 280, margin: '0 auto' }}>{step.desc}</p>
                            </div>
                        ))}
                    </div>

                    {/* Highlight bar */}
                    <div className="lp-fade" style={{ marginTop: 60, background: L.blueGlow, border: `1px solid ${L.blue}20`, borderRadius: 16, padding: '20px 32px', textAlign: 'center' }}>
                        <span style={{ fontSize: 15, fontWeight: 700, color: L.text }}>Sin app. Sin registro. Sin complicaciones. Solo escanea y listo.</span>
                    </div>
                </Section>
            </div>

            {/* ══════════════ SECTORS ══════════════ */}
            <div id="sectors" style={{ background: L.navy }}>
                <Section>
                    <SectionTitle tag="Industrias" title="¿Tus clientes hacen fila? Olinora es para ti." subtitle="Funciona para cualquier negocio con atención presencial" />
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16 }} className="lp-sectors-grid">
                        {[
                            { icon: '🏥', title: 'Salud', items: 'Clínicas, hospitales, laboratorios, consultorios', color: L.green },
                            { icon: '🏛️', title: 'Gobierno', items: 'Oficinas públicas, SAT, registro civil, municipios', color: L.purple },
                            { icon: '🏦', title: 'Finanzas', items: 'Bancos, aseguradoras, cajas de ahorro, AFORES', color: L.blue },
                            { icon: '🏪', title: 'Comercio', items: 'Telecom, restaurantes, tiendas de servicio', color: L.amber },
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

            {/* ══════════════ RESULTS ══════════════ */}
            <div id="results" style={{ background: L.dark }}>
                <Section>
                    <SectionTitle tag="Impacto" title="Resultados que importan" />
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20, maxWidth: 800, margin: '0 auto' }} className="lp-results-grid">
                        {[
                            { icon: '✓', text: 'Elimina filas físicas — tus clientes esperan cómodamente', color: L.green },
                            { icon: '✓', text: 'Reduce tiempos de espera hasta un 60% desde el primer día', color: L.blue },
                            { icon: '✓', text: 'Datos en tiempo real para decisiones operativas inteligentes', color: L.purple },
                            { icon: '✓', text: 'Imagen moderna y profesional con tu propia marca', color: L.amber },
                        ].map((r, i) => (
                            <div key={i} className="lp-fade" style={{ display: 'flex', alignItems: 'flex-start', gap: 14, padding: 20, background: L.card, borderRadius: 14, border: `1px solid ${L.border}`, animationDelay: `${i * 100}ms` }}>
                                <div style={{ width: 28, height: 28, borderRadius: 8, background: `${r.color}15`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 14, color: r.color, fontWeight: 900, flexShrink: 0 }}>{r.icon}</div>
                                <p style={{ fontSize: 14, color: L.textSoft, lineHeight: 1.5 }}>{r.text}</p>
                            </div>
                        ))}
                    </div>
                </Section>
            </div>

            {/* ══════════════ CTA ══════════════ */}
            <div id="contact" style={{ background: L.navy, position: 'relative', overflow: 'hidden' }}>
                <div style={{ position: 'absolute', top: '50%', left: '50%', transform: 'translate(-50%, -50%)', width: 600, height: 600, borderRadius: '50%', background: `radial-gradient(circle, ${L.blueGlow}, transparent 70%)`, filter: 'blur(80px)', pointerEvents: 'none' }} />
                <Section style={{ textAlign: 'center', position: 'relative', paddingTop: 80, paddingBottom: 80 }}>
                    <div className="lp-fade">
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 10, marginBottom: 24 }}>
                            <div style={{ width: 44, height: 44, borderRadius: 12, background: `linear-gradient(135deg, ${L.blue}, ${L.purple})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 18, color: '#fff' }}>O</div>
                        </div>
                    </div>
                    <h2 className="lp-fade" style={{ fontSize: 'clamp(28px, 5vw, 44px)', fontWeight: 900, letterSpacing: '-0.03em', marginBottom: 16, animationDelay: '100ms' }}>
                        ¿Listo para eliminar las filas?
                    </h2>
                    <p className="lp-fade" style={{ fontSize: 17, color: L.textMuted, marginBottom: 36, maxWidth: 500, margin: '0 auto 36px', lineHeight: 1.6, animationDelay: '200ms' }}>
                        Agenda una demo personalizada y descubre cómo Olinora puede transformar la experiencia de tus clientes.
                    </p>
                    <div className="lp-fade" style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap', animationDelay: '300ms' }}>
                        <Link href="/onboarding" style={{ padding: '16px 40px', fontSize: 16, fontWeight: 700, color: '#fff', background: `linear-gradient(135deg, ${L.blue}, ${L.purple})`, borderRadius: 14, textDecoration: 'none', boxShadow: `0 8px 32px ${L.blueGlow}`, transition: 'transform 0.2s' }}
                              onMouseEnter={e => e.currentTarget.style.transform = 'translateY(-2px)'}
                              onMouseLeave={e => e.currentTarget.style.transform = 'none'}>
                            Probar Olinora Ahora
                        </Link>
                        {canLogin && (
                            <Link href={route('login')} style={{ padding: '16px 32px', fontSize: 16, fontWeight: 600, color: L.textSoft, border: `1px solid ${L.border}`, borderRadius: 14, textDecoration: 'none', transition: 'all 0.2s' }}
                                  onMouseEnter={e => { e.currentTarget.style.borderColor = L.blue; e.currentTarget.style.color = L.text; }}
                                  onMouseLeave={e => { e.currentTarget.style.borderColor = L.border; e.currentTarget.style.color = L.textSoft; }}>
                                Ya tengo cuenta
                            </Link>
                        )}
                    </div>
                    <p className="lp-fade" style={{ fontSize: 12, color: L.textMuted, marginTop: 20, animationDelay: '400ms' }}>
                        Demo gratuita · Sin compromiso · Configuración en minutos
                    </p>
                </Section>
            </div>

            {/* ══════════════ FOOTER ══════════════ */}
            <footer style={{ background: L.dark, borderTop: `1px solid ${L.border}`, padding: '40px 24px' }}>
                <div style={{ maxWidth: 1200, margin: '0 auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 16 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <div style={{ width: 28, height: 28, borderRadius: 7, background: `linear-gradient(135deg, ${L.blue}, ${L.purple})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 12, color: '#fff' }}>O</div>
                        <span style={{ fontSize: 13, color: L.textMuted }}>Olinora · Sistema Inteligente de Gestión de Turnos</span>
                    </div>
                    <div style={{ fontSize: 12, color: L.textMuted }}>
                        © {new Date().getFullYear()} Olinora. Todos los derechos reservados.
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
                .lp-card-hover:hover { transform: translateY(-4px); border-color: ${L.blue}30 !important; box-shadow: 0 16px 48px rgba(0,0,0,0.3); }

                .lp-nav-mobile { display: none !important; }

                @media (max-width: 900px) {
                    .lp-hero-grid { grid-template-columns: 1fr !important; }
                    .lp-hero-visual { display: none !important; }
                    .lp-features-grid { grid-template-columns: 1fr !important; }
                    .lp-steps-grid { grid-template-columns: 1fr !important; }
                    .lp-step-line { display: none !important; }
                    .lp-sectors-grid { grid-template-columns: repeat(2, 1fr) !important; }
                    .lp-stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
                    .lp-results-grid { grid-template-columns: 1fr !important; }
                    .lp-nav-desktop { display: none !important; }
                    .lp-nav-mobile { display: flex !important; }
                }

                @media (max-width: 500px) {
                    .lp-sectors-grid { grid-template-columns: 1fr !important; }
                    .lp-stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
                }

                /* Smooth scrolling */
                html { scroll-behavior: smooth; }
            `}</style>
        </div>
    </>);
}
