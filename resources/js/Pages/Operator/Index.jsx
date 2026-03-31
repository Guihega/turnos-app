// resources/js/Pages/Operator/Index.jsx
import { useForm, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Card, Btn, StatusBadge, Stat, Select, FlashMessages, fmtSeconds, fmtMinutes, theme, statusMap, useAutoRefresh } from '@/Components/TurnosUI';

export default function OperatorIndex({ branches, currentBranch, counter, availableCounters, currentTicket, waitingTickets, queues, allQueues, myStats }) {
    const { flash } = usePage().props;
    const [selectedCounter, setSelectedCounter] = useState(counter?.id || availableCounters?.[0]?.id || '');
    const [selectedQueue, setSelectedQueue] = useState('');
    const [transferQueue, setTransferQueue] = useState('');
    const [transferReason, setTransferReason] = useState('');
    const [showTransfer, setShowTransfer] = useState(false);
    const [rating, setRating] = useState(0);
    const [notes, setNotes] = useState('');
    const [elapsed, setElapsed] = useState(0);

    useAutoRefresh(5000);

    // Timer for current ticket
    useEffect(() => {
        if (!currentTicket) { setElapsed(0); return; }
        const start = currentTicket.started_at ? new Date(currentTicket.started_at) : (currentTicket.called_at ? new Date(currentTicket.called_at) : null);
        if (!start) return;
        const id = setInterval(() => setElapsed(Math.floor((Date.now() - start.getTime()) / 1000)), 1000);
        return () => clearInterval(id);
    }, [currentTicket?.id, currentTicket?.started_at]);

    const callNext = () => {
        router.post(route('operator.call'), {
            counter_id: selectedCounter,
            queue_id: selectedQueue || null,
        });
    };

    const startServing = () => router.post(route('operator.start', currentTicket.id));
    const completeTicket = () => {
        router.post(route('operator.complete', currentTicket.id), { rating: rating || null, notes: notes || null });
        setRating(0); setNotes('');
    };
    const cancelTicket = () => { if (confirm('¿Cancelar este turno?')) router.post(route('operator.cancel', currentTicket.id)); };
    const noShowTicket = () => { if (confirm('¿Marcar como no presentado?')) router.post(route('operator.noshow', currentTicket.id)); };
    const recallTicket = () => router.post(route('operator.recall', currentTicket.id));
    const doTransfer = () => {
        router.post(route('operator.transfer', currentTicket.id), { target_queue_id: transferQueue, reason: transferReason });
        setShowTransfer(false); setTransferReason('');
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">Atención de Turnos</h2>}>
            <Head title="Operador" />
            <div style={{ padding: '24px 32px', background: theme.bg, minHeight: '100vh', fontFamily: "'DM Sans', sans-serif" }}>
                <FlashMessages flash={flash} />

                {/* Top bar */}
                <div style={{ display: 'flex', gap: 12, marginBottom: 20, flexWrap: 'wrap', alignItems: 'center' }}>
                    <Select value={selectedCounter} onChange={e => setSelectedCounter(e.target.value)} label="Ventanilla"
                        options={availableCounters.map(c => ({ value: c.id, label: `${c.name} (${c.number})${c.is_mine ? ' ★' : ''}` }))} />
                    <Select value={selectedQueue} onChange={e => setSelectedQueue(e.target.value)} label="Filtrar cola"
                        options={[{ value: '', label: 'Todas las colas' }, ...queues.map(q => ({ value: q.id, label: `${q.prefix} — ${q.name} (${q.waiting})` }))]} />
                    <div style={{ marginLeft: 'auto', display: 'flex', gap: 12 }}>
                        <Stat label="Atendidos" value={myStats.served} color={theme.success} />
                        <Stat label="Prom. servicio" value={fmtMinutes(myStats.avg_service)} />
                        {myStats.avg_rating > 0 && <Stat label="Rating" value={Number(myStats.avg_rating).toFixed(1)} suffix="/5" color={theme.warning} />}
                    </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 380px', gap: 20 }}>
                    {/* ── Left: Current ticket + Actions ── */}
                    <div>
                        {/* Current ticket card */}
                        {currentTicket ? (
                            <Card style={{ marginBottom: 20, borderTop: `3px solid ${currentTicket.service_color || theme.accent}` }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 16 }}>
                                    <div>
                                        <div style={{ fontSize: 11, color: theme.textMuted, textTransform: 'uppercase', marginBottom: 4 }}>Turno actual</div>
                                        <div style={{ fontSize: 36, fontWeight: 800, color: theme.accent, letterSpacing: '-0.02em', fontVariantNumeric: 'tabular-nums' }}>
                                            {currentTicket.display_number}
                                        </div>
                                    </div>
                                    <div style={{ textAlign: 'right' }}>
                                        <StatusBadge status={currentTicket.status} />
                                        <div style={{ fontSize: 24, fontWeight: 700, marginTop: 8, color: elapsed > 600 ? theme.warning : theme.textPrimary, fontVariantNumeric: 'tabular-nums' }}>
                                            {fmtSeconds(elapsed)}
                                        </div>
                                    </div>
                                </div>

                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 16 }}>
                                    <div><span style={{ fontSize: 10, color: theme.textMuted }}>CLIENTE</span><div style={{ fontSize: 14, fontWeight: 600 }}>{currentTicket.customer_name || 'Sin nombre'}</div></div>
                                    <div><span style={{ fontSize: 10, color: theme.textMuted }}>SERVICIO</span><div style={{ fontSize: 14, fontWeight: 600 }}>{currentTicket.service_name}</div></div>
                                    <div><span style={{ fontSize: 10, color: theme.textMuted }}>COLA</span><div style={{ fontSize: 14 }}>{currentTicket.queue_name}</div></div>
                                    <div><span style={{ fontSize: 10, color: theme.textMuted }}>PRIORIDAD</span><div style={{ fontSize: 14 }}>{currentTicket.priority_label}</div></div>
                                </div>

                                {/* Action buttons */}
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                    {currentTicket.status === 'called' && (
                                        <>
                                            <Btn variant="success" size="lg" onClick={startServing}>▶ Iniciar Atención</Btn>
                                            <Btn variant="ghost" onClick={recallTicket}>🔊 Re-llamar</Btn>
                                            <Btn variant="warning" onClick={noShowTicket}>No se presentó</Btn>
                                        </>
                                    )}
                                    {currentTicket.status === 'in_progress' && (
                                        <>
                                            <Btn variant="success" size="lg" onClick={completeTicket}>✓ Completar</Btn>
                                            <Btn variant="ghost" onClick={() => setShowTransfer(true)}>↗ Transferir</Btn>
                                            <Btn variant="danger" onClick={cancelTicket}>✕ Cancelar</Btn>
                                        </>
                                    )}
                                </div>

                                {/* Rating & notes (for completion) */}
                                {currentTicket.status === 'in_progress' && (
                                    <div style={{ marginTop: 16, paddingTop: 16, borderTop: `1px solid ${theme.border}` }}>
                                        <div style={{ display: 'flex', gap: 16, alignItems: 'center', marginBottom: 8 }}>
                                            <span style={{ fontSize: 11, color: theme.textMuted }}>CALIFICACIÓN:</span>
                                            {[1, 2, 3, 4, 5].map(n => (
                                                <span key={n} onClick={() => setRating(n)}
                                                    style={{ cursor: 'pointer', fontSize: 22, filter: n <= rating ? 'none' : 'grayscale(1) opacity(0.3)', transition: 'all 0.15s' }}>
                                                    ⭐
                                                </span>
                                            ))}
                                        </div>
                                        <textarea value={notes} onChange={e => setNotes(e.target.value)} placeholder="Notas (opcional)..."
                                            style={{ width: '100%', background: theme.bg, color: theme.textPrimary, border: `1px solid ${theme.border}`, borderRadius: 8, padding: 10, fontSize: 12, resize: 'vertical', minHeight: 60, fontFamily: 'inherit' }} />
                                    </div>
                                )}

                                {/* Transfer modal */}
                                {showTransfer && (
                                    <div style={{ marginTop: 16, padding: 16, background: theme.bg, borderRadius: 8, border: `1px solid ${theme.border}` }}>
                                        <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 12 }}>Transferir a otra cola</div>
                                        <Select value={transferQueue} onChange={e => setTransferQueue(e.target.value)} label="Cola destino"
                                            options={[{ value: '', label: 'Seleccionar...' }, ...allQueues.map(q => ({ value: q.id, label: `${q.prefix} — ${q.name}` }))]} />
                                        <textarea value={transferReason} onChange={e => setTransferReason(e.target.value)} placeholder="Razón de transferencia..."
                                            style={{ width: '100%', background: theme.cardBg, color: theme.textPrimary, border: `1px solid ${theme.border}`, borderRadius: 8, padding: 10, fontSize: 12, marginTop: 8, resize: 'none', height: 60, fontFamily: 'inherit' }} />
                                        <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                                            <Btn variant="primary" onClick={doTransfer} disabled={!transferQueue}>Transferir</Btn>
                                            <Btn variant="ghost" onClick={() => setShowTransfer(false)}>Cancelar</Btn>
                                        </div>
                                    </div>
                                )}
                            </Card>
                        ) : (
                            <Card style={{ textAlign: 'center', padding: '40px 20px', marginBottom: 20 }}>
                                <div style={{ fontSize: 48, marginBottom: 12 }}>📋</div>
                                <div style={{ fontSize: 16, fontWeight: 600, marginBottom: 8 }}>Sin turno activo</div>
                                <div style={{ fontSize: 13, color: theme.textMuted, marginBottom: 20 }}>Llama al siguiente turno para comenzar</div>
                                <Btn variant="primary" size="lg" onClick={callNext} disabled={!selectedCounter}>
                                    📢 Llamar Siguiente Turno
                                </Btn>
                            </Card>
                        )}

                        {/* Call next button when has active but completed */}
                        {!currentTicket && (
                            <div style={{ display: 'flex', gap: 12, marginBottom: 20 }}>
                                {queues.map(q => (
                                    <Card key={q.id} style={{ flex: 1, textAlign: 'center', cursor: 'pointer', transition: 'border-color 0.2s' }}
                                        onClick={() => { setSelectedQueue(q.id); callNext(); }}>
                                        <div style={{ fontSize: 24, fontWeight: 700, color: q.waiting > 0 ? theme.warning : theme.textMuted }}>{q.waiting}</div>
                                        <div style={{ fontSize: 10, color: theme.textMuted }}>{q.prefix} — {q.name}</div>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* ── Right: Waiting queue ── */}
                    <Card style={{ maxHeight: 'calc(100vh - 160px)', overflowY: 'auto', padding: 0 }}>
                        <div style={{ padding: '14px 18px', borderBottom: `1px solid ${theme.border}`, position: 'sticky', top: 0, background: theme.cardBg, zIndex: 1, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <span style={{ fontSize: 13, fontWeight: 600 }}>Cola de Espera</span>
                            <span style={{ fontSize: 20, fontWeight: 700, color: theme.warning }}>{waitingTickets.length}</span>
                        </div>
                        {waitingTickets.length === 0 ? (
                            <div style={{ padding: 40, textAlign: 'center', color: theme.textMuted, fontSize: 13 }}>No hay turnos en espera</div>
                        ) : (
                            waitingTickets.map((t, i) => (
                                <div key={t.id} style={{
                                    padding: '12px 18px', display: 'flex', alignItems: 'center', gap: 12,
                                    borderBottom: i < waitingTickets.length - 1 ? `1px solid ${theme.border}` : 'none',
                                    background: t.priority === 'vip' || t.priority === 'urgent' ? 'rgba(239,68,68,0.04)' : 'transparent',
                                }}>
                                    <div style={{
                                        width: 6, height: 6, borderRadius: '50%', flexShrink: 0,
                                        background: t.service_color || theme.accent,
                                    }} />
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontSize: 14, fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>{t.display_number}</div>
                                        <div style={{ fontSize: 10, color: theme.textMuted, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {t.customer_name || 'Sin nombre'} · {t.service_name}
                                        </div>
                                    </div>
                                    <div style={{ textAlign: 'right', flexShrink: 0 }}>
                                        <div style={{ fontSize: 12, fontWeight: 600, color: t.wait_minutes > 10 ? theme.danger : t.wait_minutes > 5 ? theme.warning : theme.textSecondary, fontVariantNumeric: 'tabular-nums' }}>
                                            {t.wait_minutes} min
                                        </div>
                                        <div style={{ fontSize: 9, color: theme.textMuted }}>{t.queue_prefix} · {t.priority_label}</div>
                                    </div>
                                </div>
                            ))
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
