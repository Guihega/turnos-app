// resources/js/Pages/Public/TicketStatus.jsx
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { T } from '@/Components/TurnosUI';

const statusCfg = {
    waiting:     { icon: '◷', color: 'var(--t-amber)',  msg: 'Estás en la fila. Te llamaremos pronto.', ms: 5000 },
    called:      { icon: '◈', color: 'var(--t-blue)',   msg: '¡Dirígete a la ventanilla ahora!', ms: 3000 },
    in_progress: { icon: '◉', color: 'var(--t-purple)', msg: 'Estás siendo atendido.', ms: 10000 },
    completed:   { icon: '◆', color: 'var(--t-green)',  msg: 'Tu atención ha finalizado. ¡Gracias!', ms: 0 },
    cancelled:   { icon: '◇', color: 'var(--t-red)',    msg: 'Este turno fue cancelado.', ms: 0 },
    no_show:     { icon: '◌', color: '#F97316',         msg: 'No se presentó a la ventanilla.', ms: 0 },
    transferred: { icon: '↻', color: 'var(--t-cyan)',   msg: 'Tu turno fue reasignado.', ms: 5000 },
};

export default function TicketStatus({ branch, ticket }) {
    const [pulse, setPulse] = useState(false);
    const [prev, setPrev] = useState(ticket.status);
    const cfg = statusCfg[ticket.status] || statusCfg.waiting;
    const done = ['completed', 'cancelled', 'no_show'].includes(ticket.status);

    useEffect(() => {
        if (done || !cfg.ms) return;
        const id = setInterval(() => {
            router.reload({ only: ['ticket'], onSuccess: (p) => {
                if (p.props.ticket?.status !== prev) { setPulse(true); setPrev(p.props.ticket.status); setTimeout(() => setPulse(false), 800); }
            }});
        }, cfg.ms);
        return () => clearInterval(id);
    }, [ticket.status, prev]);

    useEffect(() => {
        if (ticket.status === 'transferred' && ticket.new_ticket?.url) {
            const t = setTimeout(() => { window.location.href = ticket.new_ticket.url; }, 4000);
            return () => clearTimeout(t);
        }
    }, [ticket.status, ticket.new_ticket?.url]);

    return (<>
        <Head title={`Turno ${ticket.display_number}`} />
        <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
            <div style={{ maxWidth: 400, width: '100%', textAlign: 'center' }}>
                <div style={{ fontSize: 13, color: T.textMuted, marginBottom: 28, letterSpacing: '0.05em' }}>{branch.name}</div>

                <div className="t-fade-up" style={{
                    background: T.card, borderRadius: 28, padding: '44px 32px 36px', border: `1px solid ${T.border}`,
                    position: 'relative', overflow: 'hidden', marginBottom: 20,
                }}>
                    <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 3, background: `linear-gradient(90deg, ${cfg.color}, transparent 80%)`, transition: 'background 0.5s' }} />

                    <div style={{ fontSize: 48, marginBottom: 14, transition: 'transform 0.4s', transform: pulse ? 'scale(1.15)' : 'scale(1)', opacity: 0.8 }}>{cfg.icon}</div>

                    <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.15em', marginBottom: 8 }}>Tu turno</div>

                    <div style={{
                        fontSize: 52, fontWeight: 900, fontFamily: T.mono, letterSpacing: '-0.04em',
                        color: cfg.color, lineHeight: 1, marginBottom: 16,
                        animation: pulse ? 'tScaleIn 0.5s ease' : 'none',
                    }}>
                        {ticket.display_number}
                    </div>

                    <div style={{
                        display: 'inline-block', padding: '8px 22px', borderRadius: 24,
                        background: `color-mix(in srgb, ${cfg.color} 12%, transparent)`, color: cfg.color,
                        fontSize: 14, fontWeight: 700, marginBottom: 20, letterSpacing: '0.02em',
                    }}>
                        {ticket.status_label}
                    </div>

                    <p style={{ fontSize: 14, color: T.textMuted, lineHeight: 1.6, marginBottom: 24 }}>{cfg.msg}</p>

                    {(ticket.status === 'called' || ticket.status === 'in_progress') && ticket.counter_number && (
                        <div style={{ background: T.bg, borderRadius: 20, padding: 24, marginBottom: 16, animation: ticket.status === 'called' ? 'tCounterPulse 2s ease-in-out infinite' : 'none' }}>
                            <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 6 }}>Ventanilla</div>
                            <div style={{ fontSize: 60, fontWeight: 900, color: T.blue, fontFamily: T.mono, lineHeight: 1 }}>{ticket.counter_number}</div>
                        </div>
                    )}

                    {ticket.status === 'waiting' && (
                        <div style={{ display: 'flex', gap: 14, justifyContent: 'center' }}>
                            {ticket.position != null && (
                                <div style={{ background: T.bg, borderRadius: 14, padding: '16px 28px' }}>
                                    <div style={{ fontSize: 30, fontWeight: 900, color: T.amber, fontFamily: T.mono }}>{ticket.position}</div>
                                    <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em' }}>en la fila</div>
                                </div>
                            )}
                            {ticket.estimated_wait_minutes != null && (
                                <div style={{ background: T.bg, borderRadius: 14, padding: '16px 28px' }}>
                                    <div style={{ fontSize: 30, fontWeight: 900, fontFamily: T.mono }}>~{ticket.estimated_wait_minutes}</div>
                                    <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em' }}>min.</div>
                                </div>
                            )}
                        </div>
                    )}

                    {ticket.status === 'transferred' && ticket.new_ticket && (
                        <div style={{ background: T.bg, borderRadius: 18, padding: 22, border: `1px solid color-mix(in srgb, ${T.cyan} 15%, transparent)`, marginBottom: 12 }}>
                            <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8 }}>Nuevo turno</div>
                            <div style={{ fontSize: 34, fontWeight: 900, color: T.cyan, fontFamily: T.mono, marginBottom: 6 }}>{ticket.new_ticket.display_number}</div>
                            <div style={{ fontSize: 12, color: T.textMuted }}>Cola: {ticket.new_ticket.queue_name}</div>
                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 10 }}>Redirigiendo...</div>
                            <button onClick={() => window.location.href = ticket.new_ticket.url}
                                style={{ marginTop: 12, padding: '10px 24px', borderRadius: 10, background: `linear-gradient(135deg, ${T.cyan}, color-mix(in srgb, ${T.cyan} 70%, black))`, color: '#fff', border: 'none', fontSize: 13, fontWeight: 700, cursor: 'pointer', fontFamily: T.font }}>
                                Ver nuevo turno →
                            </button>
                        </div>
                    )}

                    {ticket.status === 'completed' && (
                        <button onClick={() => window.location.href = `/kiosco/${branch.id}`}
                            style={{ padding: '14px 32px', borderRadius: 12, background: `linear-gradient(135deg, ${T.green}, color-mix(in srgb, ${T.green} 70%, black))`, color: '#fff', border: 'none', fontSize: 15, fontWeight: 800, cursor: 'pointer', fontFamily: T.font }}>
                            Tomar otro turno
                        </button>
                    )}
                </div>

                <div className="t-fade-up" style={{ background: T.card, borderRadius: 18, padding: 20, border: `1px solid ${T.border}`, textAlign: 'left' }}>
                    {[
                        { l: 'Servicio', v: ticket.service_name },
                        { l: 'Cola', v: ticket.queue_name },
                        ticket.customer_name && { l: 'Nombre', v: ticket.customer_name },
                        ticket.issued_at && { l: 'Emitido', v: new Date(ticket.issued_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) },
                        ticket.called_at && { l: 'Llamado', v: new Date(ticket.called_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) },
                        ticket.completed_at && { l: 'Completado', v: new Date(ticket.completed_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) },
                    ].filter(Boolean).map((item, i, arr) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '9px 0', borderBottom: i < arr.length - 1 ? `1px solid ${T.border}` : 'none' }}>
                            <span style={{ fontSize: 12, color: T.textMuted }}>{item.l}</span>
                            <span style={{ fontSize: 12, fontWeight: 600, fontFamily: T.font }}>{item.v}</span>
                        </div>
                    ))}
                </div>

                {!done && <div style={{ marginTop: 20, fontSize: 10, color: T.textMuted, letterSpacing: '0.05em' }}>Actualización automática</div>}
            </div>
            <style>{`
                @keyframes tScaleIn { from{transform:scale(.94);opacity:0} to{transform:scale(1);opacity:1} }
                @keyframes tCounterPulse { 0%,100%{box-shadow:0 0 0 0 rgba(61,122,255,.25)} 50%{box-shadow:0 0 30px 8px rgba(61,122,255,.08)} }
                @keyframes tFadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
                .t-fade-up { animation: tFadeUp .5s ease both; }
                * { margin:0; padding:0; box-sizing:border-box; }
            `}</style>
        </div>
    </>);
}
