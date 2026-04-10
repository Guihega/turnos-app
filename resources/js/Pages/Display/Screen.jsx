// resources/js/Pages/Display/Screen.jsx
import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';
import { T } from '@/Components/TurnosUI';
import { useBranchChannel } from '@/Hooks/useBranchChannel';
import useTenantBranding from '@/Hooks/useTenantBranding';

export default function DisplayScreen({ branch, initialData, isPublic = false }) {
    const { branding, display, tickets, logoUrl, tenantName, cssVars } = useTenantBranding();
    const [data, setData] = useState(initialData || { serving: [], recent: [], waitingCount: 0, avgWaitMinutes: 0 });
    const [clock, setClock] = useState(new Date());
    const [flash, setFlash] = useState(false);
    const [wsConnected, setWsConnected] = useState(false);
    const lastEventRef = useRef(Date.now());

    // Tenant display settings
    const primaryColor = branding.primary_color || T.blue;
    const secondaryColor = branding.secondary_color || T.purple;
    const showQueueName = display.show_queue_name ?? true;
    const showServiceName = display.show_service_name ?? true;
    const showWaitTime = display.show_wait_time ?? true;
    const recentCount = display.show_recent_count || 5;
    const announcementText = display.announcement_text || null;
    const callSound = display.call_sound || 'chime';

    const soundMap = { chime: '/sounds/chime.mp3', bell: '/sounds/bell.mp3', ding: '/sounds/ding.mp3', none: null };

    const playCallSound = useCallback(() => {
        const src = soundMap[callSound];
        if (!src) return;
        try { const audio = new Audio(src); audio.play().catch(() => {}); } catch {}
    }, [callSound]);

    // Clock
    useEffect(() => {
        const id = setInterval(() => setClock(new Date()), 1000);
        return () => clearInterval(id);
    }, []);

    // Full data reload
    const reloadData = useCallback(() => {
        router.reload({
            only: ['initialData'],
            preserveScroll: true,
            onSuccess: (page) => {
                const newData = page.props.initialData;
                if (newData) {
                    if (newData.serving?.length > data.serving?.length) {
                        setFlash(true);
                        setTimeout(() => setFlash(false), 2500);
                    }
                    setData(newData);
                }
            }
        });
    }, [data.serving?.length]);

    // WebSocket listeners
    useBranchChannel(branch?.id, 'display', {
        'TicketCalled': () => {
            lastEventRef.current = Date.now();
            setWsConnected(true);
            setFlash(true);
            playCallSound();
            setTimeout(() => setFlash(false), 2500);
            reloadData();
        },
        'TicketCompleted': () => {
            lastEventRef.current = Date.now();
            setWsConnected(true);
            reloadData();
        },
    });

    // Echo connection status
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

    // Polling fallback
    useEffect(() => {
        const interval = wsConnected ? 30000 : 5000;
        const id = setInterval(reloadData, interval);
        return () => clearInterval(id);
    }, [wsConnected, reloadData]);

    const serving = data.serving || [];
    const recent = (data.recent || []).slice(0, recentCount);
    const now = serving.filter(t => t.status === 'called' || t.status === 'in_progress');

    return (<>
        <Head title={`Pantalla — ${branch.name}`} />
        <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100vh', padding: 0, overflow: 'hidden', ...cssVars }}>

            {/* ── Header ── */}
            <div style={{
                padding: '16px 28px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                borderBottom: `1px solid ${T.border}`, background: T.card,
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                    {logoUrl ? (
                        <img src={logoUrl} alt={tenantName} style={{
                            width: 40, height: 40,
                            borderRadius: branding.logo_shape === 'circle' ? '50%' : branding.logo_shape === 'rounded' ? 10 : 4,
                            objectFit: 'cover',
                        }} />
                    ) : (
                        <div style={{
                            width: 40, height: 40, borderRadius: 10,
                            background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`,
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontWeight: 900, fontSize: 16, color: '#fff',
                        }}>
                            {(tenantName || 'O')[0]}
                        </div>
                    )}
                    <div>
                        <div style={{ fontSize: 18, fontWeight: 800, letterSpacing: '-0.02em' }}>{branch.name}</div>
                        <div style={{ fontSize: 11, color: T.textMuted }}>{tenantName}</div>
                    </div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 20 }}>
                    {/* Connection indicator */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
                        <div style={{
                            width: 7, height: 7, borderRadius: '50%',
                            background: wsConnected ? T.green : T.amber,
                            boxShadow: wsConnected ? `0 0 8px var(--t-green-glow)` : 'none',
                            animation: wsConnected ? 'tPulse 3s ease-in-out infinite' : 'none',
                        }} />
                        <span style={{ fontSize: 9, color: T.textMuted, fontFamily: T.mono }}>
                            {wsConnected ? 'LIVE' : 'POLL'}
                        </span>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 32, fontWeight: 800, fontFamily: T.mono, fontVariantNumeric: 'tabular-nums', lineHeight: 1 }}>
                            {clock.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}
                        </div>
                        <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                            {clock.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })}
                        </div>
                    </div>
                </div>
            </div>

            {/* Announcement banner */}
            {announcementText && (
                <div style={{
                    padding: '8px 28px',
                    background: `color-mix(in srgb, ${primaryColor} 6%, transparent)`,
                    borderBottom: `1px solid color-mix(in srgb, ${primaryColor} 15%, transparent)`,
                    display: 'flex', alignItems: 'center', gap: 8,
                }}>
                    <span style={{ fontSize: 13 }}>📢</span>
                    <span style={{ fontSize: 12, color: primaryColor, fontWeight: 600 }}>{announcementText}</span>
                </div>
            )}

            {/* ── Main Content ── */}
            <div style={{
                display: 'grid', gridTemplateColumns: '1fr 320px', gap: 0,
                minHeight: `calc(100vh - ${announcementText ? '130px' : '90px'})`,
            }}>
                {/* Left: Now serving */}
                <div style={{ padding: '24px 28px', display: 'flex', flexDirection: 'column' }}>
                    <div style={{
                        fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: 'uppercase',
                        letterSpacing: '0.15em', marginBottom: 20, fontFamily: T.mono,
                    }}>
                        Atendiendo ahora
                    </div>

                    {now.length === 0 ? (
                        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', opacity: 0.4 }}>
                            <div style={{ fontSize: 64, marginBottom: 16 }}>⏳</div>
                            <div style={{ fontSize: 20, fontWeight: 600, color: T.textMuted }}>Esperando turnos...</div>
                        </div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14, flex: 1 }}>
                            {now.map((ticket, i) => (
                                <div key={i} style={{
                                    background: T.card, borderRadius: 20, padding: '24px 28px',
                                    border: `1px solid ${flash && i === 0 ? (ticket.service_color || T.green) : T.border}`,
                                    borderLeft: `6px solid ${ticket.service_color || T.green}`,
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    animation: flash && i === 0 ? 'screenCallPulse 1.5s ease-in-out' : 'none',
                                    flex: now.length === 1 ? '0 0 auto' : 1,
                                }}>
                                    <div>
                                        <div style={{
                                            fontSize: now.length === 1 ? 72 : 52,
                                            fontWeight: 900, fontFamily: T.mono, letterSpacing: '-0.03em',
                                            lineHeight: 1, color: T.text,
                                        }}>
                                            {ticket.display_number}
                                        </div>
                                        {showServiceName && ticket.service_name && (
                                            <div style={{ fontSize: 13, color: T.textMuted, marginTop: 8 }}>{ticket.service_name}</div>
                                        )}
                                    </div>
                                    <div style={{ textAlign: 'right' }}>
                                        <div style={{
                                            fontSize: 10, color: T.textMuted, textTransform: 'uppercase',
                                            letterSpacing: '0.12em', marginBottom: 6, fontFamily: T.mono,
                                        }}>Ventanilla</div>
                                        <div style={{
                                            fontSize: now.length === 1 ? 80 : 56,
                                            fontWeight: 900, fontFamily: T.mono, lineHeight: 1,
                                            color: T.green,
                                            textShadow: flash && i === 0 ? `0 0 30px var(--t-green-glow)` : 'none',
                                        }}>
                                            {ticket.counter_number || '—'}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Right: Stats + recent */}
                <div style={{ borderLeft: `1px solid ${T.border}`, display: 'flex', flexDirection: 'column', background: T.surface }}>
                    {/* Stats */}
                    <div style={{ display: 'grid', gridTemplateColumns: showWaitTime ? '1fr 1fr' : '1fr', borderBottom: `1px solid ${T.border}` }}>
                        <div style={{
                            padding: '20px 16px', textAlign: 'center',
                            borderRight: showWaitTime ? `1px solid ${T.border}` : 'none',
                        }}>
                            <div style={{ fontSize: 36, fontWeight: 900, color: T.amber, fontFamily: T.mono }}>{data.waitingCount}</div>
                            <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', fontFamily: T.mono, marginTop: 2 }}>En espera</div>
                        </div>
                        {showWaitTime && (
                            <div style={{ padding: '20px 16px', textAlign: 'center' }}>
                                <div style={{ fontSize: 36, fontWeight: 900, fontFamily: T.mono }}>~{data.avgWaitMinutes}</div>
                                <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', fontFamily: T.mono, marginTop: 2 }}>Min. espera</div>
                            </div>
                        )}
                    </div>

                    {/* Recent tickets */}
                    <div style={{ flex: 1, padding: '16px' }}>
                        <div style={{
                            fontSize: 10, fontWeight: 700, color: T.textMuted, textTransform: 'uppercase',
                            letterSpacing: '0.12em', marginBottom: 14, fontFamily: T.mono,
                        }}>Últimos atendidos</div>
                        {recent.length === 0 ? (
                            <div style={{ color: T.textMuted, fontSize: 12, textAlign: 'center', padding: 20, opacity: 0.5 }}>Sin turnos atendidos aún</div>
                        ) : (
                            recent.map((t, i) => (
                                <div key={i} style={{
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    padding: '10px 4px',
                                    borderBottom: i < recent.length - 1 ? `1px solid color-mix(in srgb, ${T.border} 50%, transparent)` : 'none',
                                }}>
                                    <span style={{ fontSize: 15, fontWeight: 700, fontFamily: T.mono, color: T.textMuted }}>{t.display_number}</span>
                                    {showQueueName && (
                                        <span style={{
                                            fontSize: 11, color: T.textMuted, fontFamily: T.mono,
                                            background: T.card, padding: '2px 8px', borderRadius: 4,
                                        }}>V{t.counter_number || '—'}</span>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes screenCallPulse {
                    0% { box-shadow: 0 0 0 0 rgba(0,214,143,0.4); }
                    25% { box-shadow: 0 0 40px 8px rgba(0,214,143,0.15); }
                    50% { box-shadow: 0 0 60px 12px rgba(0,214,143,0.08); }
                    100% { box-shadow: 0 0 0 0 rgba(0,214,143,0); }
                }
                @keyframes tPulse { 0%, 100% { opacity: 1; } 50% { opacity: .4; } }
                * { margin: 0; padding: 0; box-sizing: border-box; }
            `}</style>
        </div>
    </>);
}
