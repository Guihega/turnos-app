// resources/js/Pages/Public/TicketStatus.jsx
import { Head, router } from '@inertiajs/react';
import { useEffect, useState, useRef } from 'react';
import { T } from '@/Components/TurnosUI';
import { useBranchChannel } from '@/Hooks/useBranchChannel';
import useTenantBranding from '@/Hooks/useTenantBranding';

const statusCfg = {
    waiting:     { icon: '◷', label: 'En espera', color: 'var(--t-amber, #F59E0B)',  msg: 'Estás en la fila. Te avisaremos cuando sea tu turno.', ms: 5000 },
    called:      { icon: '📢', label: 'Te toca', color: 'var(--t-blue, #3B82F6)',    msg: '¡Dirígete a la ventanilla ahora!', ms: 3000 },
    in_progress: { icon: '◉', label: 'En atención', color: 'var(--t-purple, #8B5CF6)', msg: 'Estás siendo atendido.', ms: 10000 },
    completed:   { icon: '✓', label: 'Completado', color: 'var(--t-green, #10B981)',  msg: '¡Gracias por su visita!', ms: 0 },
    cancelled:   { icon: '✕', label: 'Cancelado', color: 'var(--t-red, #EF4444)',    msg: 'Este turno fue cancelado.', ms: 0 },
    no_show:     { icon: '◌', label: 'No presentado', color: '#F97316',              msg: 'No se presentó a la ventanilla.', ms: 0 },
    transferred: { icon: '↻', label: 'Reasignado', color: 'var(--t-cyan, #06B6D4)',  msg: 'Tu turno fue reasignado a otra cola.', ms: 5000 },
};

export default function TicketStatus({ branch, ticket }) {
    const { branding, logoUrl, tenantName, cssVars } = useTenantBranding();
    const [pulse, setPulse] = useState(false);
    const [prev, setPrev] = useState(ticket.status);
    const [justCalled, setJustCalled] = useState(ticket.status === 'called');
    const [wsConnected, setWsConnected] = useState(false);
    const cfg = statusCfg[ticket.status] || statusCfg.waiting;
    const done = ['completed', 'cancelled', 'no_show'].includes(ticket.status);
    const primaryColor = branding.primary_color || '#3B82F6';

    // Vibrate when called
    const vibrate = () => {
        try { navigator.vibrate?.([200, 100, 200, 100, 400]); } catch {}
    };

    // ─── WebSocket: listen for ticket events ───
    useBranchChannel(branch?.id, 'display', {
        'TicketCalled': (eventData) => {
            setWsConnected(true);
            // Check if this event is for our ticket
            router.reload({ only: ['ticket'], onSuccess: (page) => {
                const t = page.props.ticket;
                if (t?.status !== prev) {
                    setPrev(t.status);
                    setPulse(true);
                    setTimeout(() => setPulse(false), 1000);
                    if (t.status === 'called') {
                        setJustCalled(true);
                        vibrate();
                    }
                }
            }});
        },
        'TicketCompleted': (eventData) => {
            setWsConnected(true);
            router.reload({ only: ['ticket'], onSuccess: (page) => {
                const t = page.props.ticket;
                if (t?.status !== prev) {
                    setPrev(t.status);
                    setPulse(true);
                    setTimeout(() => setPulse(false), 1000);
                    setJustCalled(false);
                }
            }});
        },
    });

    // ─── Polling fallback (only if WS not connected) ───
    useEffect(() => {
        if (done || !cfg.ms) return;
        const interval = wsConnected ? 15000 : cfg.ms;
        const id = setInterval(() => {
            router.reload({ only: ['ticket'], onSuccess: (p) => {
                if (p.props.ticket?.status !== prev) {
                    setPulse(true); setPrev(p.props.ticket.status);
                    setTimeout(() => setPulse(false), 800);
                    if (p.props.ticket.status === 'called') { setJustCalled(true); vibrate(); }
                    else setJustCalled(false);
                }
            }});
        }, interval);
        return () => clearInterval(id);
    }, [ticket.status, prev, wsConnected]);

    // ─── WS connection status ───
    useEffect(() => {
        if (!window.Echo) return;
        const check = () => {
            const state = window.Echo?.connector?.pusher?.connection?.state;
            setWsConnected(state === 'connected');
        };
        check();
        const id = setInterval(check, 5000);
        return () => clearInterval(id);
    }, []);

    // ─── Auto-redirect on transfer ───
    useEffect(() => {
        if (ticket.status === 'transferred' && ticket.new_ticket?.url) {
            const t = setTimeout(() => { window.location.href = ticket.new_ticket.url; }, 4000);
            return () => clearTimeout(t);
        }
    }, [ticket.status, ticket.new_ticket?.url]);

    const logoRadius = branding.logo_shape === 'circle' ? '50%' : branding.logo_shape === 'rounded' ? 12 : 4;

    return (<>
        <Head title={`Turno ${ticket.display_number}`} />
        <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100dvh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '24px 16px', ...cssVars }}>
            <div style={{ maxWidth: 420, width: '100%', textAlign: 'center' }}>

                {/* Branch header */}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, marginBottom: 24 }}>
                    {logoUrl ? (
                        <img src={logoUrl} alt={tenantName} style={{ width: 28, height: 28, borderRadius: logoRadius, objectFit: 'cover' }} />
                    ) : (
                        <div style={{ width: 28, height: 28, borderRadius: 8, background: `linear-gradient(135deg, ${primaryColor}, ${branding.secondary_color || '#8B5CF6'})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 11, color: '#fff' }}>{(tenantName || 'O')[0]}</div>
                    )}
                    <span style={{ fontSize: 14, fontWeight: 600, color: T.textMuted }}>{branch.name}</span>
                    {/* Connection indicator */}
                    <div style={{ width: 6, height: 6, borderRadius: '50%', background: wsConnected ? '#10B981' : T.textMuted, marginLeft: 4 }} title={wsConnected ? 'Tiempo real' : 'Actualización periódica'} />
                </div>

                {/* ══ CALLED STATE — Full-screen prominent ══ */}
                {justCalled && ticket.status === 'called' && (
                    <div style={{
                        background: `linear-gradient(135deg, ${primaryColor}, color-mix(in srgb, ${primaryColor} 70%, #6366F1))`,
                        borderRadius: 28, padding: '40px 28px', marginBottom: 20,
                        animation: 'calledPulse 2s ease-in-out infinite',
                        boxShadow: `0 8px 40px color-mix(in srgb, ${primaryColor} 40%, transparent)`,
                    }}>
                        <div style={{ fontSize: 48, marginBottom: 12 }}>📢</div>
                        <div style={{ fontSize: 14, color: 'rgba(255,255,255,0.7)', textTransform: 'uppercase', letterSpacing: '0.2em', fontWeight: 700, marginBottom: 8 }}>¡Es tu turno!</div>
                        <div style={{ fontSize: 56, fontWeight: 900, fontFamily: T.mono, color: '#fff', letterSpacing: '-0.04em', lineHeight: 1, marginBottom: 12 }}>
                            {ticket.display_number}
                        </div>
                        <div style={{ fontSize: 13, color: 'rgba(255,255,255,0.7)', marginBottom: 20 }}>Dirígete a la ventanilla</div>
                        <div style={{
                            display: 'inline-block', background: 'rgba(255,255,255,0.2)', borderRadius: 20, padding: '16px 40px',
                            backdropFilter: 'blur(10px)',
                        }}>
                            <div style={{ fontSize: 10, color: 'rgba(255,255,255,0.6)', textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 4 }}>Ventanilla</div>
                            <div style={{ fontSize: 52, fontWeight: 900, color: '#fff', fontFamily: T.mono, lineHeight: 1 }}>{ticket.counter_number || '—'}</div>
                        </div>
                    </div>
                )}

                {/* ══ NORMAL STATES ══ */}
                {!(justCalled && ticket.status === 'called') && (
                    <div style={{
                        background: T.card, borderRadius: 24, padding: '36px 28px 32px', border: `1px solid ${T.border}`,
                        position: 'relative', overflow: 'hidden', marginBottom: 20,
                    }}>
                        {/* Accent bar */}
                        <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 3, background: `linear-gradient(90deg, ${cfg.color}, transparent 80%)`, transition: 'background 0.5s' }} />

                        <div style={{ fontSize: 42, marginBottom: 12, transition: 'transform 0.4s', transform: pulse ? 'scale(1.15)' : 'scale(1)' }}>{cfg.icon}</div>

                        <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.15em', marginBottom: 8 }}>Tu turno</div>

                        <div style={{
                            fontSize: 48, fontWeight: 900, fontFamily: T.mono, letterSpacing: '-0.04em',
                            color: cfg.color, lineHeight: 1, marginBottom: 14,
                            animation: pulse ? 'tScaleIn 0.5s ease' : 'none',
                        }}>
                            {ticket.display_number}
                        </div>

                        <div style={{
                            display: 'inline-block', padding: '8px 20px', borderRadius: 20,
                            background: `color-mix(in srgb, ${cfg.color} 12%, transparent)`, color: cfg.color,
                            fontSize: 13, fontWeight: 700, marginBottom: 18, letterSpacing: '0.02em',
                        }}>
                            {cfg.label}
                        </div>

                        <p style={{ fontSize: 14, color: T.textMuted, lineHeight: 1.6, marginBottom: 20, maxWidth: 300, margin: '0 auto 20px' }}>{cfg.msg}</p>

                        {/* Counter (for in_progress) */}
                        {ticket.status === 'in_progress' && ticket.counter_number && (
                            <div style={{ background: T.bg, borderRadius: 16, padding: '18px 24px', display: 'inline-block' }}>
                                <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 4 }}>Ventanilla</div>
                                <div style={{ fontSize: 48, fontWeight: 900, color: T.blue, fontFamily: T.mono, lineHeight: 1 }}>{ticket.counter_number}</div>
                            </div>
                        )}

                        {/* Position + wait estimate (for waiting) */}
                        {ticket.status === 'waiting' && (
                            <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 4 }}>
                                {ticket.position != null && (
                                    <div style={{ background: T.bg, borderRadius: 14, padding: '14px 24px' }}>
                                        <div style={{ fontSize: 28, fontWeight: 900, color: T.amber, fontFamily: T.mono }}>{ticket.position}</div>
                                        <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em' }}>en la fila</div>
                                    </div>
                                )}
                                {ticket.estimated_wait_minutes != null && (
                                    <div style={{ background: T.bg, borderRadius: 14, padding: '14px 24px' }}>
                                        <div style={{ fontSize: 28, fontWeight: 900, fontFamily: T.mono }}>~{ticket.estimated_wait_minutes}</div>
                                        <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em' }}>min</div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Transfer info */}
                        {ticket.status === 'transferred' && ticket.new_ticket && (
                            <div style={{ background: T.bg, borderRadius: 16, padding: 20, border: `1px solid color-mix(in srgb, var(--t-cyan, #06B6D4) 15%, transparent)`, marginTop: 12 }}>
                                <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 6 }}>Nuevo turno</div>
                                <div style={{ fontSize: 32, fontWeight: 900, color: 'var(--t-cyan, #06B6D4)', fontFamily: T.mono, marginBottom: 4 }}>{ticket.new_ticket.display_number}</div>
                                <div style={{ fontSize: 12, color: T.textMuted }}>Cola: {ticket.new_ticket.queue_name}</div>
                                <div style={{ fontSize: 11, color: T.textMuted, marginTop: 8 }}>Redirigiendo automáticamente...</div>
                            </div>
                        )}

                        {/* Take another turn */}
                        {ticket.status === 'completed' && (
                            <button onClick={() => window.location.href = `/kiosco/${branch.id}`} style={{
                                marginTop: 8, padding: '14px 32px', borderRadius: 12,
                                background: `linear-gradient(135deg, ${primaryColor}, color-mix(in srgb, ${primaryColor} 70%, black))`,
                                color: '#fff', border: 'none', fontSize: 15, fontWeight: 800, cursor: 'pointer', fontFamily: T.font,
                                boxShadow: `0 4px 16px color-mix(in srgb, ${primaryColor} 30%, transparent)`,
                            }}>
                                Tomar otro turno
                            </button>
                        )}
                    </div>
                )}

                {/* ── Ticket details ── */}
                <div style={{ background: T.card, borderRadius: 16, padding: '16px 20px', border: `1px solid ${T.border}`, textAlign: 'left' }}>
                    {[
                        { l: 'Servicio', v: ticket.service_name },
                        { l: 'Cola', v: ticket.queue_name },
                        ticket.customer_name && { l: 'Nombre', v: ticket.customer_name },
                        ticket.issued_at && { l: 'Emitido', v: new Date(ticket.issued_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) },
                        ticket.called_at && { l: 'Llamado', v: new Date(ticket.called_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) },
                        ticket.completed_at && { l: 'Completado', v: new Date(ticket.completed_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) },
                    ].filter(Boolean).map((item, i, arr) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: i < arr.length - 1 ? `1px solid ${T.border}` : 'none' }}>
                            <span style={{ fontSize: 12, color: T.textMuted }}>{item.l}</span>
                            <span style={{ fontSize: 12, fontWeight: 600 }}>{item.v}</span>
                        </div>
                    ))}
                </div>

                {/* Status line */}
                {!done && (
                    <div style={{ marginTop: 16, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6 }}>
                        <div style={{ width: 6, height: 6, borderRadius: '50%', background: wsConnected ? '#10B981' : T.amber, animation: 'blink 2s infinite' }} />
                        <span style={{ fontSize: 10, color: T.textMuted }}>
                            {wsConnected ? 'Actualización en tiempo real' : 'Actualizando cada unos segundos'}
                        </span>
                    </div>
                )}
            </div>

            <style>{`
                @keyframes tScaleIn { from{transform:scale(.94);opacity:0} to{transform:scale(1);opacity:1} }
                @keyframes calledPulse { 0%,100%{box-shadow:0 8px 40px color-mix(in srgb, ${primaryColor} 40%, transparent)} 50%{box-shadow:0 8px 60px color-mix(in srgb, ${primaryColor} 60%, transparent), 0 0 0 8px color-mix(in srgb, ${primaryColor} 15%, transparent)} }
                @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
                * { margin:0; padding:0; box-sizing:border-box; }
            `}</style>
        </div>
    </>);
}
