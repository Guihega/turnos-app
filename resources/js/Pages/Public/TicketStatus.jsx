// resources/js/Pages/Public/TicketStatus.jsx
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const theme = { bg: '#050810', cardBg: '#0F1219', accent: '#3B82F6', success: '#10B981', warning: '#F59E0B', danger: '#EF4444', textPrimary: '#F0F4FC', textMuted: '#5C6478', border: '#1E2432' };
const statusConfig = {
    waiting: { icon: '⏳', color: '#EAB308', msg: 'Estás en la fila. Te llamaremos pronto.' },
    called: { icon: '📢', color: '#3B82F6', msg: '¡Tu turno fue llamado! Dirígete a la ventanilla.' },
    in_progress: { icon: '⚡', color: '#6366F1', msg: 'Estás siendo atendido.' },
    completed: { icon: '✅', color: '#10B981', msg: 'Tu atención ha finalizado. ¡Gracias!' },
    cancelled: { icon: '❌', color: '#EF4444', msg: 'Este turno fue cancelado.' },
    no_show: { icon: '👤', color: '#F97316', msg: 'No se presentó a la ventanilla.' },
};

export default function TicketStatus({ branch, ticket }) {
    const [pulse, setPulse] = useState(false);
    const cfg = statusConfig[ticket.status] || statusConfig.waiting;

    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['ticket'], onSuccess: () => setPulse(true) });
            setTimeout(() => setPulse(false), 500);
        }, 5000);
        return () => clearInterval(id);
    }, []);

    return (
        <>
            <Head title={`Turno ${ticket.display_number}`} />
            <div style={{ fontFamily: "'DM Sans', sans-serif", background: theme.bg, color: theme.textPrimary, minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24 }}>
                <div style={{ maxWidth: 420, width: '100%', textAlign: 'center' }}>
                    {/* Branch name */}
                    <div style={{ fontSize: 14, color: theme.textMuted, marginBottom: 32 }}>{branch.name}</div>

                    {/* Main ticket card */}
                    <div style={{
                        background: theme.cardBg, borderRadius: 24, padding: '40px 32px', border: `1px solid ${theme.border}`,
                        position: 'relative', overflow: 'hidden', marginBottom: 24,
                        boxShadow: `0 0 60px ${cfg.color}15`,
                    }}>
                        <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 4, background: cfg.color }} />

                        <div style={{ fontSize: 56, marginBottom: 12 }}>{cfg.icon}</div>

                        <div style={{ fontSize: 11, color: theme.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8 }}>Tu turno</div>

                        <div style={{
                            fontSize: 56, fontWeight: 800, letterSpacing: '-0.03em', fontVariantNumeric: 'tabular-nums',
                            color: cfg.color, lineHeight: 1, marginBottom: 16,
                            animation: pulse ? 'ticketPulse 0.5s ease' : 'none',
                        }}>
                            {ticket.display_number}
                        </div>

                        {/* Status badge */}
                        <div style={{
                            display: 'inline-block', padding: '8px 20px', borderRadius: 24,
                            background: `${cfg.color}18`, color: cfg.color, fontSize: 14, fontWeight: 600, marginBottom: 20,
                        }}>
                            {ticket.status_label}
                        </div>

                        <p style={{ fontSize: 15, color: theme.textMuted, lineHeight: 1.5, marginBottom: 24 }}>{cfg.msg}</p>

                        {/* Counter info when called */}
                        {(ticket.status === 'called' || ticket.status === 'in_progress') && ticket.counter_number && (
                            <div style={{ background: theme.bg, borderRadius: 16, padding: 20, marginBottom: 16 }}>
                                <div style={{ fontSize: 11, color: theme.textMuted, textTransform: 'uppercase', marginBottom: 4 }}>Ventanilla</div>
                                <div style={{ fontSize: 48, fontWeight: 800, color: theme.accent }}>{ticket.counter_number}</div>
                            </div>
                        )}

                        {/* Position and wait time */}
                        {ticket.status === 'waiting' && (
                            <div style={{ display: 'flex', gap: 16, justifyContent: 'center' }}>
                                {ticket.position && (
                                    <div style={{ background: theme.bg, borderRadius: 12, padding: '14px 24px' }}>
                                        <div style={{ fontSize: 28, fontWeight: 700, color: theme.warning }}>{ticket.position}</div>
                                        <div style={{ fontSize: 10, color: theme.textMuted }}>en la fila</div>
                                    </div>
                                )}
                                {ticket.estimated_wait_minutes != null && (
                                    <div style={{ background: theme.bg, borderRadius: 12, padding: '14px 24px' }}>
                                        <div style={{ fontSize: 28, fontWeight: 700 }}>~{ticket.estimated_wait_minutes}</div>
                                        <div style={{ fontSize: 10, color: theme.textMuted }}>min. espera</div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Details */}
                    <div style={{ background: theme.cardBg, borderRadius: 16, padding: 20, border: `1px solid ${theme.border}`, textAlign: 'left' }}>
                        {[
                            { l: 'Servicio', v: ticket.service_name },
                            { l: 'Cola', v: ticket.queue_name },
                            ticket.customer_name && { l: 'Nombre', v: ticket.customer_name },
                            ticket.issued_at && { l: 'Emitido', v: new Date(ticket.issued_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) },
                            ticket.called_at && { l: 'Llamado', v: new Date(ticket.called_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) },
                        ].filter(Boolean).map((item, i) => (
                            <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: i < 4 ? `1px solid ${theme.border}` : 'none' }}>
                                <span style={{ fontSize: 12, color: theme.textMuted }}>{item.l}</span>
                                <span style={{ fontSize: 12, fontWeight: 600 }}>{item.v}</span>
                            </div>
                        ))}
                    </div>

                    <div style={{ marginTop: 24, fontSize: 11, color: theme.textMuted }}>
                        Esta página se actualiza automáticamente
                    </div>
                </div>

                <style>{`
                    @keyframes ticketPulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                `}</style>
            </div>
        </>
    );
}
