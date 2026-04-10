// resources/js/Pages/Admin/Reports/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { PageHeader, Card, Stat, Select, DataTable, Btn, StatusBadge, Badge, Avatar, FlashMessages, fmtMinutes, T } from '@/Components/TurnosUI';
import { useState } from 'react';

export default function ReportsIndex({ branches, currentBranchId, dateFrom, dateTo, summary, daily, operators, tickets }) {
    const { flash } = usePage().props;
    const [filters, setFilters] = useState({ branch_id: currentBranchId, date_from: dateFrom, date_to: dateTo });

    const applyFilters = () => router.get(route('admin.reports.index'), filters, { preserveState: true });

    const exportCSV = () => {
        const params = new URLSearchParams({
            branch_id: filters.branch_id || '',
            date_from: filters.date_from || '',
            date_to: filters.date_to || '',
        });
        window.open(`${route('admin.reports.export')}?${params.toString()}`, '_blank');
    };

    const inputStyle = {
        background: T.surface, color: T.text, border: `1px solid ${T.border}`,
        borderRadius: 8, padding: '9px 14px', fontSize: 12, fontFamily: T.font, outline: 'none',
    };

    const ticketColumns = [
        { key: 'display_number', label: 'Turno', render: r => (
            <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums', fontFamily: T.mono }}>{r.display_number}</span>
        )},
        { key: 'customer_name', label: 'Cliente', render: r => r.customer_name || <span style={{ color: T.textMuted }}>—</span> },
        { key: 'service_name', label: 'Servicio' },
        { key: 'queue_name', label: 'Cola' },
        { key: 'status', label: 'Estado', render: r => <StatusBadge status={r.status} size="sm" /> },
        { key: 'operator_name', label: 'Operador', render: r => r.operator_name || <span style={{ color: T.textMuted }}>—</span> },
        { key: 'wait_seconds', label: 'Espera', render: r => (
            <span style={{ fontFamily: T.mono, fontSize: 11 }}>{r.wait_seconds ? fmtMinutes(r.wait_seconds) : '—'}</span>
        )},
        { key: 'service_seconds', label: 'Servicio', render: r => (
            <span style={{ fontFamily: T.mono, fontSize: 11 }}>{r.service_seconds ? fmtMinutes(r.service_seconds) : '—'}</span>
        )},
        { key: 'rating', label: '★', align: 'center', render: r => r.rating
            ? <span style={{ color: T.amber, fontWeight: 700, fontFamily: T.mono }}>{r.rating}</span>
            : <span style={{ color: T.textMuted }}>—</span>
        },
        { key: 'created_at', label: 'Fecha', render: r => (
            <span style={{ fontSize: 11, color: T.textMuted, fontFamily: T.mono }}>{r.created_at}</span>
        )},
    ];

    const maxDaily = Math.max(...daily.map(d => d.total), 1);

    const operatorColors = ['#3D7AFF', '#00D68F', '#9D5CFF', '#FFB020', '#00D4FF', '#EC4899'];

    return (
        <AuthenticatedLayout>
            <Head title="Reportes" />
            <div className="t-page-shell" style={{ padding: T.pagePadding, background: T.bg, minHeight: '100vh', fontFamily: T.font }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>
                <FlashMessages flash={flash} />
                <PageHeader title="Reportes y Análisis" subtitle="Métricas históricas de rendimiento" />

                {/* Filters */}
                <Card style={{ marginBottom: 20, display: 'flex', gap: 14, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                    <Select label="Sucursal" value={filters.branch_id || ''} onChange={e => setFilters({ ...filters, branch_id: e.target.value })}
                        options={branches.map(b => ({ value: b.id, label: b.name }))} />
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
                        <label style={{ fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', fontFamily: T.font }}>Desde</label>
                        <input type="date" value={filters.date_from} onChange={e => setFilters({ ...filters, date_from: e.target.value })} style={inputStyle} />
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
                        <label style={{ fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', fontFamily: T.font }}>Hasta</label>
                        <input type="date" value={filters.date_to} onChange={e => setFilters({ ...filters, date_to: e.target.value })} style={inputStyle} />
                    </div>
                    <Btn variant="primary" onClick={applyFilters}>Aplicar</Btn>
                    <Btn variant="ghost" onClick={exportCSV}>⬡ Exportar CSV</Btn>
                </Card>

                {/* KPI Summary */}
                {summary.total > 0 && (
                    <div className="t-reports-kpi" style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 8, marginBottom: 20 }}>
                        <Stat label="Total" value={summary.total} />
                        <Stat label="Completados" value={summary.completed} color={T.green} />
                        <Stat label="Cancelados" value={summary.cancelled} color={T.red} />
                        <Stat label="No show" value={summary.no_show} color={T.amber} />
                        <Stat label="Espera" value={`${Math.round((summary.avg_wait || 0) / 60)}`} suffix="m" />
                        <Stat label="Servicio" value={`${Math.round((summary.avg_service || 0) / 60)}`} suffix="m" />
                        <Stat label="Rating" value={summary.avg_rating || '—'} suffix="/5" color={T.amber} />
                    </div>
                )}

                {/* Daily chart + Operators (side by side) */}
                {(daily.length > 0 || operators.length > 0) && (
                    <div className="t-grid-responsive" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginBottom: 20 }}>
                        {/* Daily chart */}
                        {daily.length > 0 && (
                            <Card style={{ padding: 20 }}>
                                <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 14, color: T.textSoft }}>Tendencia diaria</div>
                                <div style={{ display: 'flex', alignItems: 'flex-end', gap: 3, height: 140, padding: '0 4px' }}>
                                    {daily.map((d, i) => (
                                        <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3 }}>
                                            <span style={{ fontSize: 9, fontWeight: 600, color: T.textMuted, fontFamily: T.mono }}>{d.total}</span>
                                            <div style={{
                                                width: '100%', maxWidth: 28, borderRadius: 4,
                                                transition: 'height 0.6s ease',
                                                background: `linear-gradient(180deg, ${T.blue}, color-mix(in srgb, ${T.blue} 40%, transparent))`,
                                                height: Math.max((d.total / maxDaily) * 110, 2),
                                            }} />
                                            <span style={{ fontSize: 8, color: T.textMuted, whiteSpace: 'nowrap', fontFamily: T.mono }}>
                                                {new Date(d.date).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        )}

                        {/* Operators ranking */}
                        {operators.length > 0 && (
                            <Card style={{ padding: 20 }}>
                                <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 14, color: T.textSoft }}>Top operadores</div>
                                {operators.slice(0, 5).map((op, i) => (
                                    <div key={i} style={{
                                        display: 'flex', alignItems: 'center', gap: 10, padding: '8px 0',
                                        borderBottom: i < Math.min(operators.length, 5) - 1 ? `1px solid color-mix(in srgb, ${T.border} 50%, transparent)` : 'none',
                                    }}>
                                        <span style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, width: 18, fontFamily: T.mono, textAlign: 'center' }}>{i + 1}</span>
                                        <Avatar name={op.name} size={26} color={operatorColors[i % operatorColors.length]} />
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div style={{ fontWeight: 600, fontSize: 12, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{op.name}</div>
                                        </div>
                                        <div style={{ display: 'flex', gap: 14, flexShrink: 0 }}>
                                            <div style={{ textAlign: 'center' }}>
                                                <div style={{ fontWeight: 700, color: T.blue, fontFamily: T.mono, fontSize: 13 }}>{op.served}</div>
                                                <div style={{ fontSize: 8, color: T.textMuted }}>atendidos</div>
                                            </div>
                                            <div style={{ textAlign: 'center' }}>
                                                <div style={{ fontWeight: 700, fontFamily: T.mono, fontSize: 13 }}>{fmtMinutes(op.avg_time)}</div>
                                                <div style={{ fontSize: 8, color: T.textMuted }}>prom.</div>
                                            </div>
                                            {op.rating && (
                                                <div style={{ textAlign: 'center' }}>
                                                    <div style={{ fontWeight: 700, color: T.amber, fontFamily: T.mono, fontSize: 13 }}>★ {op.rating}</div>
                                                    <div style={{ fontSize: 8, color: T.textMuted }}>rating</div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </Card>
                        )}
                    </div>
                )}

                {/* Ticket list */}
                {tickets && (
                    <>
                        <div style={{ fontSize: 13, fontWeight: 700, color: T.textSoft, marginBottom: 12 }}>Detalle de turnos</div>
                        <DataTable columns={ticketColumns} rows={tickets.data} />
                        {/* Pagination */}
                        {tickets.last_page > 1 && (
                            <div style={{ display: 'flex', gap: 4, justifyContent: 'center', marginTop: 16 }}>
                                {tickets.links.filter(l => l.url).map((link, i) => (
                                    <button key={i} onClick={() => router.get(link.url, {}, { preserveState: true })}
                                        style={{
                                            padding: '6px 12px', borderRadius: 6, fontSize: 11, fontFamily: T.font, fontWeight: 600,
                                            border: `1px solid ${link.active ? T.blue : T.border}`, cursor: 'pointer',
                                            background: link.active ? `color-mix(in srgb, ${T.blue} 15%, transparent)` : T.card,
                                            color: link.active ? T.blue : T.textSoft,
                                        }} dangerouslySetInnerHTML={{ __html: link.label }} />
                                ))}
                            </div>
                        )}
                    </>
                )}
                <style>{`
                    @media (max-width: 900px) {
                        .t-reports-kpi { grid-template-columns: repeat(4, 1fr) !important; }
                        .t-grid-responsive { grid-template-columns: 1fr !important; }
                    }
                    @media (max-width: 768px) {
                        .t-reports-kpi { grid-template-columns: repeat(2, 1fr) !important; }
                    }
                `}</style>
                </div>{/* end maxWidth */}
            </div>
        </AuthenticatedLayout>
    );
}
