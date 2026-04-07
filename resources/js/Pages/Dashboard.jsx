// resources/js/Pages/Dashboard.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import { Card, StatusBadge, FlashMessages, LiveDot, MetricBar, useAutoRefresh, fmtSeconds, fmtMinutes, T, theme, statusMap } from '@/Components/TurnosUI';

// ── Mini Charts ──
const Sparkline = ({ data, color, height = 28, width = 80 }) => {
    if (!data || data.length < 2) return null;
    const max = Math.max(...data), min = Math.min(...data), range = max - min || 1;
    const pts = data.map((v, i) => `${(i / (data.length - 1)) * width},${height - ((v - min) / range) * (height - 4) - 2}`).join(' ');
    return <svg width={width} height={height} style={{ display: 'block' }}><polyline fill="none" stroke={color} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" points={pts} /></svg>;
};

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
                        <div style={{ width: '100%', maxWidth: 36, height: h, borderRadius: 4, background: d[colorKey] || T.blue, transition: 'height 0.6s ease' }} />
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
    useAutoRefresh(8000);
    useEffect(() => { const id = setInterval(() => setClock(new Date()), 1000); return () => clearInterval(id); }, []);

    const s = todayStats;
    const tabs = [
        { id: 'overview', label: 'Resumen', icon: '◉' },
        { id: 'live', label: 'En Vivo', icon: '⚡' },
        { id: 'queues', label: 'Colas', icon: '▦' },
    ];

    // Build hourly data from activeTickets timestamps (approximate)
    const hours = Array.from({ length: 13 }, (_, i) => ({ hour: i + 7, label: `${i + 7}:00`, issued: 0, color: T.blue }));

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">Dashboard</h2>}>
            <Head title="Dashboard" />
            <div style={{ background: T.bg, color: T.text, minHeight: '100vh', fontFamily: T.font, padding: 0, margin: 0 }}>

                {/* ── Sub-header: Branch + Clock ── */}
                <div style={{ padding: '16px 24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: `1px solid ${T.border}` }}>
                    <div>
                        <div style={{ fontSize: 18, fontWeight: 800, letterSpacing: '-0.02em' }}>{currentBranch?.name || 'Olinora'}</div>
                        <div style={{ fontSize: 11, color: T.textMuted }}>Resumen en tiempo real</div>
                    </div>
                    <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                        {branches.length > 1 && (
                            <select onChange={e => router.get('/dashboard', { branch_id: e.target.value }, { preserveState: true })} defaultValue={currentBranch?.id || ''}
                                style={{ background: T.surface, color: T.textSoft, border: `1px solid ${T.border}`, borderRadius: 8, padding: '7px 12px', fontSize: 12, fontFamily: T.font }}>
                                {branches.map(b => <option key={b.id} value={b.id}>{b.name} ({b.code})</option>)}
                            </select>
                        )}
                        <span style={{ fontSize: 12, color: T.textMuted, fontFamily: T.mono, fontVariantNumeric: 'tabular-nums' }}>
                            {clock.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                        </span>
                        <LiveDot />
                    </div>
                </div>

                {/* ── Tabs ── */}
                <div style={{ display: 'flex', gap: 2, padding: '8px 24px', borderBottom: `1px solid ${T.border}`, background: T.card, overflowX: 'auto' }}>
                    {tabs.map(t => (
                        <button key={t.id} onClick={() => setTab(t.id)} style={{
                            padding: '8px 18px', borderRadius: 8, fontSize: 12, fontWeight: 600, border: 'none', cursor: 'pointer',
                            transition: 'all 0.2s', fontFamily: T.font, display: 'flex', alignItems: 'center', gap: 6, whiteSpace: 'nowrap',
                            background: tab === t.id ? `${T.blue}15` : 'transparent', color: tab === t.id ? T.blue : T.textMuted,
                        }}><span style={{ fontSize: 13 }}>{t.icon}</span> {t.label}</button>
                    ))}
                </div>

                <FlashMessages flash={flash} />

                {/* ── Content ── */}
                <div style={{ padding: '20px 24px', maxWidth: 1400, margin: '0 auto' }}>

                    {/* ══ OVERVIEW ══ */}
                    {tab === 'overview' && (<>
                        {/* KPI Row */}
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 12, marginBottom: 20 }} className="t-grid-responsive">
                            {[
                                { label: 'Turnos Emitidos', value: s.total_issued || 0, icon: '⬡', color: T.blue },
                                { label: 'Completados', value: s.completed || 0, icon: '◆', color: T.green },
                                { label: 'Tiempo Espera Prom.', value: fmtMinutes(s.avg_wait), icon: '⏱', color: T.text },
                                { label: 'Rating Promedio', value: s.avg_rating ? `${s.avg_rating}/5` : '—', icon: '★', color: T.amber },
                            ].map((k, i) => (
                                <Card key={k.label} className={`t-fade-up t-stagger-${i + 1}`}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                        <span style={{ fontSize: 10, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em' }}>{k.label}</span>
                                        <span style={{ fontSize: 14, opacity: 0.6 }}>{k.icon}</span>
                                    </div>
                                    <div style={{ fontSize: 30, fontWeight: 900, color: k.color, fontFamily: T.mono, letterSpacing: '-0.03em', margin: '6px 0 4px' }}>{k.value}</div>
                                </Card>
                            ))}
                        </div>

                        {/* Charts row */}
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 260px', gap: 14, marginBottom: 20 }} className="t-grid-responsive">
                            {/* Service breakdown as bars */}
                            <Card className="t-fade-up t-stagger-3">
                                <div style={{ fontSize: 12, fontWeight: 700, color: T.textSoft, marginBottom: 14 }}>Estado por Colas</div>
                                {queues.length > 0 ? (
                                    <BarChart data={queues.map(q => ({ ...q, label: q.name, value: (q.waiting || 0) + (q.in_progress || 0) + (q.completed || 0), color: T.blue }))} valueKey="value" labelKey="label" colorKey="color" height={140} />
                                ) : (
                                    <div style={{ textAlign: 'center', padding: 32, color: T.textMuted }}>Sin datos de colas</div>
                                )}
                            </Card>

                            {/* Status donut */}
                            <Card className="t-fade-up t-stagger-4" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                                <div style={{ fontSize: 12, fontWeight: 700, color: T.textSoft, marginBottom: 14, alignSelf: 'flex-start' }}>Estado Actual</div>
                                <DonutChart size={110} segments={[
                                    { value: s.completed || 0, color: T.green },
                                    { value: s.waiting || 0, color: T.amber },
                                    { value: s.in_progress || 0, color: T.purple },
                                    { value: s.called || 0, color: T.blue },
                                    { value: s.cancelled || 0, color: T.red },
                                ]} />
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '5px 12px', marginTop: 14, justifyContent: 'center' }}>
                                    {[
                                        { l: 'Completado', c: T.green, v: s.completed || 0 },
                                        { l: 'Espera', c: T.amber, v: s.waiting || 0 },
                                        { l: 'Atención', c: T.purple, v: s.in_progress || 0 },
                                        { l: 'Cancelado', c: T.red, v: s.cancelled || 0 },
                                    ].map(x => (
                                        <span key={x.l} style={{ fontSize: 9, color: T.textMuted, display: 'flex', alignItems: 'center', gap: 4 }}>
                                            <span style={{ width: 6, height: 6, borderRadius: '50%', background: x.c }} /> {x.l} ({x.v})
                                        </span>
                                    ))}
                                </div>
                            </Card>
                        </div>

                        {/* Queue status cards */}
                        <Card className="t-fade-up t-stagger-5">
                            <div style={{ fontSize: 12, fontWeight: 700, color: T.textSoft, marginBottom: 14 }}>Estado de Colas</div>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 12 }}>
                                {queues.map(q => (
                                    <div key={q.id} style={{ background: T.surface, borderRadius: T.radiusSm, padding: 14, display: 'flex', flexDirection: 'column', gap: 8 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                            <span style={{ fontWeight: 700, fontSize: 13 }}>{q.name}</span>
                                            <span style={{ fontSize: 10, color: T.textMuted, background: T.card, borderRadius: 4, padding: '2px 7px', fontFamily: T.mono, fontWeight: 700 }}>{q.prefix}</span>
                                        </div>
                                        <div style={{ display: 'flex', gap: 12 }}>
                                            {[
                                                { v: q.waiting, l: 'esperando', c: T.amber },
                                                { v: q.in_progress, l: 'atendiendo', c: T.purple },
                                                { v: q.completed, l: 'completados', c: T.green },
                                            ].map(m => (
                                                <div key={m.l} style={{ textAlign: 'center' }}>
                                                    <div style={{ fontSize: 20, fontWeight: 800, color: m.v > 0 ? m.c : T.textMuted, fontFamily: T.mono }}>{m.v}</div>
                                                    <div style={{ fontSize: 9, color: T.textMuted }}>{m.l}</div>
                                                </div>
                                            ))}
                                        </div>
                                        <MetricBar value={q.waiting} max={30} color={q.waiting > 15 ? T.red : q.waiting > 5 ? T.amber : T.green} />
                                    </div>
                                ))}
                                {queues.length === 0 && <div style={{ padding: 24, color: T.textMuted }}>Sin colas configuradas</div>}
                            </div>
                        </Card>
                    </>)}

                    {/* ══ LIVE TAB ══ */}
                    {tab === 'live' && (<>
                        <div style={{ display: 'flex', gap: 10, marginBottom: 16 }}>
                            {[
                                { label: 'En Espera', val: s.waiting || 0, color: T.amber },
                                { label: 'Llamados', val: s.called || 0, color: T.blue },
                                { label: 'En Atención', val: s.in_progress || 0, color: T.purple },
                            ].map(x => (
                                <Card key={x.label} style={{ flex: 1, textAlign: 'center', padding: '14px 16px' }} glow={x.val > 0 ? `${x.color}10` : null}>
                                    <div style={{ fontSize: 30, fontWeight: 900, color: x.val > 0 ? x.color : T.textMuted, fontFamily: T.mono }}>{x.val}</div>
                                    <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em' }}>{x.label}</div>
                                </Card>
                            ))}
                        </div>

                        <Card accent={T.blue} style={{ padding: 0, overflow: 'hidden' }}>
                            <div style={{ padding: '14px 18px', borderBottom: `1px solid ${T.border}`, display: 'flex', justifyContent: 'space-between' }}>
                                <span style={{ fontSize: 14, fontWeight: 700 }}>Turnos Activos</span>
                                <span style={{ fontSize: 10, color: T.textMuted, fontFamily: T.mono }}>auto-refresh 8s</span>
                            </div>
                            {activeTickets.length === 0 ? (
                                <div style={{ padding: 48, textAlign: 'center', color: T.textMuted }}>
                                    <div style={{ fontSize: 32, marginBottom: 8, opacity: 0.3 }}>◷</div>Sin turnos activos
                                </div>
                            ) : (
                                <div style={{ overflowX: 'auto' }}>
                                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12, fontFamily: T.font }}>
                                        <thead>
                                            <tr>{['Turno', 'Cliente', 'Servicio', 'Estado', 'V.', 'Operador', 'Espera'].map(h => (
                                                <th key={h} style={{ padding: '11px 14px', textAlign: 'left', fontWeight: 600, color: T.textMuted, fontSize: 9, textTransform: 'uppercase', letterSpacing: '0.1em', borderBottom: `1px solid ${T.border}` }}>{h}</th>
                                            ))}</tr>
                                        </thead>
                                        <tbody>
                                            {activeTickets.map((t, i) => {
                                                const st = statusMap[t.status] || statusMap.waiting;
                                                return (
                                                    <tr key={t.id} className={`t-fade-up t-stagger-${Math.min(i + 1, 8)}`} style={{ borderBottom: `1px solid ${T.border}08` }}>
                                                        <td style={{ padding: '12px 14px', fontWeight: 700, fontFamily: T.mono, fontSize: 13 }}>{t.display_number}</td>
                                                        <td style={{ padding: '12px 14px', color: T.textSoft }}>{t.customer_name || '—'}</td>
                                                        <td style={{ padding: '12px 14px' }}>
                                                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                                <span style={{ width: 7, height: 7, borderRadius: '50%', background: t.service_color || T.blue, boxShadow: `0 0 6px ${t.service_color || T.blue}40` }} />
                                                                {t.service_name}
                                                            </span>
                                                        </td>
                                                        <td style={{ padding: '12px 14px' }}><StatusBadge status={t.status} size="sm" /></td>
                                                        <td style={{ padding: '12px 14px', fontFamily: T.mono, fontWeight: 600, color: t.counter_number ? T.blue : T.textMuted }}>{t.counter_number || '—'}</td>
                                                        <td style={{ padding: '12px 14px', color: T.textSoft, fontSize: 11 }}>{t.operator_name || '—'}</td>
                                                        <td style={{ padding: '12px 14px', fontFamily: T.mono, fontWeight: 600, color: t.wait_seconds > 600 ? T.red : t.wait_seconds > 300 ? T.amber : T.textSoft }}>{fmtSeconds(t.wait_seconds)}</td>
                                                    </tr>
                                                );
                                            })}
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
                                <Card key={q.id} accent={T.blue} glow={q.waiting > 10 ? T.amberGlow : null} className={`t-fade-up t-stagger-${i + 1}`}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                                        <span style={{ fontSize: 16, fontWeight: 800 }}>{q.name}</span>
                                        <span style={{ fontSize: 12, color: T.textMuted, background: T.surface, borderRadius: 5, padding: '3px 10px', fontFamily: T.mono, fontWeight: 700 }}>{q.prefix}</span>
                                    </div>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 10, marginBottom: 14 }}>
                                        {[
                                            { v: q.waiting, l: 'Esperando', c: T.amber },
                                            { v: q.in_progress, l: 'Atendiendo', c: T.purple },
                                            { v: q.completed, l: 'Completados', c: T.green },
                                        ].map(m => (
                                            <div key={m.l} style={{ textAlign: 'center', background: T.surface, borderRadius: T.radiusSm, padding: 12 }}>
                                                <div style={{ fontSize: 26, fontWeight: 900, color: m.v > 0 ? m.c : T.textMuted, fontFamily: T.mono }}>{m.v}</div>
                                                <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.06em' }}>{m.l}</div>
                                            </div>
                                        ))}
                                    </div>
                                    <MetricBar value={q.waiting} max={30} color={q.waiting > 15 ? T.red : q.waiting > 5 ? T.amber : T.green} height={6} />
                                </Card>
                            ))}
                            {queues.length === 0 && <div style={{ padding: 48, textAlign: 'center', color: T.textMuted }}>Sin colas configuradas</div>}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
