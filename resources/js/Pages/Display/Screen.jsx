// resources/js/Pages/Display/Screen.jsx
import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { T } from '@/Components/TurnosUI';

export default function DisplayScreen({ branch, initialData, isPublic = false }) {
    const [data, setData] = useState(initialData || { serving: [], recent: [], waitingCount: 0, avgWaitMinutes: 0 });
    const [clock, setClock] = useState(new Date());
    const [flash, setFlash] = useState(null);

    useEffect(() => {
        const id = setInterval(() => setClock(new Date()), 1000);
        return () => clearInterval(id);
    }, []);

    useEffect(() => {
        const id = setInterval(() => {
            router.reload({
                only: ['initialData'],
                preserveScroll: true,
                onSuccess: (page) => {
                    const newData = page.props.initialData;
                    if (newData) {
                        if (newData.serving?.length > data.serving?.length) setFlash(true);
                        setData(newData);
                        setTimeout(() => setFlash(false), 2000);
                    }
                }
            });
        }, 5000);
        return () => clearInterval(id);
    }, [data.serving?.length]);

    const serving = data.serving || [];
    const recent = data.recent || [];
    const now = serving.filter(t => t.status === 'called' || t.status === 'in_progress');

    return (<>
        <Head title={`Pantalla — ${branch.name}`} />
        <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100vh', padding: 0, overflow: 'hidden' }}>

            {/* Header */}
            <div style={{ padding: '20px 32px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: `1px solid ${T.border}` }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                    <div style={{ width: 44, height: 44, borderRadius: 12, background: `linear-gradient(135deg, ${T.blue}, ${T.purple})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 18, color: '#fff' }}>T</div>
                    <div>
                        <div style={{ fontSize: 20, fontWeight: 800 }}>{branch.name}</div>
                        <div style={{ fontSize: 12, color: T.textMuted }}>Sistema de Turnos</div>
                    </div>
                </div>
                <div style={{ textAlign: 'right' }}>
                    <div style={{ fontSize: 36, fontWeight: 800, fontFamily: T.mono, fontVariantNumeric: 'tabular-nums' }}>
                        {clock.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}
                    </div>
                    <div style={{ fontSize: 12, color: T.textMuted }}>{clock.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })}</div>
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 340px', gap: 0, minHeight: 'calc(100vh - 100px)' }}>
                {/* Left: Now serving */}
                <div style={{ padding: '28px 32px' }}>
                    <div style={{ fontSize: 13, fontWeight: 700, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.15em', marginBottom: 20 }}>
                        Atendiendo Ahora
                    </div>

                    {now.length === 0 ? (
                        <div style={{ textAlign: 'center', padding: '80px 0', color: T.textMuted }}>
                            <div style={{ fontSize: 56, marginBottom: 16 }}>⏳</div>
                            <div style={{ fontSize: 18 }}>Esperando turnos...</div>
                        </div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            {now.map((ticket, i) => (
                                <div key={i} style={{
                                    background: T.card, borderRadius: 20, padding: '28px 32px',
                                    border: `1px solid ${T.border}`, borderLeft: `5px solid ${ticket.service_color || T.green}`,
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    animation: flash && i === 0 ? 'screenPulse 1s ease-in-out' : 'none',
                                }}>
                                    <div>
                                        <div style={{ fontSize: 56, fontWeight: 900, fontFamily: T.mono, letterSpacing: '-0.03em', lineHeight: 1, color: T.text }}>
                                            {ticket.display_number}
                                        </div>
                                        {ticket.service_name && (
                                            <div style={{ fontSize: 14, color: T.textMuted, marginTop: 8 }}>{ticket.service_name}</div>
                                        )}
                                    </div>
                                    <div style={{ textAlign: 'right' }}>
                                        <div style={{ fontSize: 13, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 6 }}>Ventanilla</div>
                                        <div style={{ fontSize: 64, fontWeight: 900, color: T.green, fontFamily: T.mono, lineHeight: 1 }}>
                                            {ticket.counter_number || '—'}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Right: Stats + recent */}
                <div style={{ borderLeft: `1px solid ${T.border}`, display: 'flex', flexDirection: 'column' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', borderBottom: `1px solid ${T.border}` }}>
                        <div style={{ padding: '24px 20px', textAlign: 'center', borderRight: `1px solid ${T.border}` }}>
                            <div style={{ fontSize: 42, fontWeight: 900, color: T.amber, fontFamily: T.mono }}>{data.waitingCount}</div>
                            <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em' }}>En espera</div>
                        </div>
                        <div style={{ padding: '24px 20px', textAlign: 'center' }}>
                            <div style={{ fontSize: 42, fontWeight: 900, fontFamily: T.mono }}>~{data.avgWaitMinutes}</div>
                            <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em' }}>Min. espera</div>
                        </div>
                    </div>

                    <div style={{ flex: 1, padding: '20px' }}>
                        <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.12em', marginBottom: 16 }}>Últimos Atendidos</div>
                        {recent.length === 0 ? (
                            <div style={{ color: T.textMuted, fontSize: 13, textAlign: 'center', padding: 24 }}>Sin turnos atendidos aún</div>
                        ) : (
                            recent.map((t, i) => (
                                <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: i < recent.length - 1 ? `1px solid ${T.border}` : 'none' }}>
                                    <span style={{ fontSize: 16, fontWeight: 700, fontFamily: T.mono, color: T.textMuted }}>{t.display_number}</span>
                                    <span style={{ fontSize: 13, color: T.textMuted }}>V{t.counter_number || '—'}</span>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes screenPulse { 0%{box-shadow:0 0 0 0 rgba(0,214,143,0.4)} 50%{box-shadow:0 0 40px 8px rgba(0,214,143,0.1)} 100%{box-shadow:0 0 0 0 rgba(0,214,143,0)} }
                * { margin:0; padding:0; box-sizing:border-box; }
            `}</style>
        </div>
    </>);
}
