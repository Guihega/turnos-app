// resources/js/Pages/Operator/Index.jsx
import { usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import { Card, GlassCard, Btn, StatusBadge, Stat, Select, FlashMessages, LiveDot, MetricBar, useAutoRefresh, fmtSeconds, fmtMinutes, T } from '@/Components/TurnosUI';

export default function OperatorIndex({ branches, currentBranch, counter, availableCounters, currentTicket, waitingTickets, queues, allQueues, myStats, error }) {
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

    useEffect(() => { if (counter?.id) setSelectedCounter(counter.id); else if (availableCounters?.length > 0 && !selectedCounter) setSelectedCounter(availableCounters[0].id); }, [counter?.id]);
    useEffect(() => { if (!currentTicket) setSelectedQueue(''); }, [currentTicket?.id]);

    useEffect(() => {
        if (!currentTicket) { setElapsed(0); return; }
        const start = currentTicket.started_at ? new Date(currentTicket.started_at) : (currentTicket.called_at ? new Date(currentTicket.called_at) : null);
        if (!start) return;
        const id = setInterval(() => setElapsed(Math.floor((Date.now() - start.getTime()) / 1000)), 1000);
        return () => clearInterval(id);
    }, [currentTicket?.id, currentTicket?.started_at]);

    // Call next - accepts optional queueId override for direct queue card clicks
    const callNext = useCallback((queueIdOverride) => {
        const qId = queueIdOverride || selectedQueue || null;
        router.post(route('operator.call'), { counter_id: selectedCounter, queue_id: qId });
    }, [selectedCounter, selectedQueue]);

    const startServing = () => router.post(route('operator.start', currentTicket.id));
    const completeTicket = () => { router.post(route('operator.complete', currentTicket.id), { rating: rating || null, notes: notes || null }); setRating(0); setNotes(''); };
    const cancelTicket = () => { if (confirm('¿Cancelar este turno?')) router.post(route('operator.cancel', currentTicket.id)); };
    const noShowTicket = () => { if (confirm('¿Marcar como no presentado?')) router.post(route('operator.noshow', currentTicket.id)); };
    const recallTicket = () => router.post(route('operator.recall', currentTicket.id));
    const doTransfer = () => { router.post(route('operator.transfer', currentTicket.id), { target_queue_id: transferQueue, reason: transferReason }); setShowTransfer(false); setTransferReason(''); };
    const totalWaiting = queues?.reduce((sum, q) => sum + (q.waiting || 0), 0) || 0;

    // Filter waiting tickets by selected queue
    const filteredTickets = selectedQueue
        ? (waitingTickets || []).filter(t => {
            const q = queues?.find(q => q.id === selectedQueue);
            return q && t.queue_prefix === q.prefix;
          })
        : (waitingTickets || []);

    const filteredWaiting = selectedQueue
        ? (queues?.find(q => q.id === selectedQueue)?.waiting || 0)
        : totalWaiting;

    // Timer ring
    const timerMax = 900;
    const timerPct = Math.min(elapsed / timerMax, 1);
    const timerColor = elapsed > 600 ? T.red : elapsed > 300 ? T.amber : T.green;

    return (
        <AuthenticatedLayout>
            <Head title="Operador" />
            <div style={{ background: T.bg, minHeight: '100vh', padding: '20px 24px', fontFamily: T.font, color: T.text }}>
                <FlashMessages flash={flash} />
                {error && <div className="t-fade-up" style={{ background: T.redGlow, border: `1px solid ${T.red}25`, borderRadius: T.radiusSm, padding: '12px 16px', marginBottom: 16, fontSize: 13, color: T.red }}>{error}</div>}

                {/* ── Top controls ── */}
                <div className="t-fade-up" style={{ display: 'flex', gap: 10, marginBottom: 20, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    {branches?.length > 1 && (
                        <Select label="Sucursal" value={currentBranch?.id || ''} onChange={e => router.get(route('operator.index'), { branch_id: e.target.value }, { preserveState: true })}
                            options={branches.map(b => ({ value: b.id, label: `${b.name} (${b.code})` }))} />
                    )}
                    {branches?.length === 1 && <div style={{ padding: '8px 0', fontSize: 13, color: T.textMuted, display: 'flex', alignItems: 'center', gap: 6 }}>{currentBranch?.name} <LiveDot size={6} /></div>}

                    <Select label="Ventanilla" value={selectedCounter} onChange={e => setSelectedCounter(e.target.value)}
                        options={availableCounters?.length > 0 ? availableCounters.map(c => ({ value: c.id, label: `${c.name} (${c.number})${c.is_mine ? ' ★' : ''}` })) : [{ value: '', label: 'Sin ventanillas' }]} />

                    <Select label={currentTicket ? "Cola (próximo)" : "Filtrar cola"} value={selectedQueue} onChange={e => setSelectedQueue(e.target.value)} disabled={!!currentTicket}
                        options={[{ value: '', label: 'Todas' }, ...(queues || []).map(q => ({ value: q.id, label: `${q.prefix} — ${q.name} (${q.waiting})` }))]} />

                    <div style={{ marginLeft: 'auto', display: 'flex', gap: 10 }}>
                        <Stat label="Atendidos" value={myStats?.served || 0} color={T.green} />
                        <Stat label="Prom." value={fmtMinutes(myStats?.avg_service || 0)} />
                    </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 360px', gap: 16 }} className="t-grid-responsive">
                    {/* ── Left: Ticket workspace ── */}
                    <div>
                        {currentTicket ? (
                            <Card accent={currentTicket.service_color || T.blue} glow={`${currentTicket.service_color || T.blue}08`} className="t-fade-up" style={{ marginBottom: 16 }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 20 }}>
                                    <div>
                                        <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 6 }}>Turno actual</div>
                                        <div style={{ fontSize: 40, fontWeight: 900, color: T.blue, fontFamily: T.mono, letterSpacing: '-0.03em', lineHeight: 1 }}>{currentTicket.display_number}</div>
                                        <div style={{ marginTop: 8 }}><StatusBadge status={currentTicket.status} size="lg" /></div>
                                    </div>
                                    <div style={{ textAlign: 'center' }}>
                                        <svg width="80" height="80" viewBox="0 0 80 80">
                                            <circle cx="40" cy="40" r="34" fill="none" stroke={T.border} strokeWidth="4" />
                                            <circle cx="40" cy="40" r="34" fill="none" stroke={timerColor} strokeWidth="4" strokeDasharray={`${timerPct * 213.6} 213.6`} strokeLinecap="round" transform="rotate(-90 40 40)" style={{ transition: 'stroke-dasharray 1s ease, stroke 0.5s ease' }} />
                                            <text x="40" y="38" textAnchor="middle" style={{ fontSize: 16, fontWeight: 800, fill: T.text, fontFamily: T.mono }}>{fmtSeconds(elapsed)}</text>
                                            <text x="40" y="52" textAnchor="middle" style={{ fontSize: 8, fill: T.textMuted }}>{currentTicket.counter_number ? `V${currentTicket.counter_number}` : ''}</text>
                                        </svg>
                                    </div>
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 18, background: T.surface, borderRadius: T.radiusSm, padding: 14 }}>
                                    {[{ l: 'Cliente', v: currentTicket.customer_name || 'Anónimo' }, { l: 'Servicio', v: currentTicket.service_name }, { l: 'Cola', v: `${currentTicket.queue_prefix || ''} — ${currentTicket.queue_name}` }, { l: 'Prioridad', v: currentTicket.priority_label }].map(d => (
                                        <div key={d.l}><div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 2 }}>{d.l}</div><div style={{ fontSize: 14, fontWeight: 600 }}>{d.v}</div></div>
                                    ))}
                                </div>
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                    {currentTicket.status === 'called' && <><Btn variant="success" size="lg" onClick={startServing}>▶ Iniciar Atención</Btn><Btn variant="ghost" onClick={recallTicket}>◈ Re-llamar</Btn><Btn variant="warning" size="sm" onClick={noShowTicket}>No se presentó</Btn></>}
                                    {currentTicket.status === 'in_progress' && <><Btn variant="success" size="lg" onClick={completeTicket}>◆ Completar</Btn><Btn variant="ghost" onClick={() => setShowTransfer(true)}>↻ Transferir</Btn><Btn variant="danger" size="sm" onClick={cancelTicket}>◇ Cancelar</Btn></>}
                                </div>
                                {currentTicket.status === 'in_progress' && (
                                    <div style={{ marginTop: 18, paddingTop: 18, borderTop: `1px solid ${T.border}` }}>
                                        <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginBottom: 10 }}>
                                            <span style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em' }}>Calificación</span>
                                            <div style={{ display: 'flex', gap: 4 }}>{[1,2,3,4,5].map(n => <span key={n} onClick={() => setRating(n)} style={{ cursor: 'pointer', fontSize: 22, transition: 'all 0.2s', filter: n <= rating ? 'none' : 'grayscale(1) opacity(0.2)', transform: n <= rating ? 'scale(1.1)' : 'scale(1)' }}>★</span>)}</div>
                                        </div>
                                        <textarea value={notes} onChange={e => setNotes(e.target.value)} placeholder="Notas opcionales..." style={{ width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`, borderRadius: T.radiusSm, padding: 12, fontSize: 12, resize: 'vertical', minHeight: 50, fontFamily: T.font }} />
                                    </div>
                                )}
                                {showTransfer && (
                                    <div style={{ marginTop: 16, padding: 16, background: T.surface, borderRadius: T.radiusSm, border: `1px solid ${T.cyan}20` }}>
                                        <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 10, color: T.cyan }}>↻ Transferir turno</div>
                                        <Select value={transferQueue} onChange={e => setTransferQueue(e.target.value)} label="Cola destino" options={[{ value: '', label: 'Seleccionar...' }, ...(allQueues || []).map(q => ({ value: q.id, label: `${q.prefix} — ${q.name}` }))]} />
                                        <textarea value={transferReason} onChange={e => setTransferReason(e.target.value)} placeholder="Razón..." style={{ width: '100%', background: T.card, color: T.text, border: `1px solid ${T.border}`, borderRadius: T.radiusSm, padding: 10, fontSize: 12, marginTop: 8, resize: 'none', height: 50, fontFamily: T.font }} />
                                        <div style={{ display: 'flex', gap: 8, marginTop: 10 }}><Btn variant="primary" onClick={doTransfer} disabled={!transferQueue}>Transferir</Btn><Btn variant="ghost" onClick={() => setShowTransfer(false)}>Cancelar</Btn></div>
                                    </div>
                                )}
                            </Card>
                        ) : (
                            <Card className="t-fade-up" style={{ textAlign: 'center', padding: '48px 24px', marginBottom: 16 }}>
                                <div style={{ fontSize: 48, marginBottom: 14, opacity: 0.4 }}>◷</div>
                                <div style={{ fontSize: 18, fontWeight: 700, marginBottom: 6 }}>Sin turno activo</div>
                                <div style={{ fontSize: 13, color: T.textMuted, marginBottom: 24, maxWidth: 280, margin: '0 auto 24px' }}>
                                    {filteredWaiting > 0 ? `Hay ${filteredWaiting} turno${filteredWaiting > 1 ? 's' : ''} esperando` : 'Esperando nuevos turnos...'}
                                </div>
                                <Btn variant="primary" size="lg" onClick={() => callNext()} disabled={!selectedCounter || filteredWaiting === 0}>◈ Llamar Siguiente</Btn>
                                {!selectedCounter && <div style={{ fontSize: 11, color: T.amber, marginTop: 10 }}>Selecciona una ventanilla</div>}
                            </Card>
                        )}

                        {/* Queue cards - click calls DIRECTLY with that queue_id */}
                        {!currentTicket && queues?.length > 0 && (
                            <div style={{ display: 'grid', gridTemplateColumns: `repeat(${Math.min(queues.length, 3)}, 1fr)`, gap: 10 }}>
                                {queues.map((q, i) => (
                                    <Card key={q.id} className={`t-fade-up t-stagger-${i + 1}`}
                                        glow={q.waiting > 0 ? T.amberGlow : null}
                                        style={{
                                            textAlign: 'center', cursor: q.waiting > 0 ? 'pointer' : 'default',
                                            transition: 'all 0.2s',
                                            borderColor: selectedQueue === q.id ? T.blue : undefined,
                                            boxShadow: selectedQueue === q.id ? `0 0 0 1px ${T.blue}40` : undefined,
                                        }}
                                        onClick={() => {
                                            if (q.waiting > 0) {
                                                // FIX: Pass queue_id directly to callNext, not via setTimeout
                                                callNext(q.id);
                                            } else {
                                                // Just filter to show this queue's tickets
                                                setSelectedQueue(prev => prev === q.id ? '' : q.id);
                                            }
                                        }}>
                                        <div style={{ fontSize: 28, fontWeight: 900, color: q.waiting > 0 ? T.amber : T.textMuted, fontFamily: T.mono }}>{q.waiting}</div>
                                        <div style={{ fontSize: 10, color: T.textMuted }}>{q.prefix} — {q.name}</div>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* ── Right: Waiting queue (filtered) ── */}
                    <Card className="t-fade-up t-stagger-2" style={{ maxHeight: 'calc(100vh - 140px)', overflowY: 'auto', padding: 0 }}>
                        <div style={{ padding: '14px 18px', borderBottom: `1px solid ${T.border}`, position: 'sticky', top: 0, background: T.card, zIndex: 1, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <div>
                                <span style={{ fontSize: 14, fontWeight: 700 }}>Cola de Espera</span>
                                {selectedQueue && (
                                    <span style={{ fontSize: 10, color: T.blue, marginLeft: 8, cursor: 'pointer' }} onClick={() => setSelectedQueue('')}>
                                        ({queues?.find(q => q.id === selectedQueue)?.name}) ✕
                                    </span>
                                )}
                            </div>
                            <span style={{ fontSize: 20, fontWeight: 900, color: filteredWaiting > 0 ? T.amber : T.textMuted, fontFamily: T.mono }}>{filteredWaiting}</span>
                        </div>
                        {filteredTickets.length === 0 ? (
                            <div style={{ padding: 40, textAlign: 'center', color: T.textMuted, fontSize: 13 }}>
                                <div style={{ fontSize: 28, marginBottom: 8, opacity: 0.3 }}>◷</div>
                                No hay turnos en espera
                                <div style={{ fontSize: 10, marginTop: 4, opacity: 0.5 }}>{currentBranch?.name}</div>
                            </div>
                        ) : (
                            filteredTickets.map((t, i) => (
                                <div key={t.id} className={`t-fade-up t-stagger-${Math.min(i + 1, 8)}`} style={{
                                    padding: '13px 18px', display: 'flex', alignItems: 'center', gap: 12,
                                    borderBottom: `1px solid ${T.border}08`,
                                    background: (t.priority === 'vip' || t.priority === 'urgent') ? `${T.red}06` : 'transparent',
                                }}>
                                    <div style={{ width: 7, height: 7, borderRadius: '50%', flexShrink: 0, background: t.service_color || T.blue, boxShadow: `0 0 6px ${t.service_color || T.blue}40` }} />
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontSize: 14, fontWeight: 700, fontFamily: T.mono }}>{t.display_number}</div>
                                        <div style={{ fontSize: 10, color: T.textMuted, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.customer_name || 'Anónimo'} · {t.service_name}</div>
                                    </div>
                                    <div style={{ textAlign: 'right', flexShrink: 0 }}>
                                        <div style={{ fontSize: 12, fontWeight: 600, fontFamily: T.mono, color: t.wait_minutes > 10 ? T.red : t.wait_minutes > 5 ? T.amber : T.textSoft }}>{Math.max(0, t.wait_minutes)}m</div>
                                        <div style={{ fontSize: 8, color: T.textMuted, textTransform: 'uppercase' }}>{t.queue_prefix} · {t.priority_label}</div>
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
