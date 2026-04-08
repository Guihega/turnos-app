/**
 * Olinora — Chart Components for Analytics
 * Uses Recharts with Olinora's Refined Industrial palette
 */
import {
    AreaChart, Area, BarChart, Bar, LineChart, Line,
    XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    PieChart, Pie, Cell, Legend,
} from 'recharts';

// ── Olinora palette (mirrors TurnosUI T tokens) ────────────────
const COLORS = {
    blue: '#3B82F6',
    purple: '#8B5CF6',
    green: '#10B981',
    amber: '#F59E0B',
    red: '#EF4444',
    cyan: '#06B6D4',
    pink: '#EC4899',
    indigo: '#6366F1',
};
const SERIES_COLORS = [COLORS.blue, COLORS.purple, COLORS.green, COLORS.amber, COLORS.cyan, COLORS.pink, COLORS.red, COLORS.indigo];

// ── Shared tooltip style ────────────────────────────────────────
const tooltipStyle = {
    backgroundColor: '#141720',
    border: '1px solid rgba(255,255,255,0.08)',
    borderRadius: 10,
    padding: '10px 14px',
    boxShadow: '0 8px 32px rgba(0,0,0,0.4)',
    fontSize: 12,
    fontFamily: "'Outfit', sans-serif",
    color: '#e2e5eb',
};

const labelStyle = {
    fontSize: 11,
    fontWeight: 700,
    color: '#8892a4',
    marginBottom: 4,
    fontFamily: "'JetBrains Mono', monospace",
};

// ── Custom Tooltip ──────────────────────────────────────────────
function CustomTooltip({ active, payload, label, formatter, suffix = '' }) {
    if (!active || !payload?.length) return null;
    return (
        <div style={tooltipStyle}>
            <div style={labelStyle}>{label}</div>
            {payload.map((entry, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 3 }}>
                    <span style={{ width: 8, height: 8, borderRadius: 3, background: entry.color, flexShrink: 0 }} />
                    <span style={{ color: '#8892a4', fontSize: 11 }}>{entry.name}:</span>
                    <span style={{ fontWeight: 700, fontFamily: "'JetBrains Mono', monospace", fontSize: 12, color: '#e2e5eb' }}>
                        {formatter ? formatter(entry.value) : entry.value}{suffix}
                    </span>
                </div>
            ))}
        </div>
    );
}

// ── Axis tick style ─────────────────────────────────────────────
const axisProps = {
    tick: { fontSize: 10, fill: '#5a6275', fontFamily: "'JetBrains Mono', monospace" },
    axisLine: { stroke: 'rgba(255,255,255,0.06)' },
    tickLine: false,
};

const gridProps = {
    strokeDasharray: '3 6',
    stroke: 'rgba(255,255,255,0.04)',
    vertical: false,
};

// ═══════════════════════════════════════════════════════════════
// EXPORTED CHART COMPONENTS
// ═══════════════════════════════════════════════════════════════

/**
 * Volume by Hour — Area chart
 */
export function HourlyChart({ data, height = 260 }) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <AreaChart data={data} margin={{ top: 8, right: 8, left: -10, bottom: 0 }}>
                <defs>
                    <linearGradient id="gradIssued" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={COLORS.blue} stopOpacity={0.3} />
                        <stop offset="100%" stopColor={COLORS.blue} stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="gradCompleted" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={COLORS.green} stopOpacity={0.2} />
                        <stop offset="100%" stopColor={COLORS.green} stopOpacity={0} />
                    </linearGradient>
                </defs>
                <CartesianGrid {...gridProps} />
                <XAxis dataKey="hour" {...axisProps} tickFormatter={h => `${h}:00`} />
                <YAxis {...axisProps} allowDecimals={false} />
                <Tooltip content={<CustomTooltip />} />
                <Area type="monotone" dataKey="issued" name="Emitidos" stroke={COLORS.blue} strokeWidth={2} fill="url(#gradIssued)" dot={false} activeDot={{ r: 4, stroke: COLORS.blue, fill: '#141720', strokeWidth: 2 }} />
                <Area type="monotone" dataKey="completed" name="Completados" stroke={COLORS.green} strokeWidth={2} fill="url(#gradCompleted)" dot={false} activeDot={{ r: 4, stroke: COLORS.green, fill: '#141720', strokeWidth: 2 }} />
            </AreaChart>
        </ResponsiveContainer>
    );
}

/**
 * Daily Trend — Line chart
 */
export function TrendChart({ data, height = 260 }) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <LineChart data={data} margin={{ top: 8, right: 8, left: -10, bottom: 0 }}>
                <CartesianGrid {...gridProps} />
                <XAxis dataKey="date" {...axisProps} tickFormatter={d => {
                    const dt = new Date(d);
                    return `${dt.getDate()}/${dt.getMonth() + 1}`;
                }} />
                <YAxis {...axisProps} allowDecimals={false} />
                <Tooltip content={<CustomTooltip />} />
                <Line type="monotone" dataKey="tickets" name="Turnos" stroke={COLORS.blue} strokeWidth={2.5} dot={{ r: 3, fill: COLORS.blue, stroke: '#141720', strokeWidth: 2 }} activeDot={{ r: 5, stroke: COLORS.blue, fill: '#141720', strokeWidth: 2 }} />
                <Line type="monotone" dataKey="avg_wait" name="Espera prom. (s)" stroke={COLORS.amber} strokeWidth={2} strokeDasharray="5 3" dot={false} />
            </LineChart>
        </ResponsiveContainer>
    );
}

/**
 * Service Breakdown — Horizontal bar chart
 */
export function ServiceChart({ data, height = 260 }) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={data} layout="vertical" margin={{ top: 4, right: 8, left: 0, bottom: 0 }}>
                <CartesianGrid {...gridProps} horizontal={false} />
                <XAxis type="number" {...axisProps} allowDecimals={false} />
                <YAxis type="category" dataKey="name" {...axisProps} width={90} tick={{ ...axisProps.tick, fontSize: 10 }} />
                <Tooltip content={<CustomTooltip />} />
                <Bar dataKey="total" name="Total" radius={[0, 6, 6, 0]} maxBarSize={22}>
                    {data.map((entry, i) => (
                        <Cell key={i} fill={entry.color || SERIES_COLORS[i % SERIES_COLORS.length]} fillOpacity={0.85} />
                    ))}
                </Bar>
                <Bar dataKey="completed" name="Completados" radius={[0, 4, 4, 0]} maxBarSize={22} fill={COLORS.green} fillOpacity={0.5} />
            </BarChart>
        </ResponsiveContainer>
    );
}

/**
 * Operator Performance — Bar chart
 */
export function OperatorChart({ data, height = 260 }) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={data} margin={{ top: 8, right: 8, left: -10, bottom: 0 }}>
                <CartesianGrid {...gridProps} />
                <XAxis dataKey="name" {...axisProps} tick={{ ...axisProps.tick, fontSize: 9 }} interval={0} angle={-20} textAnchor="end" height={50} />
                <YAxis {...axisProps} allowDecimals={false} />
                <Tooltip content={<CustomTooltip />} />
                <Bar dataKey="served" name="Atendidos" radius={[6, 6, 0, 0]} maxBarSize={36}>
                    {data.map((_, i) => (
                        <Cell key={i} fill={SERIES_COLORS[i % SERIES_COLORS.length]} fillOpacity={0.85} />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}

/**
 * Wait Time Heatmap — Custom grid
 */
export function WaitHeatmap({ data, height = 180 }) {
    // data: array of { hour, day_of_week, avg_wait }
    const days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    const hours = Array.from({ length: 12 }, (_, i) => i + 7); // 7:00 to 18:00

    // Build lookup
    const lookup = {};
    (data || []).forEach(d => {
        lookup[`${d.day_of_week}-${d.hour}`] = d.avg_wait;
    });

    const maxWait = Math.max(...Object.values(lookup), 1);

    const getColor = (val) => {
        if (!val) return 'rgba(255,255,255,0.02)';
        const ratio = Math.min(val / maxWait, 1);
        if (ratio < 0.25) return `rgba(16, 185, 129, ${0.15 + ratio * 2})`; // green
        if (ratio < 0.5) return `rgba(245, 158, 11, ${0.2 + ratio})`; // amber
        if (ratio < 0.75) return `rgba(245, 158, 11, ${0.4 + ratio * 0.5})`; // dark amber
        return `rgba(239, 68, 68, ${0.3 + ratio * 0.6})`; // red
    };

    return (
        <div style={{ overflowX: 'auto' }}>
            <div style={{ display: 'grid', gridTemplateColumns: `40px repeat(${hours.length}, 1fr)`, gap: 2, minWidth: 400 }}>
                {/* Header row */}
                <div />
                {hours.map(h => (
                    <div key={h} style={{ fontSize: 8, color: '#5a6275', textAlign: 'center', fontFamily: "'JetBrains Mono', monospace", padding: '0 0 4px' }}>
                        {h}:00
                    </div>
                ))}

                {/* Data rows */}
                {days.map((day, di) => (<>
                    <div key={`label-${di}`} style={{ fontSize: 9, color: '#5a6275', display: 'flex', alignItems: 'center', fontFamily: "'JetBrains Mono', monospace" }}>
                        {day}
                    </div>
                    {hours.map(h => {
                        const val = lookup[`${di + 1}-${h}`] || 0;
                        return (
                            <div key={`${di}-${h}`} title={val ? `${day} ${h}:00 — ${Math.round(val / 60)}min espera` : ''} style={{
                                height: 18, borderRadius: 3, background: getColor(val),
                                transition: 'background 0.3s', cursor: val ? 'pointer' : 'default',
                            }} />
                        );
                    })}
                </>))}
            </div>
            {/* Legend */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 8, justifyContent: 'flex-end' }}>
                <span style={{ fontSize: 8, color: '#5a6275' }}>Menos</span>
                {[0.1, 0.3, 0.5, 0.75, 1].map((r, i) => (
                    <div key={i} style={{ width: 14, height: 10, borderRadius: 2, background: getColor(r * maxWait) }} />
                ))}
                <span style={{ fontSize: 8, color: '#5a6275' }}>Más espera</span>
            </div>
        </div>
    );
}

/**
 * Branch Comparison — Grouped bar chart
 */
export function BranchComparisonChart({ data, height = 260 }) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={data} margin={{ top: 8, right: 8, left: -10, bottom: 0 }}>
                <CartesianGrid {...gridProps} />
                <XAxis dataKey="name" {...axisProps} />
                <YAxis {...axisProps} allowDecimals={false} />
                <Tooltip content={<CustomTooltip />} />
                <Bar dataKey="total" name="Emitidos" fill={COLORS.blue} radius={[4, 4, 0, 0]} maxBarSize={28} fillOpacity={0.8} />
                <Bar dataKey="completed" name="Completados" fill={COLORS.green} radius={[4, 4, 0, 0]} maxBarSize={28} fillOpacity={0.8} />
            </BarChart>
        </ResponsiveContainer>
    );
}

export { COLORS, SERIES_COLORS };
