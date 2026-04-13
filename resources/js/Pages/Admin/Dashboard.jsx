// resources/js/Pages/Admin/Dashboard.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Card, LiveDot, MetricBar, Avatar, useAutoRefresh, fmtMinutes, PageShell, PageHeader, T } from '@/Components/TurnosUI';
import { useBranchChannel } from '@/Hooks/useBranchChannel';

const adminSections = [
    { route: 'admin.sucursales.index', label: 'Sucursales', icon: '◈', color: '#3D7AFF', countKey: 'branches', desc: 'Ubicaciones y horarios' },
    { route: 'admin.servicios.index', label: 'Servicios', icon: '⬡', color: '#00D68F', countKey: 'services', desc: 'Tipos de atención' },
    { route: 'admin.colas.index', label: 'Colas', icon: '▦', color: '#FFB020', countKey: null, desc: 'Filas y prioridades' },
    { route: 'admin.ventanillas.index', label: 'Ventanillas', icon: '▣', color: '#9D5CFF', countKey: null, desc: 'Puntos de atención' },
    { route: 'admin.usuarios.index', label: 'Usuarios', icon: '◉', color: '#00D4FF', countKey: 'operators', desc: 'Operadores y permisos' },
    { route: 'admin.qr.index', label: 'Códigos QR', icon: '⬢', color: '#EC4899', countKey: null, desc: 'QR para kioscos móviles' },
    { route: 'admin.analytics', label: 'Analytics', icon: '◧', color: '#6366F1', countKey: null, desc: 'Métricas y tendencias' },
    { route: 'admin.settings.edit', label: 'Personalización', icon: '⚙', color: '#8B95AD', countKey: null, desc: 'Marca y configuración' },
];

function NavCard({ section, index }) {
    let href;
    try { href = route(section.route); } catch { href = '#'; }
    return (
        <Link href={href} style={{ textDecoration: 'none' }}>
            <div className={`t-fade-up t-stagger-${Math.min(index + 1, 8)}`} style={{
                background: T.card, border: `1px solid ${T.border}`,
                borderRadius: 12, padding: '14px 16px', cursor: 'pointer',
                transition: 'all 0.25s cubic-bezier(0.4,0,0.2,1)',
                position: 'relative', overflow: 'hidden',
                display: 'flex', alignItems: 'center', gap: 12,
            }}
            onMouseEnter={e => {
                e.currentTarget.style.borderColor = `${section.color}50`;
                e.currentTarget.style.transform = 'translateY(-2px)';
                e.currentTarget.style.boxShadow = `0 8px 24px ${section.color}10`;
            }}
            onMouseLeave={e => {
                e.currentTarget.style.borderColor = `var(--t-border)`;
                e.currentTarget.style.transform = 'none';
                e.currentTarget.style.boxShadow = 'none';
            }}>
                {/* Accent line */}
                <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2, background: `linear-gradient(90deg, ${section.color}, transparent)`, opacity: 0.6 }} />

                <div style={{
                    width: 32, height: 32, borderRadius: 8, flexShrink: 0,
                    background: `color-mix(in srgb, ${section.color} 10%, transparent)`,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: 14, color: section.color,
                }}>
                    {section.icon}
                </div>

                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: T.text }}>{section.label}</div>
                    <div style={{ fontSize: 9, color: T.textMuted, marginTop: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{section.desc}</div>
                </div>

                {section.count != null && (
                    <span style={{ fontSize: 15, fontWeight: 900, color: section.color, fontFamily: T.mono, flexShrink: 0 }}>{section.count}</span>
                )}
            </div>
        </Link>
    );
}

function KPICompact({ label, value, color }) {
    return (
        <div style={{
            background: T.card, borderRadius: 10, border: `1px solid ${T.border}`,
            padding: '12px 14px', textAlign: 'center',
        }}>
            <div style={{ fontSize: 20, fontWeight: 900, color, fontFamily: T.mono, lineHeight: 1 }}>{value}</div>
            <div style={{ fontSize: 8, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.06em', marginTop: 4 }}>{label}</div>
        </div>
    );
}

export default function AdminDashboard({ branches = [], currentBranchId, todayStats = {}, operators = [], services = [], branchStats = [] }) {
    const [wsConnected, setWsConnected] = useState(false);

    // ── WebSocket: real-time updates when tickets change in selected branch ──
    useBranchChannel(currentBranchId, 'branch', {
        'TicketIssued': () => {
            setWsConnected(true);
            router.reload({ only: ['todayStats', 'operators', 'branchStats'], preserveScroll: true });
        },
        'TicketCalled': () => {
            setWsConnected(true);
            router.reload({ only: ['todayStats', 'operators', 'branchStats'], preserveScroll: true });
        },
        'TicketCompleted': () => {
            setWsConnected(true);
            router.reload({ only: ['todayStats', 'operators', 'branchStats'], preserveScroll: true });
        },
        'TicketTransferred': () => {
            setWsConnected(true);
            router.reload({ only: ['todayStats', 'operators', 'branchStats'], preserveScroll: true });
        },
    });

    // Polling fallback: slow if WS connected, fast if not
    useAutoRefresh(wsConnected ? 30000 : 15000);

    const s = todayStats;
    const countMap = { branches: branches.length, services: services.length, operators: operators.length };
    const sections = adminSections.map(sec => ({
        ...sec,
        count: sec.countKey ? (countMap[sec.countKey] || 0) : null,
    }));

    const operatorColors = ['#3D7AFF', '#00D68F', '#9D5CFF', '#FFB020', '#00D4FF', '#EC4899'];

    return (
        <AuthenticatedLayout>
            <Head title="Admin — Olinora" />
            <div className="t-page-shell" style={{ background: T.bg, minHeight: '100vh', padding: T.pagePadding, fontFamily: T.font, color: T.text }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>

                {/* ── Header ── */}
                <PageHeader
                    title="Panel de Control"
                    subtitle="Gestión y configuración del sistema"
                    actions={
                        branches.length > 0 ? (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <LiveDot label={wsConnected ? 'WS' : 'En vivo'} />
                                <select
                                    onChange={e => router.get(route('admin.dashboard'), { branch_id: e.target.value }, { preserveState: true })}
                                    defaultValue={currentBranchId || ''}
                                    style={{
                                        background: T.surface, color: T.textSoft, border: `1px solid ${T.border}`,
                                        borderRadius: 10, padding: '9px 16px', fontSize: 12, fontFamily: T.font, fontWeight: 500, cursor: 'pointer',
                                    }}>
                                    {branches.map(b => <option key={b.id} value={b.id}>{b.name} ({b.code})</option>)}
                                </select>
                            </div>
                        ) : null
                    }
                />

                {/* ── Admin Navigation Grid ── */}
                <div className="t-nav-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10, marginBottom: 28 }}>
                    {sections.map((sec, i) => <NavCard key={sec.route} section={sec} index={i} />)}
                </div>

                {/* ── KPIs (single row) ── */}
                <div className="t-kpi-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 8, marginBottom: 20 }}>
                    <KPICompact label="Emitidos" value={s.total_issued || 0} color={T.blue} />
                    <KPICompact label="Completados" value={s.completed || 0} color={T.green} />
                    <KPICompact label="En espera" value={s.waiting || 0} color={T.amber} />
                    <KPICompact label="En atención" value={s.in_progress || 0} color={T.purple} />
                    <KPICompact label="Cancelados" value={s.cancelled || 0} color={T.red} />
                    <KPICompact label="Espera prom." value={fmtMinutes(s.avg_wait)} color={T.text} />
                    <KPICompact label="Rating" value={s.avg_rating ? `★ ${s.avg_rating}` : '—'} color={T.amber} />
                </div>

                {/* ── Operators + Services ── */}
                <div className="t-grid-responsive" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 24 }}>
                    {/* Operators */}
                    <Card accent={T.blue} className="t-fade-up t-stagger-3" style={{ padding: 0 }}>
                        <div style={{ padding: '14px 20px', borderBottom: `1px solid ${T.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <span style={{ fontWeight: 700, fontSize: 13 }}>Operadores hoy</span>
                            <Link href={route('admin.usuarios.index')} style={{ fontSize: 11, color: T.blue, textDecoration: 'none', fontWeight: 600 }}>Ver todos →</Link>
                        </div>
                        {operators.length === 0 ? (
                            <div style={{ padding: 32, textAlign: 'center', color: T.textMuted, fontSize: 12 }}>Sin actividad registrada</div>
                        ) : (
                            operators.slice(0, 6).map((op, i) => (
                                <div key={op.id} style={{
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    padding: '10px 20px',
                                    borderBottom: i < Math.min(operators.length, 6) - 1 ? `1px solid color-mix(in srgb, ${T.border} 50%, transparent)` : 'none',
                                }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                        <Avatar name={op.name} size={28} color={`linear-gradient(135deg, ${operatorColors[i % operatorColors.length]}, ${operatorColors[(i + 1) % operatorColors.length]})`} />
                                        <div>
                                            <div style={{ fontSize: 12, fontWeight: 600 }}>{op.name}</div>
                                            <div style={{ fontSize: 9, color: T.textMuted }}>{op.role}</div>
                                        </div>
                                    </div>
                                    <div style={{
                                        fontSize: 16, fontWeight: 900, fontFamily: T.mono,
                                        color: op.today_served > 0 ? T.blue : T.textMuted,
                                    }}>{op.today_served}</div>
                                </div>
                            ))
                        )}
                    </Card>

                    {/* Services */}
                    <Card accent={T.green} className="t-fade-up t-stagger-4">
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                            <span style={{ fontSize: 13, fontWeight: 700 }}>Servicios activos</span>
                            <Link href={route('admin.servicios.index')} style={{ fontSize: 11, color: T.green, textDecoration: 'none', fontWeight: 600 }}>Gestionar →</Link>
                        </div>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                            {services.map(sv => (
                                <div key={sv.id} style={{
                                    display: 'flex', alignItems: 'center', gap: 6,
                                    background: T.surface, borderRadius: 8, padding: '7px 12px',
                                    border: `1px solid ${T.border}`,
                                }}>
                                    <span style={{ width: 7, height: 7, borderRadius: '50%', background: sv.color, boxShadow: `0 0 6px ${sv.color}40` }} />
                                    <span style={{ fontSize: 11, fontWeight: 600 }}>{sv.name}</span>
                                </div>
                            ))}
                            {services.length === 0 && <span style={{ fontSize: 12, color: T.textMuted }}>Sin servicios configurados</span>}
                        </div>
                    </Card>
                </div>

                {/* ── Branch comparison ── */}
                {branchStats.length > 0 && (
                    <Card accent={T.purple} className="t-fade-up t-stagger-5">
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                            <span style={{ fontSize: 13, fontWeight: 700 }}>Comparación de sucursales</span>
                            <Link href={route('admin.sucursales.index')} style={{ fontSize: 11, color: T.purple, textDecoration: 'none', fontWeight: 600 }}>Gestionar →</Link>
                        </div>
                        <div className="t-grid-responsive" style={{ display: 'grid', gridTemplateColumns: `repeat(${Math.min(branchStats.length, 4)}, 1fr)`, gap: 10 }}>
                            {branchStats.map((b, i) => (
                                <div key={i} style={{ background: T.surface, borderRadius: 12, padding: 14, borderTop: `2px solid ${T.blue}` }}>
                                    <div style={{ fontSize: 13, fontWeight: 800, marginBottom: 2 }}>{b.name}</div>
                                    <div style={{ fontSize: 10, color: T.textMuted, fontFamily: T.mono, marginBottom: 10 }}>{b.code}</div>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                                        <div>
                                            <div style={{ fontSize: 18, fontWeight: 900, color: T.blue, fontFamily: T.mono }}>{b.total}</div>
                                            <div style={{ fontSize: 8, color: T.textMuted, textTransform: 'uppercase' }}>Total</div>
                                        </div>
                                        <div>
                                            <div style={{ fontSize: 18, fontWeight: 900, color: T.green, fontFamily: T.mono }}>{b.completed}</div>
                                            <div style={{ fontSize: 8, color: T.textMuted, textTransform: 'uppercase' }}>Completos</div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}

                {/* Responsive */}
                <style>{`
                    @media (max-width: 1024px) {
                        .t-nav-grid { grid-template-columns: repeat(4, 1fr) !important; }
                        .t-kpi-grid { grid-template-columns: repeat(4, 1fr) !important; }
                    }
                    @media (max-width: 768px) {
                        .t-nav-grid { grid-template-columns: repeat(2, 1fr) !important; }
                        .t-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; }
                        .t-grid-responsive { grid-template-columns: 1fr !important; }
                    }
                    @media (max-width: 480px) {
                        .t-page-shell { padding: 16px 14px !important; }
                    }
                `}</style>
                </div>{/* end maxWidth */}
            </div>
        </AuthenticatedLayout>
    );
}
