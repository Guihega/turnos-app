/**
 * Olinora — Analytics Dashboard (Priority 2)
 * Consumes existing DashboardController API endpoints via fetch
 * Refined Industrial aesthetic
 */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import { HourlyChart, TrendChart, ServiceChart, OperatorChart, WaitHeatmap, BranchComparisonChart, COLORS } from '@/Components/Charts';

const V = (n) => `var(${n})`;

// ── Helpers ─────────────────────────────────────────────────────
const fmtMin = (seconds) => seconds ? `${Math.round(seconds / 60)}m` : '—';
const fmtPct = (a, b) => b ? `${Math.round((a / b) * 100)}%` : '—';
const delta = (current, previous) => {
    if (!previous || !current) return null;
    const diff = ((current - previous) / previous) * 100;
    return { value: Math.abs(Math.round(diff)), positive: diff >= 0 };
};

// ── Card wrapper ────────────────────────────────────────────────
function ACard({ title, subtitle, children, span, style: extraStyle }) {
    return (
        <div style={{
            background: V('--t-card'), border: `1px solid ${V('--t-border')}`, borderRadius: 14,
            padding: '20px 22px', gridColumn: span ? `span ${span}` : undefined,
            ...extraStyle,
        }}>
            {title && (
                <div style={{ marginBottom: 16 }}>
                    <div style={{ fontSize: 13, fontWeight: 700, color: V('--t-text') }}>{title}</div>
                    {subtitle && <div style={{ fontSize: 11, color: V('--t-text-muted'), marginTop: 2 }}>{subtitle}</div>}
                </div>
            )}
            {children}
        </div>
    );
}

// ── KPI Card ────────────────────────────────────────────────────
function KPI({ label, value, suffix, icon, color, delta: d }) {
    return (
        <div style={{
            background: V('--t-card'), border: `1px solid ${V('--t-border')}`, borderRadius: 14,
            padding: '18px 20px', position: 'relative', overflow: 'hidden',
        }}>
            {/* Accent bar */}
            <div style={{ position: 'absolute', top: 0, left: 0, width: 4, height: '100%', background: color, borderRadius: '14px 0 0 14px', opacity: 0.6 }} />
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                    <div style={{ fontSize: 10, fontWeight: 600, color: V('--t-text-muted'), textTransform: 'uppercase', letterSpacing: '0.08em',
                        fontFamily: "'JetBrains Mono', monospace" }}>{label}</div>
                    <div style={{ fontSize: 28, fontWeight: 900, color: color || V('--t-text'), fontFamily: "'JetBrains Mono', monospace",
                        letterSpacing: '-0.03em', marginTop: 4, lineHeight: 1 }}>
                        {value}<span style={{ fontSize: 14, fontWeight: 600, opacity: 0.6 }}>{suffix}</span>
                    </div>
                </div>
                {d && (
                    <div style={{
                        fontSize: 11, fontWeight: 700, padding: '3px 8px', borderRadius: 6,
                        background: d.positive ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)',
                        color: d.positive ? COLORS.green : COLORS.red,
                        fontFamily: "'JetBrains Mono', monospace",
                    }}>
                        {d.positive ? '↑' : '↓'} {d.value}%
                    </div>
                )}
            </div>
        </div>
    );
}

// ── Period Selector ─────────────────────────────────────────────
function PeriodSelector({ value, onChange }) {
    const options = [
        { id: '7', label: '7 días' },
        { id: '14', label: '14 días' },
        { id: '30', label: '30 días' },
    ];
    return (
        <div style={{ display: 'flex', gap: 2, background: V('--t-surface'), borderRadius: 8, padding: 2 }}>
            {options.map(o => (
                <button key={o.id} onClick={() => onChange(o.id)} style={{
                    padding: '5px 12px', borderRadius: 6, border: 'none', cursor: 'pointer',
                    fontSize: 11, fontWeight: 600, fontFamily: "'Outfit', sans-serif",
                    background: value === o.id ? V('--t-card') : 'transparent',
                    color: value === o.id ? V('--t-text') : V('--t-text-muted'),
                    boxShadow: value === o.id ? '0 1px 3px rgba(0,0,0,0.15)' : 'none',
                    transition: 'all 0.2s',
                }}>{o.label}</button>
            ))}
        </div>
    );
}

// ── Loading skeleton ────────────────────────────────────────────
function Skeleton({ height = 200 }) {
    return (
        <div style={{
            height, borderRadius: 10,
            background: `linear-gradient(90deg, ${V('--t-surface')} 25%, rgba(255,255,255,0.03) 50%, ${V('--t-surface')} 75%)`,
            backgroundSize: '200% 100%',
            animation: 'shimmer 1.5s infinite',
        }} />
    );
}

// ── Top Operators Table ─────────────────────────────────────────
function OperatorTable({ data }) {
    if (!data?.length) return <div style={{ padding: 24, textAlign: 'center', color: V('--t-text-muted'), fontSize: 12 }}>Sin datos de operadores</div>;
    return (
        <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                <thead>
                    <tr>
                        {['#', 'Operador', 'Atendidos', 'Prom. servicio', 'Rating'].map(h => (
                            <th key={h} style={{
                                padding: '8px 12px', textAlign: 'left', fontWeight: 700,
                                color: V('--t-text-muted'), fontSize: 9, textTransform: 'uppercase',
                                letterSpacing: '0.1em', borderBottom: `1px solid ${V('--t-border')}`,
                                fontFamily: "'JetBrains Mono', monospace",
                            }}>{h}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {data.map((op, i) => (
                        <tr key={i} style={{ borderBottom: `1px solid rgba(255,255,255,0.03)` }}>
                            <td style={{ padding: '10px 12px', fontFamily: "'JetBrains Mono', monospace", fontWeight: 700, color: V('--t-text-muted'), fontSize: 11 }}>{i + 1}</td>
                            <td style={{ padding: '10px 12px', fontWeight: 600, color: V('--t-text') }}>{op.name}</td>
                            <td style={{ padding: '10px 12px', fontFamily: "'JetBrains Mono', monospace", fontWeight: 700, color: COLORS.blue }}>{op.served}</td>
                            <td style={{ padding: '10px 12px', fontFamily: "'JetBrains Mono', monospace", color: V('--t-text-muted') }}>{fmtMin(op.avg_service)}</td>
                            <td style={{ padding: '10px 12px' }}>
                                {op.avg_rating ? (
                                    <span style={{ fontFamily: "'JetBrains Mono', monospace", fontWeight: 700, color: op.avg_rating >= 4 ? COLORS.green : op.avg_rating >= 3 ? COLORS.amber : COLORS.red }}>
                                        ★ {op.avg_rating}
                                    </span>
                                ) : <span style={{ color: V('--t-text-muted') }}>—</span>}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════
// MAIN COMPONENT
// ═══════════════════════════════════════════════════════════════

export default function Analytics({ branches = [], currentBranch, todayStats = {} }) {
    const [branchId, setBranchId] = useState(currentBranch?.id || branches[0]?.id);
    const [period, setPeriod] = useState('14');
    const [hourlyData, setHourlyData] = useState(null);
    const [trendData, setTrendData] = useState(null);
    const [serviceData, setServiceData] = useState(null);
    const [operatorData, setOperatorData] = useState(null);
    const [branchData, setBranchData] = useState(null);
    const [loading, setLoading] = useState(true);

    const s = todayStats;

    // Fetch data from existing API endpoints
    const fetchData = useCallback(async () => {
        if (!branchId) return;
        setLoading(true);

        try {
            const base = '/administracion/api/metrics';
            const [hourly, trend, services, operators, branchComp] = await Promise.all([
                fetch(`${base}/hourly/${branchId}`).then(r => r.json()),
                fetch(`${base}/trend/${branchId}?days=${period}`).then(r => r.json()),
                fetch(`${base}/services/${branchId}`).then(r => r.json()),
                fetch(`${base}/operators/${branchId}`).then(r => r.json()),
                fetch(`${base}/branches`).then(r => r.json()),
            ]);

            setHourlyData(hourly.data || []);
            setTrendData(trend.data || []);
            setServiceData(services.data || []);
            setOperatorData(operators.data || []);
            setBranchData(branchComp.data || []);
        } catch (err) {
            console.error('Analytics fetch error:', err);
        } finally {
            setLoading(false);
        }
    }, [branchId, period]);

    useEffect(() => { fetchData(); }, [fetchData]);

    // Auto-refresh every 60s
    useEffect(() => {
        const id = setInterval(fetchData, 60000);
        return () => clearInterval(id);
    }, [fetchData]);

    const selectedBranch = branches.find(b => b.id === branchId) || branches[0];

    return (
        <AuthenticatedLayout>
            <Head title="Analytics" />

            <div style={{ maxWidth: 1400, margin: '0 auto', padding: '24px', fontFamily: "'Outfit', sans-serif" }}>

                {/* ── Header ── */}
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 24, flexWrap: 'wrap', gap: 12 }}>
                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 800, color: V('--t-text'), margin: 0, letterSpacing: '-0.03em' }}>Analytics</h1>
                        <p style={{ fontSize: 13, color: V('--t-text-muted'), marginTop: 2 }}>
                            Métricas y rendimiento — {selectedBranch?.name || 'Todas las sucursales'}
                        </p>
                    </div>
                    <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
                        <PeriodSelector value={period} onChange={setPeriod} />
                        {branches.length > 1 && (
                            <select value={branchId} onChange={e => setBranchId(e.target.value)} style={{
                                background: V('--t-surface'), color: V('--t-text'), border: `1px solid ${V('--t-border')}`,
                                borderRadius: 8, padding: '7px 14px', fontSize: 12, fontFamily: "'Outfit', sans-serif",
                                cursor: 'pointer', outline: 'none',
                            }}>
                                {branches.map(b => <option key={b.id} value={b.id}>{b.name} ({b.code})</option>)}
                            </select>
                        )}
                    </div>
                </div>

                {/* ── KPI Row ── */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 12, marginBottom: 20 }}>
                    <KPI label="Emitidos hoy" value={s.total_issued || 0} color={COLORS.blue} />
                    <KPI label="Completados" value={s.completed || 0} suffix={` (${fmtPct(s.completed, s.total_issued)})`} color={COLORS.green} />
                    <KPI label="Espera promedio" value={fmtMin(s.avg_wait)} color={COLORS.amber} />
                    <KPI label="Servicio promedio" value={fmtMin(s.avg_service)} color={COLORS.purple} />
                    <KPI label="Espera máxima" value={fmtMin(s.max_wait)} color={COLORS.red} />
                    <KPI label="Rating promedio" value={s.avg_rating || '—'} suffix={s.avg_rating ? '/5' : ''} color={COLORS.cyan}
                        delta={s.avg_rating ? { value: 0, positive: s.avg_rating >= 4 } : null} />
                </div>

                {/* ── Charts Grid ── */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
                    {/* Hourly Volume */}
                    <ACard title="Volumen por hora" subtitle="Turnos emitidos vs completados hoy">
                        {loading ? <Skeleton height={240} /> : <HourlyChart data={hourlyData || []} />}
                    </ACard>

                    {/* Daily Trend */}
                    <ACard title="Tendencia diaria" subtitle={`Últimos ${period} días`}>
                        {loading ? <Skeleton height={240} /> : <TrendChart data={trendData || []} />}
                    </ACard>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
                    {/* Services */}
                    <ACard title="Desglose por servicio" subtitle="Total y completados hoy">
                        {loading ? <Skeleton height={240} /> : <ServiceChart data={serviceData || []} />}
                    </ACard>

                    {/* Operator Performance */}
                    <ACard title="Rendimiento de operadores" subtitle="Turnos atendidos hoy">
                        {loading ? <Skeleton height={240} /> : <OperatorChart data={operatorData || []} />}
                    </ACard>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 14 }}>
                    {/* Top Operators Table */}
                    <ACard title="Top operadores" subtitle="Ranking por volumen y calidad">
                        {loading ? <Skeleton height={200} /> : <OperatorTable data={operatorData || []} />}
                    </ACard>

                    {/* Branch Comparison */}
                    {branches.length > 1 && (
                        <ACard title="Comparativa entre sucursales" subtitle="Emitidos vs completados hoy">
                            {loading ? <Skeleton height={240} /> : <BranchComparisonChart data={branchData || []} />}
                        </ACard>
                    )}

                    {branches.length <= 1 && (
                        <ACard title="Distribución de estados" subtitle="Hoy">
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 8, marginTop: 4 }}>
                                {[
                                    { label: 'En espera', value: s.waiting || 0, color: COLORS.amber },
                                    { label: 'En atención', value: s.in_progress || 0, color: COLORS.purple },
                                    { label: 'Completados', value: s.completed || 0, color: COLORS.green },
                                    { label: 'Cancelados', value: s.cancelled || 0, color: COLORS.red },
                                    { label: 'No show', value: s.no_show || 0, color: V('--t-text-muted') },
                                    { label: 'Llamados', value: s.called || 0, color: COLORS.blue },
                                ].map(st => (
                                    <div key={st.label} style={{
                                        background: V('--t-surface'), borderRadius: 10, padding: '12px 14px', textAlign: 'center',
                                    }}>
                                        <div style={{ fontSize: 22, fontWeight: 900, color: st.color, fontFamily: "'JetBrains Mono', monospace" }}>{st.value}</div>
                                        <div style={{ fontSize: 9, color: V('--t-text-muted'), textTransform: 'uppercase', letterSpacing: '0.06em', marginTop: 2 }}>{st.label}</div>
                                    </div>
                                ))}
                            </div>
                        </ACard>
                    )}
                </div>
            </div>

            <style>{`
                @keyframes shimmer {
                    0% { background-position: 200% 0; }
                    100% { background-position: -200% 0; }
                }
            `}</style>
        </AuthenticatedLayout>
    );
}
