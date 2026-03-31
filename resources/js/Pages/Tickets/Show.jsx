// resources/js/Pages/Tickets/Show.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Card, StatusBadge, Btn, fmtSeconds, fmtMinutes, theme } from '@/Components/TurnosUI';

export default function TicketShow({ ticket: t }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">Turno {t.display_number}</h2>}>
            <Head title={`Turno ${t.display_number}`} />
            <div style={{ padding: '24px 32px', background: theme.bg, minHeight: '100vh', maxWidth: 800, margin: '0 auto' }}>
                <div style={{ marginBottom: 24 }}>
                    <Link href={route('dashboard')}><Btn variant="ghost" size="sm">← Volver</Btn></Link>
                </div>

                {/* Header */}
                <Card style={{ marginBottom: 20, borderTop: `3px solid ${t.service_color || theme.accent}` }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                        <div>
                            <div style={{ fontSize: 36, fontWeight: 800, color: theme.accent, fontVariantNumeric: 'tabular-nums' }}>{t.display_number}</div>
                            <div style={{ fontSize: 14, color: theme.textMuted }}>{t.branch_name}</div>
                        </div>
                        <div style={{ textAlign: 'right' }}>
                            <StatusBadge status={t.status} />
                            <div style={{ marginTop: 8 }}>
                                <span style={{ fontSize: 11, padding: '3px 8px', borderRadius: 4, background: theme.bg, color: theme.textMuted }}>{t.priority_label}</span>
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Details grid */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 20 }}>
                    {[
                        { l: 'Cliente', v: t.customer_name || '—' },
                        { l: 'Teléfono', v: t.customer_phone || '—' },
                        { l: 'Email', v: t.customer_email || '—' },
                        { l: 'Servicio', v: t.service_name },
                        { l: 'Cola', v: t.queue_name },
                        { l: 'Ventanilla', v: t.counter_number || '—' },
                        { l: 'Operador', v: t.operator_name || '—' },
                        { l: 'Creado por', v: t.created_by_name || 'Kiosco' },
                    ].map((item, i) => (
                        <Card key={i} style={{ padding: 14 }}>
                            <div style={{ fontSize: 10, color: theme.textMuted, textTransform: 'uppercase', marginBottom: 2 }}>{item.l}</div>
                            <div style={{ fontSize: 14, fontWeight: 600 }}>{item.v}</div>
                        </Card>
                    ))}
                </div>

                {/* Timestamps */}
                <Card style={{ marginBottom: 20 }}>
                    <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 12, color: theme.textSecondary }}>Tiempos</div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 16 }}>
                        <div style={{ textAlign: 'center', background: theme.bg, borderRadius: 8, padding: 12 }}>
                            <div style={{ fontSize: 22, fontWeight: 700, color: theme.warning }}>{t.wait_time_seconds ? fmtMinutes(t.wait_time_seconds) : '—'}</div>
                            <div style={{ fontSize: 10, color: theme.textMuted }}>Espera</div>
                        </div>
                        <div style={{ textAlign: 'center', background: theme.bg, borderRadius: 8, padding: 12 }}>
                            <div style={{ fontSize: 22, fontWeight: 700, color: theme.accent }}>{t.service_time_seconds ? fmtMinutes(t.service_time_seconds) : '—'}</div>
                            <div style={{ fontSize: 10, color: theme.textMuted }}>Servicio</div>
                        </div>
                        <div style={{ textAlign: 'center', background: theme.bg, borderRadius: 8, padding: 12 }}>
                            <div style={{ fontSize: 22, fontWeight: 700 }}>{t.total_time_seconds ? fmtMinutes(t.total_time_seconds) : '—'}</div>
                            <div style={{ fontSize: 10, color: theme.textMuted }}>Total</div>
                        </div>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, fontSize: 12 }}>
                        {[
                            { l: 'Emitido', v: t.issued_at },
                            { l: 'Llamado', v: t.called_at },
                            { l: 'Iniciado', v: t.started_at },
                            { l: 'Completado', v: t.completed_at },
                            { l: 'Cancelado', v: t.cancelled_at },
                        ].filter(i => i.v).map((item, i) => (
                            <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: 6, background: theme.bg, borderRadius: 4 }}>
                                <span style={{ color: theme.textMuted }}>{item.l}</span>
                                <span style={{ fontWeight: 600, fontVariantNumeric: 'tabular-nums' }}>{item.v}</span>
                            </div>
                        ))}
                    </div>
                </Card>

                {/* Rating */}
                {t.rating && (
                    <Card style={{ marginBottom: 20 }}>
                        <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 8, color: theme.textSecondary }}>Calificación</div>
                        <div style={{ fontSize: 28 }}>{'⭐'.repeat(t.rating)}{'☆'.repeat(5 - t.rating)}</div>
                        {t.feedback && <div style={{ marginTop: 8, fontSize: 13, color: theme.textSecondary }}>{t.feedback}</div>}
                    </Card>
                )}

                {/* Notes */}
                {t.notes && (
                    <Card style={{ marginBottom: 20 }}>
                        <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 8, color: theme.textSecondary }}>Notas</div>
                        <div style={{ fontSize: 13, color: theme.textSecondary }}>{t.notes}</div>
                    </Card>
                )}

                {/* Audit trail */}
                {t.events?.length > 0 && (
                    <Card>
                        <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 12, color: theme.textSecondary }}>Historial de Eventos</div>
                        {t.events.map((ev, i) => (
                            <div key={i} style={{ display: 'flex', gap: 12, alignItems: 'center', padding: '8px 0', borderBottom: i < t.events.length - 1 ? `1px solid ${theme.border}` : 'none' }}>
                                <div style={{ width: 8, height: 8, borderRadius: '50%', background: theme.accent, flexShrink: 0 }} />
                                <div style={{ flex: 1 }}>
                                    <span style={{ fontSize: 12, fontWeight: 600 }}>{ev.type.replace(/_/g, ' ')}</span>
                                    {ev.from && <span style={{ fontSize: 11, color: theme.textMuted }}> ({ev.from} → {ev.to})</span>}
                                </div>
                                <span style={{ fontSize: 11, color: theme.textMuted }}>{ev.user || 'Sistema'}</span>
                                <span style={{ fontSize: 11, color: theme.textMuted, fontVariantNumeric: 'tabular-nums' }}>{ev.at}</span>
                            </div>
                        ))}
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
