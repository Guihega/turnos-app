// resources/js/Pages/Dashboard.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Card, StatusBadge, FlashMessages, LiveDot, MetricBar, useAutoRefresh, fmtSeconds, fmtMinutes, T, statusMap } from '@/Components/TurnosUI';
import { useBranchChannel } from '@/Hooks/useBranchChannel';

// ── Mini Charts ──
const BarChart = ({ data, valueKey, labelKey, colorKey, height = 140, max: maxBars = 13 }) => {
    const sliced = data.slice(0, maxBars);
    const maxVal = Math.max(...sliced.map(d => d[valueKey])) || 1;
    return (
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 4, height, padding: '0 2px' }}>
            {sliced.map((d, i) => {
                const h = Math.max((d[valueKey] / maxVal) * (height - 24), 2);
                return (
                    <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3 }}>
                        <span style={{ fontSize: 9, fontWeight: 600, color: T.textMuted, fontFamily: T.mono }}>{d[valueKey] || ''}</span>
                        <div style={{
                            width: '100%', maxWidth: 36, height: h, borderRadius: 4,
                            background: `linear-gradient(180deg, ${d[colorKey] || T.blue}, color-mix(in srgb, ${d[colorKey] || T.blue} 50%, transparent))`,
                            transition: 'height 0.6s ease',
                        }} />
                        <span style={{ fontSize: 8, color: T.textMuted, textAlign: 'center', maxWidth: 48, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{d[labelKey]}</span>
                    </div>
                );
            })}
        </div>
    );
};

const DonutChart = ({ segments, size = 110 }) => {
    const total = segments.reduce((s, seg) => s + (seg.value || 0), 0) || 1;
    const cx = size / 2, cy = size / 2, r = size / 2 - 8, circ = 2 * Math.PI * r;
    let offset = 0;
    return (
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
            {/* Background ring */}
            <circle cx={cx} cy={cy} r={r} fill="none" stroke={T.border} strokeWidth={12} />
            {segments.filter(s => s.value > 0).map((seg, i) => {
                const dash = (seg.value / total) * circ;
                const el = <circle key={i} cx={cx} cy={cy} r={r} fill="none" stroke={seg.color} strokeWidth={12} strokeDasharray={`${dash} ${circ - dash}`} strokeDashoffset={-offset} transform={`rotate(-90 ${cx} ${cy})`} style={{ transition: 'stroke-dasharray 0.8s ease' }} />;
                offset += dash;
                return el;
            })}
            <text x={cx} y={cy - 4} textAnchor="middle" style={{ fontSize: 18, fontWeight: 800, fill: T.text, fontFamily: T.mono }}>{total}</text>
            <text x={cx} y={cy + 12} textAnchor="middle" style={{ fontSize: 9, fill: T.textMuted }}>turnos</text>
        </svg>
    );
};

export default function Dashboard({ branches = [], currentBranch, todayStats = {}, activeTickets = [], queues = [] }) {
    const { flash } = usePage().props;
    const [tab, setTab] = useState('overview');
    const [clock, setClock] = useState(new Date());
    const [wsConnected, setWsConnected] = useState(false);

    // ── WebSocket: real-time updates when tickets change in this branch ──
    useBranchChannel(currentBranch?.id, 'branch', {
        'TicketIssued': () => {
            setWsConnected(true);
            router.reload({ only: ['todayStats', 'activeTickets', 'queues'], preserveScroll: true });
        },
        'TicketCalled': () => {
            setWsConnected(true);
            router.reload({ only: ['todayStats', 'activeTickets', 'queues'], preserveScroll: true });
        },
        'TicketCompleted': () => {
            setWsConnected(true);
            router.reload({ only: ['todayStats', 'activeTickets', 'queues'], preserveScroll: true });
        },
        'TicketTransferred': () => {
            setWsConnected(true);
            router.reload({ only: ['todayStats', 'activeTickets', 'queues'], preserveScroll: true });
        },
    });

    // Polling fallback: slow if WS connected, fast if not
    useAutoRefresh(wsConnected ? 30000 : 8000);
    useEffect(() => { const id = setInterval(() => setClock(new Date()), 1000); return () => clearInterval(id); }, []);

    const s = todayStats;
    const tabs = [
        { id: 'overview', label: 'Resumen', icon: '◉' },
        { id: 'live', label: 'En Vivo', icon: '⚡' },
        { id: 'queues', label: 'Colas', icon: '▦' },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            <div style={{ background: T.bg, color: T.text, minHeight: '100vh', fontFamily: T.font }}>

                {/* ── Sub-header ── */}
                <div style={{ borderBottom: `1px solid ${T.border}` }}>
                    <div style={{ maxWidth: 1100, margin: '0 auto', padding: '14px 28px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                        <div style={{ fontSize: 17, fontWeight: 800, letterSpacing: '-0.02em' }}>{currentBranch?.name || 'Olinora'}</div>
                        <div style={{ fontSize: 11, color: T.textMuted }}>Resumen en tiempo real</div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                        {branches.length > 1 && (
                            <select onChange={e => router.get('/dashboard', { branch_id: e.target.value }, { preserveState: true })} defaultValue={currentBranch?.id || ''}
                                style={{ background: T.surface, color: T.textSoft, border: `1px solid ${T.border}`, borderRadius: 8, padding: '7px 12px', fontSize: 12, fontFamily: T.font, cursor: 'pointer' }}>
                                {branches.map(b => <option key={b.id} value={b.id}>{b.name} ({b.code})</option>)}
                            </select>
                        )}
                        <span style={{ fontSize: 12, color: T.textMuted, fontFamily: T.mono, fontVariantNumeric: 'tabular-nums' }}>
                            {clock.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                        </span>
                        <LiveDot label={wsConnected ? 'WS' : null} />
                    </div>
                    </div>
                </div>

                {/* ── Tabs ── */}
                <div style={{ borderBottom: `1px solid ${T.border}` }}>
                    <div style={{ maxWidth: 1100, margin: '0 auto', padding: '8px 28px', display: 'flex', gap: 2, overflowX: 'auto' }}>
                    {tabs.map(t => (
                        <button key={t.id} onClick={() => setTab(t.id)} style={{
                            padding: '8px 18px', borderRadius: 8, fontSize: 12, fontWeight: 600, border: 'none', cursor: 'pointer',
                            transition: 'all 0.2s', fontFamily: T.font, display: 'flex', alignItems: 'center', gap: 6, whiteSpace: 'nowrap',
                            background: tab === t.id ? `color-mix(in srgb, ${T.blue} 10%, transparent)` : 'transparent',
                            color: tab === t.id ? T.blue : T.textMuted,
                        }}><span style={{ fontSize: 13 }}>{t.icon}</span> {t.label}</button>
                    ))}
                    </div>
                </div>

                {/* ── Content ── */}
                <div style={{ padding: '20px 28px', maxWidth: 1100, margin: '0 auto' }}>
                    <FlashMessages flash={flash} />

                    {/* ══ OVERVIEW ══ */}
                    {tab === 'overview' && (<>
                        {/* KPI Row */}
                        <div className="t-dash-kpi" style={{ display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: 8, marginBottom: 16 }}>
                            {[
                                { label: 'Emitidos', val: s.total_issued || 0, color: T.blue },
                                { label: 'En Espera', val: s.waiting || 0, color: T.amber },
                                { label: 'Completados', val: s.completed || 0, color: T.green },
                                { label: 'Espera Prom.', val: fmtMinutes(s.avg_wait), color: T.text },
                                { label: 'Atención Prom.', val: fmtMinutes(s.avg_service), color: T.purple },
                                { label: 'Rating', val: s.avg_rating ? `★ ${s.avg_rating}` : '—', color: T.amber },
                            ].map(x => (
                                <div key={x.label} style={{
                                    background: T.card, border: `1px solid ${T.border}`, borderRadius: 10,
                                    textAlign: 'center', padding: '14px 10px',
                                }}>
                                    <div style={{ fontSize: 22, fontWeight: 900, color: x.color, fontFamily: T.mono, lineHeight: 1 }}>{x.val}</div>
                                    <div style={{ fontSize: 8, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', marginTop: 6 }}>{x.label}</div>
                                </div>
                            ))}
                        </div>

                        {/* Charts Row */}
                        <div className="t-grid-responsive" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 16 }}>
                            {/* Donut Chart */}
                            <Card accent={T.blue}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                                    <span style={{ fontSize: 13, fontWeight: 700 }}>Distribución</span>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 24 }}>
                                    <DonutChart segments={[
                                        { value: s.completed || 0, color: T.green },
                                        { value: s.waiting || 0, color: T.amber },
                                        { value: s.in_progress || 0, color: T.purple },
                                        { value: s.cancelled || 0, color: T.red },
                                    ]} />
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                        {[
                                            { label: 'Completados', val: s.completed || 0, color: T.green },
                                            { label: 'En espera', val: s.waiting || 0, color: T.amber },
                                            { label: 'En atención', val: s.in_progress || 0, color: T.purple },
                                            { label: 'Cancelados', val: s.cancelled || 0, color: T.red },
                                        ].map(seg => (
                                            <div key={seg.label} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                <div style={{ width: 8, height: 8, borderRadius: 2, background: seg.color }} />
                                                <span style={{ fontSize: 11, color: T.textSoft }}>{seg.label}</span>
                                                <span style={{ fontSize: 11, fontWeight: 700, fontFamily: T.mono, color: T.text, marginLeft: 'auto' }}>{seg.val}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </Card>

                            {/* Queues Overview */}
                            <Card accent={T.amber}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                                    <span style={{ fontSize: 13, fontWeight: 700 }}>Estado de Colas</span>
                                </div>
                                {queues.map((q, i) => (
                                    <div key={q.id} style={{
                                        display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 0',
                                        borderBottom: i < queues.length - 1 ? `1px solid color-mix(in srgb, ${T.border} 50%, transparent)` : 'none',
                                    }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                            <span style={{ fontSize: 11, background: T.surface, borderRadius: 4, padding: '2px 8px', fontFamily: T.mono, fontWeight: 700 }}>{q.prefix}</span>
                                            <span style={{ fontSize: 12, fontWeight: 600 }}>{q.name}</span>
                                        </div>
                                        <div style={{ display: 'flex', gap: 12 }}>
                                            {[
                                                { v: q.waiting, l: '◷', c: T.amber },
                                                { v: q.in_progress, l: '▸', c: T.purple },
                                                { v: q.completed, l: '✓', c: T.green },
                                            ].map(m => (
                                                <div key={m.l} style={{ textAlign: 'center' }}>
                                                    <span style={{ fontSize: 13, fontWeight: 900, fontFamily: T.mono, color: m.v > 0 ? m.c : T.textMuted }}>{m.v}</span>
                                                    <div style={{ fontSize: 9, color: T.textMuted }}>{m.l}</div>
                                                </div>
                                            ))}
                                        </div>
                                        <MetricBar value={q.waiting} max={30} color={q.waiting > 15 ? T.red : q.waiting > 5 ? T.amber : T.green} />
                                    </div>
                                ))}
                                {queues.length === 0 && <div style={{ padding: 24, color: T.textMuted, fontSize: 12 }}>Sin colas configuradas</div>}
                            </Card>
                        </div>
                    </>)}

                    {/* ══ LIVE TAB ══ */}
                    {tab === 'live' && (<>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginBottom: 16 }} className="t-dash-kpi-3">
                            {[
                                { label: 'En Espera', val: s.waiting || 0, color: T.amber },
                                { label: 'Llamados', val: s.called || 0, color: T.blue },
                                { label: 'En Atención', val: s.in_progress || 0, color: T.purple },
                            ].map(x => (
                                <div key={x.label} style={{
                                    background: T.card, border: `1px solid ${T.border}`, borderRadius: 10,
                                    textAlign: 'center', padding: '12px 14px', position: 'relative', overflow: 'hidden',
                                }}>
                                    <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2, background: `linear-gradient(90deg, ${x.color}, transparent)`, opacity: x.val > 0 ? 0.6 : 0.2 }} />
                                    <div style={{ fontSize: 24, fontWeight: 900, color: x.val > 0 ? x.color : T.textMuted, fontFamily: T.mono, lineHeight: 1 }}>{x.val}</div>
                                    <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', marginTop: 4 }}>{x.label}</div>
                                </div>
                            ))}
                        </div>

                        <Card accent={T.blue} style={{ padding: 0, overflow: 'hidden' }}>
                            <div style={{ padding: '12px 18px', borderBottom: `1px solid ${T.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span style={{ fontSize: 13, fontWeight: 700 }}>Turnos Activos</span>
                                <span style={{ fontSize: 9, color: wsConnected ? T.green : T.textMuted, fontFamily: T.mono }}>
                                    {wsConnected ? '● WebSocket activo' : '○ polling ' + (wsConnected ? '30s' : '8s')}
                                </span>
                            </div>
                            {activeTickets.length === 0 ? (
                                <div style={{ padding: 40, textAlign: 'center', color: T.textMuted, fontSize: 12 }}>
                                    <div style={{ fontSize: 28, marginBottom: 8, opacity: 0.25 }}>◷</div>Sin turnos activos
                                </div>
                            ) : (
                                <div style={{ overflowX: 'auto' }}>
                                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12, fontFamily: T.font }}>
                                        <thead>
                                            <tr>{['Turno', 'Cliente', 'Servicio', 'Estado', 'V.', 'Operador', 'Espera'].map(h => (
                                                <th key={h} style={{ padding: '10px 14px', textAlign: 'left', fontWeight: 600, color: T.textMuted, fontSize: 9, textTransform: 'uppercase', letterSpacing: '0.1em', borderBottom: `1px solid ${T.border}`, background: T.surface, fontFamily: T.mono }}>{h}</th>
                                            ))}</tr>
                                        </thead>
                                        <tbody>
                                            {activeTickets.map((t, i) => (
                                                <tr key={t.id} className="t-table-row" style={{ borderBottom: `1px solid color-mix(in srgb, ${T.border} 50%, transparent)` }}>
                                                    <td style={{ padding: '11px 14px', fontWeight: 700, fontFamily: T.mono, fontSize: 13 }}>{t.display_number}</td>
                                                    <td style={{ padding: '11px 14px', color: T.textSoft }}>{t.customer_name || '—'}</td>
                                                    <td style={{ padding: '11px 14px' }}>
                                                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                            <span style={{ width: 6, height: 6, borderRadius: '50%', background: t.service_color || T.blue, boxShadow: `0 0 5px ${t.service_color || T.blue}40` }} />
                                                            {t.service_name}
                                                        </span>
                                                    </td>
                                                    <td style={{ padding: '11px 14px' }}><StatusBadge status={t.status} size="sm" /></td>
                                                    <td style={{ padding: '11px 14px', fontFamily: T.mono, fontWeight: 600, color: t.counter_number ? T.blue : T.textMuted }}>{t.counter_number || '—'}</td>
                                                    <td style={{ padding: '11px 14px', color: T.textSoft, fontSize: 11 }}>{t.operator_name || '—'}</td>
                                                    <td style={{ padding: '11px 14px', fontFamily: T.mono, fontWeight: 600, color: t.wait_seconds > 600 ? T.red : t.wait_seconds > 300 ? T.amber : T.textSoft }}>{fmtSeconds(t.wait_seconds)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Card>
                    </>)}

                    {/* ══ QUEUES TAB ══ */}
                    {tab === 'queues' && (
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 14 }}>
                            {queues.map((q, i) => (
                                <Card key={q.id} accent={T.blue} glow={q.waiting > 10 ? T.amberGlow : null} className={`t-fade-up t-stagger-${Math.min(i + 1, 6)}`}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                                        <span style={{ fontSize: 15, fontWeight: 800 }}>{q.name}</span>
                                        <span style={{ fontSize: 11, color: T.textMuted, background: T.surface, borderRadius: 5, padding: '3px 10px', fontFamily: T.mono, fontWeight: 700 }}>{q.prefix}</span>
                                    </div>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 10, marginBottom: 14 }}>
                                        {[
                                            { v: q.waiting, l: 'Esperando', c: T.amber },
                                            { v: q.in_progress, l: 'Atendiendo', c: T.purple },
                                            { v: q.completed, l: 'Completados', c: T.green },
                                        ].map(m => (
                                            <div key={m.l} style={{ textAlign: 'center', background: T.surface, borderRadius: 8, padding: 12 }}>
                                                <div style={{ fontSize: 24, fontWeight: 900, color: m.v > 0 ? m.c : T.textMuted, fontFamily: T.mono }}>{m.v}</div>
                                                <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.06em' }}>{m.l}</div>
                                            </div>
                                        ))}
                                    </div>
                                    <MetricBar value={q.waiting} max={30} color={q.waiting > 15 ? T.red : q.waiting > 5 ? T.amber : T.green} height={6} />
                                </Card>
                            ))}
                            {queues.length === 0 && <div style={{ padding: 48, textAlign: 'center', color: T.textMuted, fontSize: 12 }}>Sin colas configuradas</div>}
                        </div>
                    )}
                </div>

                <style>{`
                    @media (max-width: 768px) {
                        .t-dash-kpi { grid-template-columns: repeat(2, 1fr) !important; }
                        .t-dash-kpi-3 { grid-template-columns: 1fr !important; }
                        .t-grid-responsive { grid-template-columns: 1fr !important; }
                    }
                `}</style>
            </div>
        </AuthenticatedLayout>
    );
}
