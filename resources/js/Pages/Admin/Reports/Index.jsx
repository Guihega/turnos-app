// resources/js/Pages/Admin/Reports/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { PageHeader, Card, Stat, Select, DataTable, Btn, StatusBadge, FlashMessages, fmtMinutes, T } from '@/Components/TurnosUI';
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

    const ticketColumns = [
        { key: 'display_number', label: 'Turno', render: r => <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>{r.display_number}</span> },
        { key: 'customer_name', label: 'Cliente', render: r => r.customer_name || <span style={{ color: T.textMuted }}>—</span> },
        { key: 'service_name', label: 'Servicio' },
        { key: 'queue_name', label: 'Cola' },
        { key: 'status', label: 'Estado', render: r => <StatusBadge status={r.status} /> },
        { key: 'operator_name', label: 'Operador', render: r => r.operator_name || <span style={{ color: T.textMuted }}>—</span> },
        { key: 'wait_seconds', label: 'Espera', render: r => r.wait_seconds ? fmtMinutes(r.wait_seconds) : '—' },
        { key: 'service_seconds', label: 'Servicio', render: r => r.service_seconds ? fmtMinutes(r.service_seconds) : '—' },
        { key: 'rating', label: '⭐', align: 'center', render: r => r.rating ? <span style={{ color: T.warning }}>{r.rating}</span> : '—' },
        { key: 'created_at', label: 'Fecha' },
    ];

    const maxDaily = Math.max(...daily.map(d => d.total), 1);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">Reportes</h2>}>
            <Head title="Reportes" />
            <div style={{ padding: '24px 32px', background: T.bg, minHeight: '100vh' }}>
                <FlashMessages flash={flash} />
                <PageHeader title="Reportes y Análisis" subtitle="Métricas históricas de rendimiento" />

                {/* Filters */}
                <Card style={{ marginBottom: 20, display: 'flex', gap: 16, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                    <Select label="Sucursal" value={filters.branch_id || ''} onChange={e => setFilters({ ...filters, branch_id: e.target.value })}
                        options={branches.map(b => ({ value: b.id, label: b.name }))} />
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                        <label style={{ fontSize: 11, fontWeight: 500, color: T.textMuted, textTransform: 'uppercase' }}>Desde</label>
                        <input type="date" value={filters.date_from} onChange={e => setFilters({ ...filters, date_from: e.target.value })}
                            style={{ background: T.bg, color: T.text, border: `1px solid ${T.border}`, borderRadius: 8, padding: '10px 14px', fontSize: 13, fontFamily: T.font }} />
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                        <label style={{ fontSize: 11, fontWeight: 500, color: T.textMuted, textTransform: 'uppercase' }}>Hasta</label>
                        <input type="date" value={filters.date_to} onChange={e => setFilters({ ...filters, date_to: e.target.value })}
                            style={{ background: T.bg, color: T.text, border: `1px solid ${T.border}`, borderRadius: 8, padding: '10px 14px', fontSize: 13, fontFamily: T.font }} />
                    </div>
                    <Btn variant="primary" onClick={applyFilters}>Aplicar</Btn>
                    <Btn variant="ghost" onClick={exportCSV}>⬡ Exportar CSV</Btn>
                </Card>

                {/* KPI Summary */}
                {summary.total > 0 && (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: 12, marginBottom: 20 }}>
                        <Stat label="Total turnos" value={summary.total} icon="🎫" />
                        <Stat label="Completados" value={summary.completed} color={T.success} icon="✓" />
                        <Stat label="Cancelados" value={summary.cancelled} color={T.danger} />
                        <Stat label="No presentados" value={summary.no_show} color={T.warning} />
                        <Stat label="Espera prom." value={fmtMinutes(summary.avg_wait)} icon="⏱" />
                        <Stat label="Servicio prom." value={fmtMinutes(summary.avg_service)} />
                        <Stat label="Rating" value={summary.avg_rating || '—'} suffix="/5" color={T.warning} icon="⭐" />
                    </div>
                )}

                {/* Daily chart */}
                {daily.length > 0 && (
                    <Card style={{ marginBottom: 20 }}>
                        <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 14, color: T.textSecondary }}>Tendencia Diaria</div>
                        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 4, height: 140, padding: '0 4px' }}>
                            {daily.map((d, i) => (
                                <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3 }}>
                                    <span style={{ fontSize: 9, fontWeight: 600, color: T.textMuted }}>{d.total}</span>
                                    <div style={{ width: '100%', maxWidth: 32, borderRadius: 3, transition: 'height 0.6s ease', background: T.accent, height: (d.total / maxDaily) * 110 }} />
                                    <span style={{ fontSize: 8, color: T.textMuted, whiteSpace: 'nowrap' }}>{new Date(d.date).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })}</span>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}

                {/* Operators ranking */}
                {operators.length > 0 && (
                    <Card style={{ marginBottom: 20 }}>
                        <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 14, color: T.textSecondary }}>Top Operadores</div>
                        {operators.map((op, i) => (
                            <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '10px 0', borderBottom: i < operators.length - 1 ? `1px solid ${T.border}` : 'none' }}>
                                <span style={{ fontSize: 16, fontWeight: 700, color: T.textMuted, width: 24 }}>{i + 1}</span>
                                <div style={{ width: 32, height: 32, borderRadius: '50%', background: `hsl(${i * 55}, 55%, 45%)`, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontWeight: 700, fontSize: 13 }}>{op.name.charAt(0)}</div>
                                <div style={{ flex: 1 }}><div style={{ fontWeight: 600, fontSize: 13 }}>{op.name}</div></div>
                                <div style={{ display: 'flex', gap: 20 }}>
                                    <div style={{ textAlign: 'center' }}><div style={{ fontWeight: 700, color: T.accent }}>{op.served}</div><div style={{ fontSize: 9, color: T.textMuted }}>atendidos</div></div>
                                    <div style={{ textAlign: 'center' }}><div style={{ fontWeight: 700 }}>{fmtMinutes(op.avg_time)}</div><div style={{ fontSize: 9, color: T.textMuted }}>prom.</div></div>
                                    {op.rating && <div style={{ textAlign: 'center' }}><div style={{ fontWeight: 700, color: T.warning }}>⭐ {op.rating}</div><div style={{ fontSize: 9, color: T.textMuted }}>rating</div></div>}
                                </div>
                            </div>
                        ))}
                    </Card>
                )}

                {/* Ticket list */}
                {tickets && (
                    <>
                        <div style={{ fontSize: 13, fontWeight: 600, color: T.textSecondary, marginBottom: 12 }}>Detalle de Turnos</div>
                        <DataTable columns={ticketColumns} rows={tickets.data} />
                        {/* Pagination */}
                        {tickets.last_page > 1 && (
                            <div style={{ display: 'flex', gap: 6, justifyContent: 'center', marginTop: 16 }}>
                                {tickets.links.filter(l => l.url).map((link, i) => (
                                    <button key={i} onClick={() => router.get(link.url, {}, { preserveState: true })}
                                        style={{ padding: '6px 12px', borderRadius: 6, fontSize: 12, border: `1px solid ${T.border}`, cursor: 'pointer', fontFamily: 'inherit',
                                            background: link.active ? T.accent : T.cardBg, color: link.active ? '#fff' : T.textSecondary,
                                        }} dangerouslySetInnerHTML={{ __html: link.label }} />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
