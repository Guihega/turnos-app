// resources/js/Pages/Display/Screen.jsx
import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

const theme = {
    bg: '#050810', cardBg: '#0D1117', accent: '#3B82F6',
    success: '#10B981', warning: '#F59E0B', danger: '#EF4444',
    textPrimary: '#F0F4FC', textMuted: '#4B5568',
};

export default function DisplayScreen({ branch, initialData, isPublic = false }) {
    const [data, setData] = useState(initialData);
    const [clock, setClock] = useState(new Date());
    const [flash, setFlash] = useState(null);

    // Auto-refresh every 5 seconds
    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['initialData'], onSuccess: (page) => {
                const newData = page.props.initialData;
                // Flash animation on new ticket called
                if (newData.serving?.[0]?.display_number !== data.serving?.[0]?.display_number) {
                    setFlash(newData.serving?.[0]?.display_number);
                    setTimeout(() => setFlash(null), 4000);
                }
                setData(newData);
            }});
        }, 5000);
        return () => clearInterval(id);
    }, [data]);

    useEffect(() => {
        const id = setInterval(() => setClock(new Date()), 1000);
        return () => clearInterval(id);
    }, []);

    const { serving = [], recent = [], waitingCount = 0, avgWaitMinutes = 0 } = data;

    return (
        <>
            <Head title={`Pantalla — ${branch.name}`} />
            <div style={{
                fontFamily: "'DM Sans', -apple-system, sans-serif",
                background: theme.bg, color: theme.textPrimary,
                minHeight: '100vh', padding: 0, overflow: 'hidden',
            }}>
                {/* Header */}
                <div style={{
                    padding: '20px 40px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    background: 'linear-gradient(180deg, #0D1117 0%, transparent 100%)',
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                        <div style={{
                            width: 44, height: 44, borderRadius: 10,
                            background: 'linear-gradient(135deg, #3B82F6, #8B5CF6)',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontWeight: 800, fontSize: 18, color: '#fff',
                        }}>T</div>
                        <div>
                            <div style={{ fontSize: 20, fontWeight: 700 }}>{branch.name}</div>
                            <div style={{ fontSize: 12, color: theme.textMuted }}>Sistema de Turnos</div>
                        </div>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 32, fontWeight: 700, fontVariantNumeric: 'tabular-nums', letterSpacing: '-0.02em' }}>
                            {clock.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}
                        </div>
                        <div style={{ fontSize: 12, color: theme.textMuted }}>
                            {clock.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })}
                        </div>
                    </div>
                </div>

                {/* Flash notification */}
                {flash && (
                    <div style={{
                        position: 'fixed', top: 100, left: '50%', transform: 'translateX(-50%)',
                        background: theme.accent, color: '#fff', padding: '20px 60px', borderRadius: 16,
                        fontSize: 28, fontWeight: 800, zIndex: 100, boxShadow: '0 20px 60px rgba(59,130,246,0.4)',
                        animation: 'flashIn 0.4s ease-out',
                    }}>
                        📢 TURNO {flash}
                    </div>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: 0, height: 'calc(100vh - 100px)' }}>
                    {/* ── Main: Now Serving ── */}
                    <div style={{ padding: '20px 40px' }}>
                        <div style={{ fontSize: 14, fontWeight: 600, color: theme.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 20 }}>
                            Atendiendo ahora
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 16 }}>
                            {serving.length === 0 && (
                                <div style={{ gridColumn: '1 / -1', textAlign: 'center', padding: 60, color: theme.textMuted }}>
                                    <div style={{ fontSize: 48, marginBottom: 8 }}>⏳</div>
                                    <div style={{ fontSize: 18 }}>Esperando turnos...</div>
                                </div>
                            )}
                            {serving.map((t, i) => (
                                <div key={i} style={{
                                    background: theme.cardBg, borderRadius: 16, padding: '24px 28px',
                                    border: `1px solid ${i === 0 ? theme.accent + '40' : '#1E2432'}`,
                                    position: 'relative', overflow: 'hidden',
                                    animation: i === 0 ? 'pulse-border 2s ease-in-out infinite' : 'none',
                                }}>
                                    {/* Color bar */}
                                    <div style={{
                                        position: 'absolute', top: 0, left: 0, right: 0, height: 4,
                                        background: t.service_color || theme.accent,
                                    }} />

                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                        <div>
                                            <div style={{ fontSize: 42, fontWeight: 800, letterSpacing: '-0.03em', fontVariantNumeric: 'tabular-nums', lineHeight: 1 }}>
                                                {t.display_number}
                                            </div>
                                            <div style={{ fontSize: 13, color: theme.textMuted, marginTop: 6 }}>{t.service_name}</div>
                                        </div>
                                        <div style={{ textAlign: 'right' }}>
                                            <div style={{
                                                fontSize: 48, fontWeight: 800, color: theme.accent,
                                                lineHeight: 1,
                                            }}>
                                                {t.counter_number || '—'}
                                            </div>
                                            <div style={{ fontSize: 11, color: theme.textMuted, marginTop: 4 }}>VENTANILLA</div>
                                        </div>
                                    </div>

                                    <div style={{
                                        marginTop: 12, padding: '6px 10px', borderRadius: 6,
                                        background: t.status === 'called' ? 'rgba(59,130,246,0.12)' : 'rgba(99,102,241,0.12)',
                                        color: t.status === 'called' ? '#60A5FA' : '#818CF8',
                                        fontSize: 11, fontWeight: 600, display: 'inline-block',
                                    }}>
                                        {t.status === 'called' ? '📢 LLAMANDO' : '⚡ EN ATENCIÓN'}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* ── Right sidebar ── */}
                    <div style={{ background: theme.cardBg, borderLeft: '1px solid #1E2432', display: 'flex', flexDirection: 'column' }}>
                        {/* Stats */}
                        <div style={{ padding: '24px 20px', borderBottom: '1px solid #1E2432' }}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                <div style={{ textAlign: 'center', background: theme.bg, borderRadius: 12, padding: 16 }}>
                                    <div style={{ fontSize: 36, fontWeight: 800, color: theme.warning }}>{waitingCount}</div>
                                    <div style={{ fontSize: 10, color: theme.textMuted, textTransform: 'uppercase' }}>En espera</div>
                                </div>
                                <div style={{ textAlign: 'center', background: theme.bg, borderRadius: 12, padding: 16 }}>
                                    <div style={{ fontSize: 36, fontWeight: 800 }}>~{avgWaitMinutes}</div>
                                    <div style={{ fontSize: 10, color: theme.textMuted, textTransform: 'uppercase' }}>Min. espera</div>
                                </div>
                            </div>
                        </div>

                        {/* Recent history */}
                        <div style={{ padding: '16px 20px', flex: 1 }}>
                            <div style={{ fontSize: 11, fontWeight: 600, color: theme.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 12 }}>
                                Últimos atendidos
                            </div>
                            {recent.map((t, i) => (
                                <div key={i} style={{
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    padding: '10px 0', borderBottom: i < recent.length - 1 ? `1px solid #1E2432` : 'none',
                                }}>
                                    <span style={{ fontSize: 15, fontWeight: 600, fontVariantNumeric: 'tabular-nums', color: theme.textMuted }}>{t.display_number}</span>
                                    <span style={{ fontSize: 12, color: theme.textMuted }}>V{t.counter_number}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <style>{`
                    @keyframes pulse-border { 0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.2); } 50% { box-shadow: 0 0 0 8px rgba(59,130,246,0); } }
                    @keyframes flashIn { from { transform: translateX(-50%) scale(0.8); opacity: 0; } to { transform: translateX(-50%) scale(1); opacity: 1; } }
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                `}</style>
            </div>
        </>
    );
}
