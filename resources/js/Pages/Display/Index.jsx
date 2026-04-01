// resources/js/Pages/Display/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { T, theme } from '@/Components/TurnosUI';

export default function DisplayIndex({ branches = [] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Pantalla de Turnos" />
            <div style={{ background: T.bg, minHeight: '100vh', fontFamily: T.font, color: T.text, padding: '40px 28px' }}>
                <div style={{ maxWidth: 700, margin: '0 auto', textAlign: 'center' }}>
                    <div style={{ fontSize: 48, marginBottom: 16, opacity: 0.5 }}>▣</div>
                    <h1 style={{ fontSize: 28, fontWeight: 900, marginBottom: 8, letterSpacing: '-0.02em' }}>Pantalla de Sala de Espera</h1>
                    <p style={{ fontSize: 14, color: T.textMuted, marginBottom: 40 }}>Seleccione la sucursal para mostrar en la pantalla</p>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 16 }}>
                        {branches.map((b, i) => (
                            <Link key={b.id} href={route('display.show', b.id)} style={{ textDecoration: 'none' }}>
                                <div className={`t-fade-up t-stagger-${i + 1}`} style={{
                                    background: T.card, border: `1px solid ${T.border}`, borderRadius: 16,
                                    padding: '32px 20px', textAlign: 'center', cursor: 'pointer',
                                    transition: 'all 0.3s cubic-bezier(0.4,0,0.2,1)', position: 'relative', overflow: 'hidden',
                                }}
                                onMouseEnter={e => { e.currentTarget.style.borderColor = T.blue; e.currentTarget.style.transform = 'translateY(-4px)'; e.currentTarget.style.boxShadow = `0 12px 40px ${T.blue}15`; }}
                                onMouseLeave={e => { e.currentTarget.style.borderColor = T.border; e.currentTarget.style.transform = 'none'; e.currentTarget.style.boxShadow = 'none'; }}>
                                    <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2, background: `linear-gradient(90deg, ${T.blue}, ${T.purple})` }} />
                                    <div style={{ fontSize: 32, marginBottom: 12, opacity: 0.5 }}>▣</div>
                                    <div style={{ fontSize: 16, fontWeight: 700, color: T.text, marginBottom: 4 }}>{b.name}</div>
                                    <div style={{ fontSize: 12, color: T.textMuted, fontFamily: T.mono }}>{b.code}</div>
                                </div>
                            </Link>
                        ))}
                    </div>

                    {branches.length === 0 && (
                        <div style={{ padding: 48, color: T.textMuted }}>No hay sucursales disponibles</div>
                    )}

                    <div style={{ marginTop: 40, padding: '20px', background: T.card, borderRadius: 12, border: `1px solid ${T.border}` }}>
                        <div style={{ fontSize: 13, fontWeight: 600, color: T.textSoft, marginBottom: 8 }}>Pantalla pública (sin login)</div>
                        <p style={{ fontSize: 12, color: T.textMuted, lineHeight: 1.5 }}>
                            Para TVs de sala de espera use la URL pública: <code style={{ background: T.surface, padding: '2px 6px', borderRadius: 4, fontFamily: T.mono, fontSize: 11 }}>/pantalla-publica/{'{'}<span style={{ color: T.blue }}>branch_id</span>{'}'}</code>
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
