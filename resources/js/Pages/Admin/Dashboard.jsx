// resources/js/Pages/Admin/Dashboard.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, LiveDot, MetricBar, useAutoRefresh, fmtMinutes, T } from '@/Components/TurnosUI';

const adminSections = [
    { route: 'admin.sucursales.index', label: 'Sucursales', icon: '◈', color: '#3D7AFF', gradient: 'linear-gradient(135deg, #3D7AFF15, #3D7AFF05)', countKey: 'branches', desc: 'Ubicaciones y horarios' },
    { route: 'admin.servicios.index', label: 'Servicios', icon: '⬡', color: '#00D68F', gradient: 'linear-gradient(135deg, #00D68F15, #00D68F05)', countKey: 'services', desc: 'Tipos de atención' },
    { route: 'admin.colas.index', label: 'Colas', icon: '▦', color: '#FFB020', gradient: 'linear-gradient(135deg, #FFB02015, #FFB02005)', countKey: null, desc: 'Filas y prioridades' },
    { route: 'admin.ventanillas.index', label: 'Ventanillas', icon: '▣', color: '#9D5CFF', gradient: 'linear-gradient(135deg, #9D5CFF15, #9D5CFF05)', countKey: null, desc: 'Puntos de atención' },
    { route: 'admin.usuarios.index', label: 'Usuarios', icon: '◉', color: '#00D4FF', gradient: 'linear-gradient(135deg, #00D4FF15, #00D4FF05)', countKey: 'operators', desc: 'Operadores y permisos' },
];

export default function AdminDashboard({ branches = [], currentBranchId, todayStats = {}, operators = [], services = [], branchStats = [] }) {
    useAutoRefresh(15000);
    const s = todayStats;

    // Enrich section counts from props
    const countMap = { branches: branches.length, services: services.length, operators: operators.length };
    const sections = adminSections.map(sec => ({
        ...sec,
        count: sec.countKey ? (countMap[sec.countKey] || 0) : null,
    }));

    return (
        <AuthenticatedLayout>
            <Head title="Admin - Olinora" />
            <div style={{ background: T.bg, minHeight: '100vh', padding: '24px 28px', fontFamily: T.font, color: T.text }}>

                {/* ── Header ── */}
                <div className="t-fade-up" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 28, flexWrap: 'wrap', gap: 12 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                            <h1 style={{ fontSize: 26, fontWeight: 900, letterSpacing: '-0.03em', margin: 0 }}>Panel de Control</h1>
                            <LiveDot />
                        </div>
                        <p style={{ fontSize: 12, color: T.textMuted, margin: '4px 0 0' }}>Gestión y configuración del sistema</p>
                    </div>
                    {branches.length > 0 && (
                        <select onChange={e => router.get(route('admin.dashboard'), { branch_id: e.target.value }, { preserveState: true })}
                            defaultValue={currentBranchId || ''} style={{ background: T.surface, color: T.textSoft, border: `1px solid ${T.border}`, borderRadius: 10, padding: '10px 16px', fontSize: 13, fontFamily: T.font, fontWeight: 500 }}>
                            {branches.map(b => <option key={b.id} value={b.id}>{b.name} ({b.code})</option>)}
                        </select>
                    )}
                </div>

                {/* ── Admin Navigation ── */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(170px, 1fr))', gap: 12, marginBottom: 32 }}>
                    {sections.map((sec, i) => {
                        let href;
                        try { href = route(sec.route); } catch { href = '#'; }
                        return (
                            <Link key={sec.route} href={href} style={{ textDecoration: 'none' }}>
                                <div className={`t-fade-up t-stagger-${Math.min(i + 1, 6)}`} style={{
                                    background: sec.gradient, border: `1px solid ${sec.color}18`,
                                    borderRadius: 16, padding: '22px 18px', cursor: 'pointer',
                                    transition: 'all 0.3s cubic-bezier(0.4,0,0.2,1)',
                                    position: 'relative', overflow: 'hidden', minHeight: 110,
                                    display: 'flex', flexDirection: 'column', justifyContent: 'space-between',
                                }}
                                onMouseEnter={e => {
                                    e.currentTarget.style.borderColor = `${sec.color}50`;
                                    e.currentTarget.style.transform = 'translateY(-3px)';
                                    e.currentTarget.style.boxShadow = `0 12px 32px ${sec.color}12`;
                                }}
                                onMouseLeave={e => {
                                    e.currentTarget.style.borderColor = `${sec.color}18`;
                                    e.currentTarget.style.transform = 'none';
                                    e.currentTarget.style.boxShadow = 'none';
                                }}>
                                    {/* Accent line */}
                                    <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2, background: `linear-gradient(90deg, ${sec.color}, transparent)` }} />

                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                        <div style={{ width: 38, height: 38, borderRadius: 10, background: `${sec.color}15`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 18, color: sec.color }}>
                                            {sec.icon}
                                        </div>
                                        {sec.count != null && (
                                            <span style={{ fontSize: 18, fontWeight: 900, color: sec.color, fontFamily: T.mono }}>{sec.count}</span>
                                        )}
                                    </div>

                                    <div style={{ marginTop: 14 }}>
                                        <div style={{ fontSize: 14, fontWeight: 700, color: T.text }}>{sec.label}</div>
                                        <div style={{ fontSize: 10, color: T.textMuted, marginTop: 2 }}>{sec.desc}</div>
                                    </div>
                                </div>
                            </Link>
                        );
                    })}
                </div>

                {/* ── KPIs compact ── */}
                <div className="t-fade-up t-stagger-2" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10, marginBottom: 20 }}>
                    {[
                        { l: 'Emitidos', v: s.total_issued || 0, c: T.blue },
                        { l: 'Completados', v: s.completed || 0, c: T.green },
                        { l: 'Espera', v: s.waiting || 0, c: T.amber },
                        { l: 'Atención', v: s.in_progress || 0, c: T.purple },
                    ].map(k => (
                        <div key={k.l} style={{ background: T.card, borderRadius: 12, border: `1px solid ${T.border}`, padding: '16px 14px', display: 'flex', alignItems: 'center', gap: 12 }}>
                            <div style={{ fontSize: 28, fontWeight: 900, color: k.c, fontFamily: T.mono, lineHeight: 1 }}>{k.v}</div>
                            <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', lineHeight: 1.3 }}>{k.l}</div>
                        </div>
                    ))}
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginBottom: 24 }} className="t-grid-responsive">
                    {[
                        { l: 'Cancelados', v: s.cancelled || 0, c: T.red },
                        { l: 'Espera Prom.', v: fmtMinutes(s.avg_wait), c: T.text },
                        { l: 'Rating', v: s.avg_rating ? `★ ${s.avg_rating}` : '—', c: T.amber },
                    ].map(k => (
                        <div key={k.l} style={{ background: T.card, borderRadius: 12, border: `1px solid ${T.border}`, padding: '14px', display: 'flex', alignItems: 'center', gap: 12 }}>
                            <div style={{ fontSize: 22, fontWeight: 800, color: k.c, fontFamily: T.mono }}>{k.v}</div>
                            <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.06em' }}>{k.l}</div>
                        </div>
                    ))}
                </div>

                {/* ── Operators + Services ── */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 24 }} className="t-grid-responsive">
                    <Card accent={T.blue} className="t-fade-up t-stagger-3" style={{ padding: 0 }}>
                        <div style={{ padding: '16px 20px', borderBottom: `1px solid ${T.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <span style={{ fontWeight: 700, fontSize: 14 }}>Operadores Hoy</span>
                            <Link href={route('admin.usuarios.index')} style={{ fontSize: 11, color: T.blue, textDecoration: 'none', fontWeight: 600 }}>Ver todos →</Link>
                        </div>
                        {operators.length === 0 ? <div style={{ padding: 32, textAlign: 'center', color: T.textMuted }}>Sin actividad</div> :
                            operators.slice(0, 6).map((op, i) => (
                                <div key={op.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '11px 20px', borderBottom: i < Math.min(operators.length, 6) - 1 ? `1px solid ${T.border}08` : 'none' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                        <div style={{ width: 30, height: 30, borderRadius: '50%', background: `linear-gradient(135deg, hsl(${i * 45}, 55%, 50%), hsl(${i * 45 + 30}, 55%, 40%))`, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontWeight: 800, fontSize: 11 }}>{op.name.charAt(0)}</div>
                                        <div>
                                            <div style={{ fontSize: 12, fontWeight: 600 }}>{op.name}</div>
                                            <div style={{ fontSize: 9, color: T.textMuted }}>{op.role}</div>
                                        </div>
                                    </div>
                                    <div style={{ fontSize: 18, fontWeight: 900, color: op.today_served > 0 ? T.blue : T.textMuted, fontFamily: T.mono }}>{op.today_served}</div>
                                </div>
                            ))
                        }
                    </Card>

                    <Card accent={T.green} className="t-fade-up t-stagger-4">
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                            <span style={{ fontSize: 14, fontWeight: 700 }}>Servicios Activos</span>
                            <Link href={route('admin.servicios.index')} style={{ fontSize: 11, color: T.green, textDecoration: 'none', fontWeight: 600 }}>Gestionar →</Link>
                        </div>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                            {services.map(sv => (
                                <div key={sv.id} style={{ display: 'flex', alignItems: 'center', gap: 6, background: T.surface, borderRadius: 8, padding: '8px 12px', border: `1px solid ${T.border}` }}>
                                    <span style={{ width: 8, height: 8, borderRadius: '50%', background: sv.color, boxShadow: `0 0 6px ${sv.color}40` }} />
                                    <span style={{ fontSize: 12, fontWeight: 600 }}>{sv.name}</span>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>

                {/* ── Branch comparison ── */}
                {branchStats.length > 0 && (
                    <Card accent={T.purple} className="t-fade-up t-stagger-5">
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                            <span style={{ fontSize: 14, fontWeight: 700 }}>Comparación de Sucursales</span>
                            <Link href={route('admin.sucursales.index')} style={{ fontSize: 11, color: T.purple, textDecoration: 'none', fontWeight: 600 }}>Gestionar →</Link>
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: `repeat(${Math.min(branchStats.length, 4)}, 1fr)`, gap: 12 }} className="t-grid-responsive">
                            {branchStats.map((b, i) => (
                                <div key={i} style={{ background: T.surface, borderRadius: 14, padding: 16, borderTop: `2px solid ${T.blue}` }}>
                                    <div style={{ fontSize: 14, fontWeight: 800, marginBottom: 2 }}>{b.name}</div>
                                    <div style={{ fontSize: 10, color: T.textMuted, fontFamily: T.mono, marginBottom: 12 }}>{b.code}</div>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                                        <div><div style={{ fontSize: 20, fontWeight: 900, color: T.blue, fontFamily: T.mono }}>{b.total}</div><div style={{ fontSize: 8, color: T.textMuted }}>TOTAL</div></div>
                                        <div><div style={{ fontSize: 20, fontWeight: 900, color: T.green, fontFamily: T.mono }}>{b.completed}</div><div style={{ fontSize: 8, color: T.textMuted }}>COMPLETOS</div></div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
