import { useState, useEffect, useCallback, useRef } from "react";

// ── Mock Data Generator ──
const generateMockData = () => {
  const hours = Array.from({ length: 13 }, (_, i) => i + 7);
  const now = new Date();
  const currentHour = now.getHours();

  const hourlyData = hours.map(h => ({
    hour: h,
    issued: h <= currentHour ? Math.floor(Math.random() * 25) + 5 : 0,
    completed: h <= currentHour ? Math.floor(Math.random() * 20) + 3 : 0,
    avgWait: h <= currentHour ? Math.floor(Math.random() * 400) + 60 : 0,
  }));

  const services = [
    { name: "Consulta General", color: "#3B82F6", total: 67, completed: 52, avgWait: 285, avgService: 420, avgRating: 4.3 },
    { name: "Laboratorio", color: "#10B981", total: 43, completed: 38, avgWait: 180, avgService: 240, avgRating: 4.5 },
    { name: "Farmacia", color: "#F59E0B", total: 55, completed: 51, avgWait: 90, avgService: 120, avgRating: 4.1 },
    { name: "Urgencias", color: "#EF4444", total: 22, completed: 18, avgWait: 45, avgService: 600, avgRating: 4.7 },
    { name: "Caja / Pagos", color: "#8B5CF6", total: 38, completed: 35, avgWait: 150, avgService: 180, avgRating: 3.9 },
    { name: "Especialidades", color: "#EC4899", total: 30, completed: 24, avgWait: 360, avgService: 540, avgRating: 4.6 },
  ];

  const operators = [
    { name: "Ana García", served: 28, avgTime: 380, rating: 4.6, utilization: 87 },
    { name: "Roberto Silva", served: 24, avgTime: 420, rating: 4.4, utilization: 82 },
    { name: "María López", served: 22, avgTime: 350, rating: 4.8, utilization: 79 },
    { name: "José Hernández", served: 19, avgTime: 450, rating: 4.2, utilization: 74 },
    { name: "Laura Martínez", served: 17, avgTime: 310, rating: 4.5, utilization: 71 },
    { name: "Pedro Sánchez", served: 15, avgTime: 400, rating: 4.3, utilization: 65 },
  ];

  const queues = [
    { name: "Cola General", prefix: "A", waiting: 8, inProgress: 3, completed: 89, avgWait: 240 },
    { name: "Cola Prioritaria", prefix: "B", waiting: 3, inProgress: 2, completed: 45, avgWait: 120 },
    { name: "Cola Express", prefix: "C", waiting: 5, inProgress: 2, completed: 62, avgWait: 90 },
  ];

  const activeTickets = [
    { number: "CTR-A021", customer: "María Fernanda L.", service: "Consulta General", status: "in_progress", counter: "3", operator: "Ana García", wait: 180, elapsed: 420 },
    { number: "CTR-B008", customer: "Carlos Eduardo M.", service: "Urgencias", status: "in_progress", counter: "1", operator: "Roberto Silva", wait: 45, elapsed: 600 },
    { number: "CTR-A022", customer: "Juan Pablo R.", service: "Laboratorio", status: "called", counter: "5", operator: "María López", wait: 210, elapsed: 0 },
    { number: "CTR-C015", customer: "Sofía Valentina G.", service: "Farmacia", status: "waiting", counter: "-", operator: "-", wait: 95, elapsed: 0 },
    { number: "CTR-A023", customer: "Diego Alejandro T.", service: "Consulta General", status: "waiting", counter: "-", operator: "-", wait: 340, elapsed: 0 },
    { number: "CTR-C016", customer: "Valentina Isabel H.", service: "Caja / Pagos", status: "waiting", counter: "-", operator: "-", wait: 78, elapsed: 0 },
    { number: "CTR-B009", customer: "Andrés Felipe S.", service: "Especialidades", status: "waiting", counter: "-", operator: "-", wait: 420, elapsed: 0 },
    { number: "CTR-A024", customer: "Lucía Gabriela P.", service: "Laboratorio", status: "waiting", counter: "-", operator: "-", wait: 55, elapsed: 0 },
  ];

  const branches = [
    { name: "Sede Centro", code: "CTR", tickets: 135, completed: 112, avgWait: 215, rating: 4.4 },
    { name: "Sede Angelópolis", code: "ANG", tickets: 98, completed: 82, avgWait: 180, rating: 4.5 },
    { name: "Sede Cholula", code: "CHO", tickets: 72, completed: 59, avgWait: 260, rating: 4.2 },
  ];

  const trendDays = Array.from({ length: 14 }, (_, i) => {
    const d = new Date();
    d.setDate(d.getDate() - (13 - i));
    return {
      date: d.toLocaleDateString("es-MX", { day: "2-digit", month: "short" }),
      tickets: Math.floor(Math.random() * 60) + 80,
      avgWait: Math.floor(Math.random() * 200) + 120,
    };
  });

  return {
    kpi: {
      totalIssued: 255, completed: 196, waiting: 16, inProgress: 7, called: 4,
      cancelled: 18, noShow: 14, avgWait: 215, avgService: 340, avgRating: 4.38,
      slaCompliance: 82.4, peakHour: 11,
      changes: { totalIssued: 12.3, completed: 8.7, avgWait: -5.2, avgRating: 2.1 },
    },
    hourlyData, services, operators, queues, activeTickets, branches, trendDays,
  };
};

// ── Utility ──
const fmt = (sec) => {
  if (!sec) return "0:00";
  const m = Math.floor(sec / 60);
  const s = sec % 60;
  return `${m}:${String(s).padStart(2, "0")}`;
};

const fmtMin = (sec) => `${Math.round(sec / 60)} min`;

const statusStyles = {
  waiting: { bg: "rgba(234,179,8,0.12)", text: "#CA8A04", label: "En espera" },
  called: { bg: "rgba(59,130,246,0.12)", text: "#2563EB", label: "Llamado" },
  in_progress: { bg: "rgba(99,102,241,0.12)", text: "#4F46E5", label: "En atención" },
  completed: { bg: "rgba(16,185,129,0.12)", text: "#059669", label: "Completado" },
  cancelled: { bg: "rgba(239,68,68,0.12)", text: "#DC2626", label: "Cancelado" },
};

// ── Sparkline mini chart ──
const Sparkline = ({ data, color, height = 32, width = 100 }) => {
  if (!data || data.length < 2) return null;
  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;
  const points = data.map((v, i) => {
    const x = (i / (data.length - 1)) * width;
    const y = height - ((v - min) / range) * (height - 4) - 2;
    return `${x},${y}`;
  }).join(" ");

  return (
    <svg width={width} height={height} style={{ display: "block" }}>
      <polyline fill="none" stroke={color} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" points={points} />
    </svg>
  );
};

// ── Bar chart component ──
const BarChart = ({ data, valueKey, labelKey, colorKey, height = 180, maxBars = 8 }) => {
  const sliced = data.slice(0, maxBars);
  const maxVal = Math.max(...sliced.map(d => d[valueKey])) || 1;

  return (
    <div style={{ display: "flex", alignItems: "flex-end", gap: 6, height, padding: "0 4px" }}>
      {sliced.map((d, i) => {
        const barH = (d[valueKey] / maxVal) * (height - 28);
        return (
          <div key={i} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 4 }}>
            <span style={{ fontSize: 10, fontWeight: 600, color: "var(--text-primary)" }}>{d[valueKey]}</span>
            <div style={{
              width: "100%", maxWidth: 40, height: barH, borderRadius: 4,
              background: d[colorKey] || "var(--accent)", transition: "height 0.6s ease",
            }} />
            <span style={{ fontSize: 9, color: "var(--text-muted)", textAlign: "center", lineHeight: 1.1, maxWidth: 56, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
              {d[labelKey]}
            </span>
          </div>
        );
      })}
    </div>
  );
};

// ── Donut chart ──
const DonutChart = ({ segments, size = 120 }) => {
  const total = segments.reduce((s, seg) => s + seg.value, 0) || 1;
  const cx = size / 2, cy = size / 2, r = size / 2 - 8;
  const circumference = 2 * Math.PI * r;
  let offset = 0;

  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
      {segments.map((seg, i) => {
        const pct = seg.value / total;
        const dash = pct * circumference;
        const el = (
          <circle key={i} cx={cx} cy={cy} r={r} fill="none" stroke={seg.color}
            strokeWidth={14} strokeDasharray={`${dash} ${circumference - dash}`}
            strokeDashoffset={-offset} transform={`rotate(-90 ${cx} ${cy})`}
            style={{ transition: "stroke-dasharray 0.8s ease" }} />
        );
        offset += dash;
        return el;
      })}
      <text x={cx} y={cy - 6} textAnchor="middle" style={{ fontSize: 18, fontWeight: 700, fill: "var(--text-primary)" }}>{total}</text>
      <text x={cx} y={cy + 12} textAnchor="middle" style={{ fontSize: 10, fill: "var(--text-muted)" }}>turnos hoy</text>
    </svg>
  );
};

// ── KPI Card ──
const KpiCard = ({ label, value, change, suffix, icon, sparkData, sparkColor }) => {
  const isPositive = change > 0;
  const changeColor = label.includes("Espera") ? (isPositive ? "#DC2626" : "#059669") : (isPositive ? "#059669" : "#DC2626");

  return (
    <div style={{
      background: "var(--card-bg)", borderRadius: 12, padding: "16px 18px",
      border: "1px solid var(--border)", display: "flex", flexDirection: "column", gap: 8,
      position: "relative", overflow: "hidden",
    }}>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
        <span style={{ fontSize: 11, fontWeight: 500, color: "var(--text-muted)", textTransform: "uppercase", letterSpacing: "0.05em" }}>{label}</span>
        <span style={{ fontSize: 14 }}>{icon}</span>
      </div>
      <div style={{ display: "flex", alignItems: "baseline", gap: 6 }}>
        <span style={{ fontSize: 28, fontWeight: 700, color: "var(--text-primary)", letterSpacing: "-0.02em" }}>{value}</span>
        {suffix && <span style={{ fontSize: 12, color: "var(--text-muted)" }}>{suffix}</span>}
      </div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        {change !== undefined && (
          <span style={{ fontSize: 11, fontWeight: 600, color: changeColor, display: "flex", alignItems: "center", gap: 2 }}>
            {isPositive ? "↑" : "↓"} {Math.abs(change)}% vs ayer
          </span>
        )}
        {sparkData && <Sparkline data={sparkData} color={sparkColor || "var(--accent)"} width={72} height={24} />}
      </div>
    </div>
  );
};

// ── Main Dashboard ──
export default function Dashboard() {
  const [data, setData] = useState(null);
  const [tab, setTab] = useState("overview");
  const [branch, setBranch] = useState("CTR");
  const [period, setPeriod] = useState("today");
  const [clock, setClock] = useState(new Date());
  const intervalRef = useRef(null);

  useEffect(() => {
    setData(generateMockData());
    intervalRef.current = setInterval(() => {
      setClock(new Date());
      if (Math.random() > 0.7) setData(generateMockData());
    }, 5000);
    return () => clearInterval(intervalRef.current);
  }, []);

  if (!data) return <div style={{ padding: 40, textAlign: "center", color: "#888" }}>Cargando dashboard…</div>;

  const { kpi, hourlyData, services, operators, queues, activeTickets, branches, trendDays } = data;

  const tabs = [
    { id: "overview", label: "Resumen", icon: "◉" },
    { id: "live", label: "En Vivo", icon: "⚡" },
    { id: "services", label: "Servicios", icon: "▦" },
    { id: "operators", label: "Operadores", icon: "👤" },
    { id: "branches", label: "Sucursales", icon: "🏢" },
  ];

  return (
    <div style={{
      "--bg": "#0B0E14", "--card-bg": "#12161F", "--border": "#1E2432",
      "--text-primary": "#E8ECF4", "--text-secondary": "#9BA3B5", "--text-muted": "#5C6478",
      "--accent": "#3B82F6", "--accent-soft": "rgba(59,130,246,0.12)",
      "--success": "#10B981", "--warning": "#F59E0B", "--danger": "#EF4444",
      fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
      background: "var(--bg)", color: "var(--text-primary)", minHeight: "100vh",
      padding: 0, margin: 0,
    }}>
      {/* Header */}
      <div style={{
        padding: "14px 24px", display: "flex", justifyContent: "space-between", alignItems: "center",
        borderBottom: "1px solid var(--border)", background: "var(--card-bg)",
      }}>
        <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
          <div style={{
            width: 32, height: 32, borderRadius: 8, background: "linear-gradient(135deg, #3B82F6, #8B5CF6)",
            display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 800, fontSize: 14, color: "#fff",
          }}>T</div>
          <div>
            <div style={{ fontSize: 15, fontWeight: 700 }}>TurnosPro</div>
            <div style={{ fontSize: 10, color: "var(--text-muted)" }}>Clínica San Rafael</div>
          </div>
        </div>

        <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
          <select value={branch} onChange={e => setBranch(e.target.value)} style={{
            background: "var(--bg)", color: "var(--text-secondary)", border: "1px solid var(--border)",
            borderRadius: 6, padding: "6px 10px", fontSize: 12, outline: "none", cursor: "pointer",
          }}>
            <option value="CTR">Sede Centro</option>
            <option value="ANG">Sede Angelópolis</option>
            <option value="CHO">Sede Cholula</option>
          </select>

          <select value={period} onChange={e => setPeriod(e.target.value)} style={{
            background: "var(--bg)", color: "var(--text-secondary)", border: "1px solid var(--border)",
            borderRadius: 6, padding: "6px 10px", fontSize: 12, outline: "none", cursor: "pointer",
          }}>
            <option value="today">Hoy</option>
            <option value="week">Esta semana</option>
            <option value="month">Este mes</option>
          </select>

          <div style={{ fontSize: 12, color: "var(--text-muted)", fontVariantNumeric: "tabular-nums", minWidth: 56, textAlign: "right" }}>
            {clock.toLocaleTimeString("es-MX", { hour: "2-digit", minute: "2-digit", second: "2-digit" })}
          </div>

          <div style={{
            width: 8, height: 8, borderRadius: "50%", background: "#10B981",
            boxShadow: "0 0 8px rgba(16,185,129,0.5)",
            animation: "pulse 2s ease-in-out infinite",
          }} />
        </div>
      </div>

      {/* Tabs */}
      <div style={{
        display: "flex", gap: 2, padding: "8px 24px", borderBottom: "1px solid var(--border)",
        background: "var(--card-bg)", overflowX: "auto",
      }}>
        {tabs.map(t => (
          <button key={t.id} onClick={() => setTab(t.id)} style={{
            padding: "8px 16px", borderRadius: 6, fontSize: 12, fontWeight: 500,
            border: "none", cursor: "pointer", transition: "all 0.2s",
            background: tab === t.id ? "var(--accent-soft)" : "transparent",
            color: tab === t.id ? "var(--accent)" : "var(--text-muted)",
            display: "flex", alignItems: "center", gap: 6, whiteSpace: "nowrap",
          }}>
            <span style={{ fontSize: 13 }}>{t.icon}</span> {t.label}
          </button>
        ))}
      </div>

      {/* Content */}
      <div style={{ padding: "20px 24px", maxWidth: 1400, margin: "0 auto" }}>

        {/* ══ OVERVIEW TAB ══ */}
        {tab === "overview" && (
          <>
            {/* KPI Grid */}
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: 14, marginBottom: 20 }}>
              <KpiCard label="Turnos Emitidos" value={kpi.totalIssued} change={kpi.changes.totalIssued} icon="🎫"
                sparkData={trendDays.map(d => d.tickets)} sparkColor="var(--accent)" />
              <KpiCard label="Completados" value={kpi.completed} change={kpi.changes.completed} icon="✓"
                sparkData={trendDays.map(d => d.tickets * 0.8)} sparkColor="var(--success)" />
              <KpiCard label="Tiempo Promedio Espera" value={fmtMin(kpi.avgWait)} change={kpi.changes.avgWait} icon="⏱"
                sparkData={trendDays.map(d => d.avgWait)} sparkColor="var(--warning)" />
              <KpiCard label="Rating Promedio" value={kpi.avgRating.toFixed(1)} suffix="/5" change={kpi.changes.avgRating} icon="⭐"
                sparkData={Array.from({ length: 14 }, () => 3.8 + Math.random() * 1.2)} sparkColor="#F59E0B" />
              <KpiCard label="SLA Cumplimiento" value={`${kpi.slaCompliance}%`} icon="📊"
                sparkData={Array.from({ length: 14 }, () => 70 + Math.random() * 25)} sparkColor="var(--success)" />
            </div>

            {/* Charts row */}
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 280px", gap: 14, marginBottom: 20 }}>
              {/* Hourly distribution */}
              <div style={{ background: "var(--card-bg)", borderRadius: 12, padding: 18, border: "1px solid var(--border)" }}>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--text-secondary)", marginBottom: 14 }}>Distribución por Hora</div>
                <BarChart data={hourlyData.map(h => ({ ...h, label: `${h.hour}:00`, color: h.hour === kpi.peakHour ? "var(--warning)" : "var(--accent)" }))}
                  valueKey="issued" labelKey="label" colorKey="color" height={160} maxBars={13} />
              </div>

              {/* Service breakdown */}
              <div style={{ background: "var(--card-bg)", borderRadius: 12, padding: 18, border: "1px solid var(--border)" }}>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--text-secondary)", marginBottom: 14 }}>Turnos por Servicio</div>
                <BarChart data={services} valueKey="total" labelKey="name" colorKey="color" height={160} />
              </div>

              {/* Status donut */}
              <div style={{ background: "var(--card-bg)", borderRadius: 12, padding: 18, border: "1px solid var(--border)", display: "flex", flexDirection: "column", alignItems: "center" }}>
                <div style={{ fontSize: 12, fontWeight: 600, color: "var(--text-secondary)", marginBottom: 14, alignSelf: "flex-start" }}>Estado Actual</div>
                <DonutChart size={120} segments={[
                  { value: kpi.completed, color: "#10B981" },
                  { value: kpi.waiting, color: "#EAB308" },
                  { value: kpi.inProgress, color: "#6366F1" },
                  { value: kpi.called, color: "#3B82F6" },
                  { value: kpi.cancelled, color: "#EF4444" },
                  { value: kpi.noShow, color: "#F97316" },
                ]} />
                <div style={{ display: "flex", flexWrap: "wrap", gap: "6px 12px", marginTop: 12, justifyContent: "center" }}>
                  {[
                    { label: "Completado", color: "#10B981", val: kpi.completed },
                    { label: "En espera", color: "#EAB308", val: kpi.waiting },
                    { label: "En atención", color: "#6366F1", val: kpi.inProgress },
                    { label: "Cancelado", color: "#EF4444", val: kpi.cancelled },
                  ].map(l => (
                    <span key={l.label} style={{ fontSize: 9, color: "var(--text-muted)", display: "flex", alignItems: "center", gap: 4 }}>
                      <span style={{ width: 6, height: 6, borderRadius: "50%", background: l.color }} /> {l.label} ({l.val})
                    </span>
                  ))}
                </div>
              </div>
            </div>

            {/* Queues status */}
            <div style={{ background: "var(--card-bg)", borderRadius: 12, padding: 18, border: "1px solid var(--border)", marginBottom: 20 }}>
              <div style={{ fontSize: 12, fontWeight: 600, color: "var(--text-secondary)", marginBottom: 14 }}>Estado de Colas</div>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))", gap: 12 }}>
                {queues.map(q => (
                  <div key={q.prefix} style={{
                    background: "var(--bg)", borderRadius: 8, padding: 14,
                    display: "flex", flexDirection: "column", gap: 8,
                  }}>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                      <span style={{ fontWeight: 600, fontSize: 13 }}>{q.name}</span>
                      <span style={{ fontSize: 10, color: "var(--text-muted)", background: "var(--border)", borderRadius: 4, padding: "2px 6px" }}>{q.prefix}</span>
                    </div>
                    <div style={{ display: "flex", gap: 12 }}>
                      <div style={{ textAlign: "center" }}>
                        <div style={{ fontSize: 20, fontWeight: 700, color: "var(--warning)" }}>{q.waiting}</div>
                        <div style={{ fontSize: 9, color: "var(--text-muted)" }}>esperando</div>
                      </div>
                      <div style={{ textAlign: "center" }}>
                        <div style={{ fontSize: 20, fontWeight: 700, color: "#6366F1" }}>{q.inProgress}</div>
                        <div style={{ fontSize: 9, color: "var(--text-muted)" }}>atendiendo</div>
                      </div>
                      <div style={{ textAlign: "center" }}>
                        <div style={{ fontSize: 20, fontWeight: 700, color: "var(--success)" }}>{q.completed}</div>
                        <div style={{ fontSize: 9, color: "var(--text-muted)" }}>completados</div>
                      </div>
                      <div style={{ textAlign: "center" }}>
                        <div style={{ fontSize: 20, fontWeight: 700, color: "var(--text-secondary)" }}>{fmtMin(q.avgWait)}</div>
                        <div style={{ fontSize: 9, color: "var(--text-muted)" }}>espera prom</div>
                      </div>
                    </div>
                    {/* Progress bar */}
                    <div style={{ height: 4, borderRadius: 2, background: "var(--border)", overflow: "hidden" }}>
                      <div style={{
                        height: "100%", borderRadius: 2, transition: "width 0.8s ease",
                        width: `${Math.min((q.waiting / 30) * 100, 100)}%`,
                        background: q.waiting > 20 ? "var(--danger)" : q.waiting > 10 ? "var(--warning)" : "var(--success)",
                      }} />
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Trend */}
            <div style={{ background: "var(--card-bg)", borderRadius: 12, padding: 18, border: "1px solid var(--border)" }}>
              <div style={{ fontSize: 12, fontWeight: 600, color: "var(--text-secondary)", marginBottom: 14 }}>Tendencia Últimos 14 Días</div>
              <div style={{ display: "flex", alignItems: "flex-end", gap: 4, height: 120 }}>
                {trendDays.map((d, i) => {
                  const maxT = Math.max(...trendDays.map(t => t.tickets));
                  const h = (d.tickets / maxT) * 100;
                  return (
                    <div key={i} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 3 }}>
                      <span style={{ fontSize: 9, fontWeight: 600, color: "var(--text-muted)" }}>{d.tickets}</span>
                      <div style={{ width: "100%", height: h, borderRadius: 3, background: i === trendDays.length - 1 ? "var(--accent)" : "rgba(59,130,246,0.3)", transition: "height 0.6s ease" }} />
                      <span style={{ fontSize: 8, color: "var(--text-muted)", transform: "rotate(-45deg)", transformOrigin: "center", whiteSpace: "nowrap" }}>{d.date}</span>
                    </div>
                  );
                })}
              </div>
            </div>
          </>
        )}

        {/* ══ LIVE TAB ══ */}
        {tab === "live" && (
          <div>
            <div style={{ display: "flex", gap: 10, marginBottom: 16 }}>
              {[
                { label: "En Espera", val: kpi.waiting, color: "var(--warning)" },
                { label: "Llamados", val: kpi.called, color: "var(--accent)" },
                { label: "En Atención", val: kpi.inProgress, color: "#6366F1" },
              ].map(s => (
                <div key={s.label} style={{
                  flex: 1, background: "var(--card-bg)", borderRadius: 10, padding: "12px 16px",
                  border: "1px solid var(--border)", textAlign: "center",
                }}>
                  <div style={{ fontSize: 28, fontWeight: 700, color: s.color }}>{s.val}</div>
                  <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{s.label}</div>
                </div>
              ))}
            </div>

            <div style={{ background: "var(--card-bg)", borderRadius: 12, border: "1px solid var(--border)", overflow: "hidden" }}>
              <div style={{ padding: "12px 18px", borderBottom: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Turnos Activos</span>
                <span style={{ fontSize: 11, color: "var(--text-muted)" }}>Actualización automática cada 5s</span>
              </div>
              <div style={{ overflowX: "auto" }}>
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
                  <thead>
                    <tr style={{ borderBottom: "1px solid var(--border)" }}>
                      {["Turno", "Cliente", "Servicio", "Estado", "Ventanilla", "Operador", "Espera", "En atención"].map(h => (
                        <th key={h} style={{ padding: "10px 14px", textAlign: "left", fontWeight: 500, color: "var(--text-muted)", fontSize: 10, textTransform: "uppercase", letterSpacing: "0.05em" }}>{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {activeTickets.map(t => {
                      const s = statusStyles[t.status];
                      return (
                        <tr key={t.number} style={{ borderBottom: "1px solid var(--border)" }}>
                          <td style={{ padding: "10px 14px", fontWeight: 600, fontVariantNumeric: "tabular-nums" }}>{t.number}</td>
                          <td style={{ padding: "10px 14px", color: "var(--text-secondary)" }}>{t.customer}</td>
                          <td style={{ padding: "10px 14px", color: "var(--text-secondary)" }}>{t.service}</td>
                          <td style={{ padding: "10px 14px" }}>
                            <span style={{ background: s.bg, color: s.text, padding: "3px 8px", borderRadius: 4, fontSize: 10, fontWeight: 600 }}>{s.label}</span>
                          </td>
                          <td style={{ padding: "10px 14px", fontVariantNumeric: "tabular-nums", color: t.counter === "-" ? "var(--text-muted)" : "var(--text-primary)" }}>{t.counter}</td>
                          <td style={{ padding: "10px 14px", color: t.operator === "-" ? "var(--text-muted)" : "var(--text-secondary)" }}>{t.operator}</td>
                          <td style={{ padding: "10px 14px", fontVariantNumeric: "tabular-nums", color: t.wait > 300 ? "var(--danger)" : "var(--text-secondary)" }}>{fmt(t.wait)}</td>
                          <td style={{ padding: "10px 14px", fontVariantNumeric: "tabular-nums", color: "var(--text-secondary)" }}>{t.elapsed > 0 ? fmt(t.elapsed) : "—"}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}

        {/* ══ SERVICES TAB ══ */}
        {tab === "services" && (
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))", gap: 14 }}>
            {services.map(s => (
              <div key={s.name} style={{ background: "var(--card-bg)", borderRadius: 12, padding: 18, border: "1px solid var(--border)", borderTop: `3px solid ${s.color}` }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 14 }}>
                  <span style={{ fontSize: 14, fontWeight: 600 }}>{s.name}</span>
                  <span style={{ fontSize: 22, fontWeight: 700, color: s.color }}>{s.total}</span>
                </div>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
                  <div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)", textTransform: "uppercase" }}>Completados</div>
                    <div style={{ fontSize: 16, fontWeight: 600, color: "var(--success)" }}>{s.completed}</div>
                  </div>
                  <div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)", textTransform: "uppercase" }}>Espera Prom</div>
                    <div style={{ fontSize: 16, fontWeight: 600 }}>{fmtMin(s.avgWait)}</div>
                  </div>
                  <div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)", textTransform: "uppercase" }}>Servicio Prom</div>
                    <div style={{ fontSize: 16, fontWeight: 600 }}>{fmtMin(s.avgService)}</div>
                  </div>
                  <div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)", textTransform: "uppercase" }}>Rating</div>
                    <div style={{ fontSize: 16, fontWeight: 600, color: s.avgRating >= 4.5 ? "var(--success)" : s.avgRating >= 4 ? "var(--warning)" : "var(--danger)" }}>
                      ⭐ {s.avgRating}
                    </div>
                  </div>
                </div>
                {/* completion rate bar */}
                <div style={{ marginTop: 12 }}>
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: 9, color: "var(--text-muted)", marginBottom: 3 }}>
                    <span>Tasa de completado</span>
                    <span>{Math.round((s.completed / s.total) * 100)}%</span>
                  </div>
                  <div style={{ height: 4, borderRadius: 2, background: "var(--border)" }}>
                    <div style={{ height: "100%", borderRadius: 2, background: s.color, width: `${(s.completed / s.total) * 100}%`, transition: "width 0.8s ease" }} />
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* ══ OPERATORS TAB ══ */}
        {tab === "operators" && (
          <div style={{ background: "var(--card-bg)", borderRadius: 12, border: "1px solid var(--border)", overflow: "hidden" }}>
            <div style={{ padding: "14px 18px", borderBottom: "1px solid var(--border)" }}>
              <span style={{ fontSize: 13, fontWeight: 600 }}>Rendimiento de Operadores</span>
            </div>
            {operators.map((op, i) => (
              <div key={op.name} style={{
                padding: "14px 18px", display: "flex", alignItems: "center", gap: 14,
                borderBottom: i < operators.length - 1 ? "1px solid var(--border)" : "none",
              }}>
                <div style={{
                  width: 36, height: 36, borderRadius: "50%",
                  background: `hsl(${i * 50}, 60%, 45%)`, display: "flex", alignItems: "center", justifyContent: "center",
                  color: "#fff", fontWeight: 700, fontSize: 14, flexShrink: 0,
                }}>{op.name.charAt(0)}</div>

                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 13, fontWeight: 600 }}>{op.name}</div>
                  <div style={{ fontSize: 10, color: "var(--text-muted)" }}>Utilización: {op.utilization}%</div>
                </div>

                <div style={{ display: "flex", gap: 20, alignItems: "center" }}>
                  <div style={{ textAlign: "center" }}>
                    <div style={{ fontSize: 16, fontWeight: 700, color: "var(--accent)" }}>{op.served}</div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)" }}>atendidos</div>
                  </div>
                  <div style={{ textAlign: "center" }}>
                    <div style={{ fontSize: 16, fontWeight: 700 }}>{fmtMin(op.avgTime)}</div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)" }}>prom. servicio</div>
                  </div>
                  <div style={{ textAlign: "center" }}>
                    <div style={{ fontSize: 16, fontWeight: 700, color: op.rating >= 4.5 ? "var(--success)" : "var(--warning)" }}>⭐ {op.rating}</div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)" }}>rating</div>
                  </div>
                  <div style={{ width: 80 }}>
                    <div style={{ height: 6, borderRadius: 3, background: "var(--border)" }}>
                      <div style={{
                        height: "100%", borderRadius: 3, transition: "width 0.8s ease",
                        width: `${op.utilization}%`,
                        background: op.utilization >= 80 ? "var(--success)" : op.utilization >= 60 ? "var(--warning)" : "var(--danger)",
                      }} />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* ══ BRANCHES TAB ══ */}
        {tab === "branches" && (
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))", gap: 14 }}>
            {branches.map(b => (
              <div key={b.code} style={{
                background: "var(--card-bg)", borderRadius: 12, padding: 18,
                border: "1px solid var(--border)", position: "relative", overflow: "hidden",
              }}>
                <div style={{
                  position: "absolute", top: 0, left: 0, right: 0, height: 3,
                  background: "linear-gradient(90deg, var(--accent), #8B5CF6)",
                }} />
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 16 }}>
                  <div>
                    <div style={{ fontSize: 14, fontWeight: 700 }}>{b.name}</div>
                    <span style={{ fontSize: 10, color: "var(--text-muted)", background: "var(--border)", borderRadius: 3, padding: "1px 5px" }}>{b.code}</span>
                  </div>
                  <div style={{ textAlign: "right" }}>
                    <div style={{ fontSize: 24, fontWeight: 700, color: "var(--accent)" }}>{b.tickets}</div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)" }}>turnos hoy</div>
                  </div>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 10 }}>
                  <div style={{ textAlign: "center", background: "var(--bg)", borderRadius: 8, padding: 10 }}>
                    <div style={{ fontSize: 18, fontWeight: 700, color: "var(--success)" }}>{b.completed}</div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)" }}>completados</div>
                  </div>
                  <div style={{ textAlign: "center", background: "var(--bg)", borderRadius: 8, padding: 10 }}>
                    <div style={{ fontSize: 18, fontWeight: 700 }}>{fmtMin(b.avgWait)}</div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)" }}>espera prom</div>
                  </div>
                  <div style={{ textAlign: "center", background: "var(--bg)", borderRadius: 8, padding: 10 }}>
                    <div style={{ fontSize: 18, fontWeight: 700, color: b.rating >= 4.4 ? "var(--success)" : "var(--warning)" }}>⭐ {b.rating}</div>
                    <div style={{ fontSize: 9, color: "var(--text-muted)" }}>rating</div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <style>{`
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        ::-webkit-scrollbar { height: 4px; width: 4px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
      `}</style>
    </div>
  );
}
