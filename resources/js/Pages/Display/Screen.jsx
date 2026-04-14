// resources/js/Pages/Display/Screen.jsx
import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';
import { T } from '@/Components/TurnosUI';
import { useBranchChannel } from '@/Hooks/useBranchChannel';
import useTenantBranding from '@/Hooks/useTenantBranding';

// ── Constantes ──
const ANNOUNCEMENT_ROTATE_MS = 8000;
const NEWS_ROTATE_MS = 12000;

export default function DisplayScreen({ branch, initialData, announcements: initialAnnouncements = [], isPublic = false }) {
    const { branding, display, tickets, logoUrl, tenantName, cssVars } = useTenantBranding();
    const [data, setData] = useState(initialData || { serving: [], recent: [], waitingCount: 0, avgWaitMinutes: 0 });
    const [clock, setClock] = useState(new Date());
    const [flash, setFlash] = useState(false);
    const [wsConnected, setWsConnected] = useState(false);
    const [announcements, setAnnouncements] = useState(initialAnnouncements);
    const [currentAnnouncementIdx, setCurrentAnnouncementIdx] = useState(0);
    const lastEventRef = useRef(Date.now());

    // Configuración del tenant
    const primaryColor = branding.primary_color || T.blue;
    const secondaryColor = branding.secondary_color || T.purple;
    const accentColor = branding.accent_color || T.green;
    const showQueueName = display.show_queue_name ?? true;
    const showServiceName = display.show_service_name ?? true;
    const showWaitTime = display.show_wait_time ?? true;
    const recentCount = display.show_recent_count || 5;
    const callSound = display.call_sound || 'chime';

    const soundMap = { chime: '/sounds/chime.mp3', bell: '/sounds/bell.mp3', ding: '/sounds/ding.mp3', none: null };

    const playCallSound = useCallback(() => {
        const src = soundMap[callSound];
        if (!src) return;
        try { const audio = new Audio(src); audio.play().catch(() => {}); } catch {}
    }, [callSound]);

    // ── Reloj ──
    useEffect(() => {
        const id = setInterval(() => setClock(new Date()), 1000);
        return () => clearInterval(id);
    }, []);

    // ── Rotación de anuncios ──
    useEffect(() => {
        if (announcements.length <= 1) return;
        const id = setInterval(() => {
            setCurrentAnnouncementIdx(prev => (prev + 1) % announcements.length);
        }, ANNOUNCEMENT_ROTATE_MS);
        return () => clearInterval(id);
    }, [announcements.length]);

    // ── Recarga de datos ──
    const reloadData = useCallback(() => {
        router.reload({
            only: ['initialData', 'announcements'],
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
                if (page.props.announcements) {
                    setAnnouncements(page.props.announcements);
                }
            }
        });
    }, [data.serving?.length]);

    // ── WebSocket ──
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

    // ── Datos derivados ──
    const serving = data.serving || [];
    const recent = (data.recent || []).slice(0, recentCount);
    const now = serving.filter(t => t.status === 'called' || t.status === 'in_progress');
    const currentAnnouncement = announcements[currentAnnouncementIdx];

    // Filtrar por tipo
    const announcementItems = announcements.filter(a => a.type === 'announcement' || a.type === 'promo');
    const newsItems = announcements.filter(a => a.type === 'news');

    // Formateo de fecha
    const dateStr = clock.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const timeStr = clock.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    return (<>
        <Head title={`Pantalla — ${branch.name}`} />
        <div style={{
            fontFamily: T.font, background: '#060810', color: '#E8ECF4',
            minHeight: '100vh', overflow: 'hidden', ...cssVars,
            display: 'grid',
            gridTemplateRows: 'auto 1fr auto',
            gridTemplateColumns: '1fr 340px',
            height: '100vh',
        }}>

            {/* ═══════════ HEADER ═══════════ */}
            <header style={{
                gridColumn: '1 / -1',
                padding: '12px 28px',
                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                background: 'rgba(255,255,255,0.02)',
                borderBottom: '1px solid rgba(255,255,255,0.06)',
            }}>
                {/* Logo + nombre */}
                <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                    {logoUrl ? (
                        <img src={logoUrl} alt={tenantName} style={{
                            width: 44, height: 44,
                            borderRadius: branding.logo_shape === 'circle' ? '50%' : branding.logo_shape === 'rounded' ? 10 : 4,
                            objectFit: 'cover',
                        }} />
                    ) : (
                        <div style={{
                            width: 44, height: 44, borderRadius: 10,
                            background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`,
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontWeight: 900, fontSize: 18, color: '#fff',
                        }}>
                            {(tenantName || 'O')[0]}
                        </div>
                    )}
                    <div>
                        <div style={{ fontSize: 20, fontWeight: 800, letterSpacing: '-0.02em', color: '#F1F5F9' }}>
                            {branch.name}
                        </div>
                        <div style={{ fontSize: 11, color: '#64748B', fontWeight: 500 }}>{tenantName}</div>
                    </div>
                </div>

                {/* Fecha, hora y estado */}
                <div style={{ display: 'flex', alignItems: 'center', gap: 24 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                        <div style={{
                            width: 8, height: 8, borderRadius: '50%',
                            background: wsConnected ? '#10B981' : '#F59E0B',
                            boxShadow: wsConnected ? '0 0 12px rgba(16,185,129,0.5)' : 'none',
                            animation: wsConnected ? 'tPulse 3s ease-in-out infinite' : 'none',
                        }} />
                        <span style={{ fontSize: 10, color: '#64748B', fontFamily: T.mono, fontWeight: 600 }}>
                            {wsConnected ? 'EN VIVO' : 'POLL'}
                        </span>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                        <div style={{
                            fontSize: 36, fontWeight: 800, fontFamily: T.mono,
                            fontVariantNumeric: 'tabular-nums', lineHeight: 1,
                            color: '#F1F5F9', letterSpacing: '-0.02em',
                        }}>
                            {clock.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}
                        </div>
                        <div style={{ fontSize: 11, color: '#64748B', marginTop: 3, textTransform: 'capitalize' }}>
                            {dateStr}
                        </div>
                    </div>
                </div>
            </header>

            {/* ═══════════ ZONA PRINCIPAL — TURNOS ═══════════ */}
            <main style={{
                padding: '24px 28px',
                display: 'flex', flexDirection: 'column',
                overflow: 'hidden',
            }}>
                {/* Turno actual destacado */}
                {now.length === 0 ? (
                    <div style={{
                        flex: 1, display: 'flex', flexDirection: 'column',
                        alignItems: 'center', justifyContent: 'center',
                    }}>
                        <div style={{ fontSize: 72, marginBottom: 16, opacity: 0.3 }}>⏳</div>
                        <div style={{ fontSize: 22, fontWeight: 600, color: '#475569' }}>
                            Esperando turnos...
                        </div>
                    </div>
                ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14, flex: 1 }}>
                        {/* Etiqueta */}
                        <div style={{
                            fontSize: 11, fontWeight: 700, color: '#64748B', textTransform: 'uppercase',
                            letterSpacing: '0.15em', fontFamily: T.mono,
                        }}>
                            Atendiendo ahora
                        </div>

                        {now.map((ticket, i) => (
                            <div key={i} style={{
                                background: 'rgba(255,255,255,0.03)',
                                borderRadius: 20,
                                padding: now.length === 1 ? '36px 32px' : '24px 28px',
                                border: `1px solid ${flash && i === 0 ? (ticket.service_color || accentColor) + '60' : 'rgba(255,255,255,0.06)'}`,
                                borderLeft: `6px solid ${ticket.service_color || accentColor}`,
                                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                animation: flash && i === 0 ? 'screenCallPulse 1.5s ease-in-out' : 'none',
                                transition: 'border-color 0.3s ease',
                                flex: now.length <= 2 ? '0 0 auto' : 1,
                            }}>
                                <div>
                                    <div style={{
                                        fontSize: now.length === 1 ? 80 : now.length === 2 ? 60 : 48,
                                        fontWeight: 900, fontFamily: T.mono, letterSpacing: '-0.03em',
                                        lineHeight: 1, color: '#F1F5F9',
                                    }}>
                                        {ticket.display_number}
                                    </div>
                                    {showServiceName && ticket.service_name && (
                                        <div style={{
                                            fontSize: 14, color: '#94A3B8', marginTop: 8,
                                            display: 'flex', alignItems: 'center', gap: 8,
                                        }}>
                                            <span style={{
                                                width: 8, height: 8, borderRadius: '50%',
                                                background: ticket.service_color || accentColor,
                                            }} />
                                            {ticket.service_name}
                                        </div>
                                    )}
                                </div>
                                <div style={{ textAlign: 'right' }}>
                                    <div style={{
                                        fontSize: 10, color: '#64748B', textTransform: 'uppercase',
                                        letterSpacing: '0.15em', marginBottom: 6, fontFamily: T.mono,
                                    }}>Ventanilla</div>
                                    <div style={{
                                        fontSize: now.length === 1 ? 88 : now.length === 2 ? 64 : 52,
                                        fontWeight: 900, fontFamily: T.mono, lineHeight: 1,
                                        color: accentColor,
                                        textShadow: flash && i === 0 ? `0 0 40px ${accentColor}50` : 'none',
                                        transition: 'text-shadow 0.3s ease',
                                    }}>
                                        {ticket.counter_number || '—'}
                                    </div>
                                </div>
                            </div>
                        ))}

                        {/* Siguientes en espera (mini lista) */}
                        {data.waitingCount > 0 && (
                            <div style={{
                                marginTop: 'auto',
                                padding: '14px 20px',
                                background: 'rgba(255,255,255,0.02)',
                                borderRadius: 12,
                                border: '1px solid rgba(255,255,255,0.04)',
                            }}>
                                <div style={{
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                }}>
                                    <span style={{
                                        fontSize: 11, fontWeight: 700, color: '#64748B',
                                        textTransform: 'uppercase', letterSpacing: '0.12em', fontFamily: T.mono,
                                    }}>
                                        En espera
                                    </span>
                                    <span style={{
                                        fontSize: 24, fontWeight: 900, fontFamily: T.mono,
                                        color: '#F59E0B',
                                    }}>
                                        {data.waitingCount}
                                    </span>
                                </div>
                                {showWaitTime && data.avgWaitMinutes > 0 && (
                                    <div style={{
                                        fontSize: 11, color: '#64748B', marginTop: 4,
                                    }}>
                                        Tiempo promedio de espera: ~{data.avgWaitMinutes} min
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </main>

            {/* ═══════════ SIDEBAR DERECHO ═══════════ */}
            <aside style={{
                gridRow: '2 / 4',
                borderLeft: '1px solid rgba(255,255,255,0.06)',
                display: 'flex', flexDirection: 'column',
                background: 'rgba(255,255,255,0.015)',
                overflow: 'hidden',
            }}>
                {/* Últimos atendidos */}
                <div style={{ padding: '16px 18px', borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
                    <div style={{
                        fontSize: 10, fontWeight: 700, color: '#64748B', textTransform: 'uppercase',
                        letterSpacing: '0.12em', marginBottom: 14, fontFamily: T.mono,
                    }}>Últimos atendidos</div>
                    {recent.length === 0 ? (
                        <div style={{ color: '#475569', fontSize: 12, textAlign: 'center', padding: 16, opacity: 0.5 }}>
                            Sin turnos atendidos aún
                        </div>
                    ) : (
                        recent.map((t, i) => (
                            <div key={i} style={{
                                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                padding: '10px 6px',
                                borderBottom: i < recent.length - 1 ? '1px solid rgba(255,255,255,0.04)' : 'none',
                            }}>
                                <span style={{
                                    fontSize: 16, fontWeight: 700, fontFamily: T.mono,
                                    color: '#94A3B8',
                                }}>
                                    {t.display_number}
                                </span>
                                <span style={{
                                    fontSize: 11, color: '#64748B', fontFamily: T.mono,
                                    background: 'rgba(255,255,255,0.04)',
                                    padding: '3px 10px', borderRadius: 6,
                                }}>
                                    V{t.counter_number || '—'}
                                </span>
                            </div>
                        ))
                    )}
                </div>

                {/* Anuncios rotativos */}
                {announcementItems.length > 0 && (
                    <div style={{
                        flex: 1, padding: '18px',
                        borderBottom: '1px solid rgba(255,255,255,0.06)',
                        display: 'flex', flexDirection: 'column',
                    }}>
                        <div style={{
                            fontSize: 10, fontWeight: 700, color: '#64748B', textTransform: 'uppercase',
                            letterSpacing: '0.12em', marginBottom: 14, fontFamily: T.mono,
                            display: 'flex', alignItems: 'center', gap: 6,
                        }}>
                            <span>📢</span> Anuncios
                        </div>
                        <AnnouncementCarousel items={announcementItems} primaryColor={primaryColor} />
                    </div>
                )}

                {/* Noticias / Info */}
                {newsItems.length > 0 && (
                    <div style={{
                        padding: '18px',
                        borderBottom: '1px solid rgba(255,255,255,0.06)',
                    }}>
                        <div style={{
                            fontSize: 10, fontWeight: 700, color: '#64748B', textTransform: 'uppercase',
                            letterSpacing: '0.12em', marginBottom: 14, fontFamily: T.mono,
                            display: 'flex', alignItems: 'center', gap: 6,
                        }}>
                            <span>📰</span> Noticias
                        </div>
                        {newsItems.slice(0, 3).map((item, i) => (
                            <div key={item.id || i} style={{
                                padding: '10px 12px',
                                background: 'rgba(255,255,255,0.02)',
                                borderRadius: 10,
                                marginBottom: 8,
                                border: '1px solid rgba(255,255,255,0.04)',
                            }}>
                                <div style={{ fontSize: 12, fontWeight: 700, color: '#CBD5E1', lineHeight: 1.4 }}>
                                    {item.title}
                                </div>
                                {item.body && (
                                    <div style={{ fontSize: 11, color: '#64748B', marginTop: 4, lineHeight: 1.5 }}>
                                        {item.body.length > 120 ? item.body.slice(0, 120) + '...' : item.body}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {/* Stats compactos al fondo del sidebar */}
                <div style={{
                    marginTop: 'auto',
                    padding: '16px 18px',
                    background: 'rgba(255,255,255,0.02)',
                    borderTop: '1px solid rgba(255,255,255,0.06)',
                }}>
                    <div style={{ display: 'grid', gridTemplateColumns: showWaitTime ? '1fr 1fr' : '1fr', gap: 12 }}>
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: 28, fontWeight: 900, color: '#F59E0B', fontFamily: T.mono }}>
                                {data.waitingCount}
                            </div>
                            <div style={{
                                fontSize: 9, color: '#64748B', textTransform: 'uppercase',
                                letterSpacing: '0.1em', fontFamily: T.mono, marginTop: 2,
                            }}>En espera</div>
                        </div>
                        {showWaitTime && (
                            <div style={{ textAlign: 'center' }}>
                                <div style={{ fontSize: 28, fontWeight: 900, fontFamily: T.mono, color: '#CBD5E1' }}>
                                    ~{data.avgWaitMinutes}
                                </div>
                                <div style={{
                                    fontSize: 9, color: '#64748B', textTransform: 'uppercase',
                                    letterSpacing: '0.1em', fontFamily: T.mono, marginTop: 2,
                                }}>Min. espera</div>
                            </div>
                        )}
                    </div>
                </div>
            </aside>

            {/* ═══════════ FOOTER — BARRA DE ANUNCIOS ═══════════ */}
            <footer style={{
                gridColumn: '1 / 1',
                padding: '12px 28px',
                background: 'rgba(255,255,255,0.02)',
                borderTop: '1px solid rgba(255,255,255,0.06)',
                display: 'flex', alignItems: 'center', gap: 16,
                overflow: 'hidden',
                minHeight: 48,
            }}>
                {currentAnnouncement ? (
                    <div style={{
                        display: 'flex', alignItems: 'center', gap: 12,
                        animation: 'fadeSlideIn 0.5s ease',
                        width: '100%',
                    }} key={currentAnnouncementIdx}>
                        <span style={{
                            fontSize: 10, fontWeight: 800, color: primaryColor,
                            background: `${primaryColor}15`,
                            padding: '4px 10px', borderRadius: 6,
                            textTransform: 'uppercase', letterSpacing: '0.08em',
                            fontFamily: T.mono, whiteSpace: 'nowrap',
                        }}>
                            {currentAnnouncement.type === 'news' ? 'Noticia' :
                             currentAnnouncement.type === 'promo' ? 'Promo' : 'Aviso'}
                        </span>
                        <span style={{ fontSize: 13, fontWeight: 600, color: '#CBD5E1' }}>
                            {currentAnnouncement.title}
                        </span>
                        {currentAnnouncement.body && (
                            <span style={{ fontSize: 12, color: '#64748B', marginLeft: 4 }}>
                                — {currentAnnouncement.body.length > 80 ? currentAnnouncement.body.slice(0, 80) + '...' : currentAnnouncement.body}
                            </span>
                        )}
                    </div>
                ) : (
                    <div style={{ fontSize: 12, color: '#475569' }}>
                        {tenantName} · {branch.name}
                    </div>
                )}

                {/* Indicador de anuncios */}
                {announcements.length > 1 && (
                    <div style={{ display: 'flex', gap: 4, marginLeft: 'auto', flexShrink: 0 }}>
                        {announcements.map((_, i) => (
                            <div key={i} style={{
                                width: i === currentAnnouncementIdx ? 16 : 5,
                                height: 5, borderRadius: 3,
                                background: i === currentAnnouncementIdx ? primaryColor : 'rgba(255,255,255,0.1)',
                                transition: 'all 0.3s ease',
                            }} />
                        ))}
                    </div>
                )}
            </footer>

            {/* ═══════════ ESTILOS ═══════════ */}
            <style>{`
                @keyframes screenCallPulse {
                    0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
                    25% { box-shadow: 0 0 40px 8px rgba(16,185,129,0.15); }
                    50% { box-shadow: 0 0 60px 12px rgba(16,185,129,0.08); }
                    100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
                }
                @keyframes tPulse { 0%, 100% { opacity: 1; } 50% { opacity: .4; } }
                @keyframes fadeSlideIn {
                    from { opacity: 0; transform: translateX(10px); }
                    to { opacity: 1; transform: translateX(0); }
                }
                * { margin: 0; padding: 0; box-sizing: border-box; }
            `}</style>
        </div>
    </>);
}

// ── Componente: Carrusel de anuncios ──
function AnnouncementCarousel({ items, primaryColor }) {
    const [idx, setIdx] = useState(0);

    useEffect(() => {
        if (items.length <= 1) return;
        const id = setInterval(() => {
            setIdx(prev => (prev + 1) % items.length);
        }, ANNOUNCEMENT_ROTATE_MS);
        return () => clearInterval(id);
    }, [items.length]);

    const current = items[idx];
    if (!current) return null;

    return (
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
            <div key={idx} style={{
                padding: '14px 16px',
                background: `${primaryColor}08`,
                border: `1px solid ${primaryColor}15`,
                borderRadius: 12,
                animation: 'fadeSlideIn 0.4s ease',
                flex: 1,
            }}>
                <div style={{
                    fontSize: 10, fontWeight: 800, color: primaryColor,
                    textTransform: 'uppercase', letterSpacing: '0.08em',
                    marginBottom: 6, fontFamily: "'JetBrains Mono', monospace",
                }}>
                    {current.type === 'promo' ? '🎉 Promoción' : '📢 Anuncio'}
                </div>
                <div style={{ fontSize: 14, fontWeight: 700, color: '#E2E8F0', lineHeight: 1.4, marginBottom: 4 }}>
                    {current.title}
                </div>
                {current.body && (
                    <div style={{ fontSize: 12, color: '#94A3B8', lineHeight: 1.5 }}>
                        {current.body.length > 150 ? current.body.slice(0, 150) + '...' : current.body}
                    </div>
                )}
            </div>
            {items.length > 1 && (
                <div style={{ display: 'flex', justifyContent: 'center', gap: 5, marginTop: 10 }}>
                    {items.map((_, i) => (
                        <div key={i} style={{
                            width: 6, height: 6, borderRadius: '50%',
                            background: i === idx ? primaryColor : 'rgba(255,255,255,0.1)',
                            transition: 'background 0.3s ease',
                        }} />
                    ))}
                </div>
            )}
        </div>
    );
}
