// resources/js/Pages/Public/Kiosk.jsx
import { useForm, Head } from '@inertiajs/react';
import { useState } from 'react';

const theme = {
    bg: '#050810', cardBg: '#0F1219', accent: '#3B82F6',
    success: '#10B981', warning: '#F59E0B',
    textPrimary: '#F0F4FC', textMuted: '#5C6478', border: '#1E2432',
};

export default function Kiosk({ branch, services, waitingCount, avgWaitMinutes }) {
    const [step, setStep] = useState('select'); // select | confirm | form
    const [selected, setSelected] = useState(null);
    const { data, setData, post, processing, errors } = useForm({
        service_id: '', queue_id: '', customer_name: '', customer_phone: '',
    });

    const selectService = (svc) => {
        setSelected(svc);
        setData({ ...data, service_id: svc.id, queue_id: svc.queue_id });
        setStep('confirm');
    };

    const submit = () => post(route('kiosk.store', branch.id));

    if (!branch.is_open || !branch.accepts_walkins) {
        return (
            <>
                <Head title={`Kiosco — ${branch.name}`} />
                <div style={{ fontFamily: "'DM Sans', sans-serif", background: theme.bg, color: theme.textPrimary, minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', padding: 40 }}>
                    <div style={{ fontSize: 64, marginBottom: 20 }}>🏥</div>
                    <h1 style={{ fontSize: 28, fontWeight: 700, marginBottom: 8 }}>{branch.name}</h1>
                    <p style={{ fontSize: 18, color: theme.textMuted }}>La sucursal no está disponible en este momento</p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Kiosco — ${branch.name}`} />
            <div style={{
                fontFamily: "'DM Sans', -apple-system, sans-serif",
                background: theme.bg, color: theme.textPrimary, minHeight: '100vh',
                display: 'flex', flexDirection: 'column',
            }}>
                {/* Header */}
                <div style={{ padding: '24px 32px', borderBottom: `1px solid ${theme.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                        <div style={{ width: 40, height: 40, borderRadius: 10, background: 'linear-gradient(135deg, #3B82F6, #8B5CF6)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800, fontSize: 16, color: '#fff' }}>T</div>
                        <div>
                            <div style={{ fontSize: 18, fontWeight: 700 }}>{branch.name}</div>
                            <div style={{ fontSize: 11, color: theme.textMuted }}>Toma tu turno</div>
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 20 }}>
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: 24, fontWeight: 700, color: theme.warning }}>{waitingCount}</div>
                            <div style={{ fontSize: 10, color: theme.textMuted }}>en espera</div>
                        </div>
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: 24, fontWeight: 700 }}>~{avgWaitMinutes}</div>
                            <div style={{ fontSize: 10, color: theme.textMuted }}>min. aprox.</div>
                        </div>
                    </div>
                </div>

                <div style={{ flex: 1, padding: '32px', maxWidth: 900, margin: '0 auto', width: '100%' }}>

                    {/* ── Step 1: Select Service ── */}
                    {step === 'select' && (
                        <>
                            <h2 style={{ fontSize: 22, fontWeight: 700, marginBottom: 8 }}>Seleccione su servicio</h2>
                            <p style={{ fontSize: 14, color: theme.textMuted, marginBottom: 28 }}>Toque el servicio que necesita</p>

                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: 16 }}>
                                {services.map(svc => (
                                    <button key={svc.id} onClick={() => selectService(svc)} style={{
                                        background: theme.cardBg, border: `2px solid ${theme.border}`, borderRadius: 16,
                                        padding: '28px 24px', cursor: 'pointer', textAlign: 'left',
                                        transition: 'all 0.2s', position: 'relative', overflow: 'hidden',
                                        color: theme.textPrimary, fontFamily: 'inherit',
                                    }}
                                    onMouseEnter={e => { e.currentTarget.style.borderColor = svc.color; e.currentTarget.style.transform = 'translateY(-2px)'; }}
                                    onMouseLeave={e => { e.currentTarget.style.borderColor = theme.border; e.currentTarget.style.transform = 'none'; }}>
                                        <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 4, background: svc.color }} />
                                        <div style={{ fontSize: 28, marginBottom: 12 }}>{svc.icon || '🏷'}</div>
                                        <div style={{ fontSize: 17, fontWeight: 700, marginBottom: 6 }}>{svc.name}</div>
                                        {svc.description && <div style={{ fontSize: 12, color: theme.textMuted, marginBottom: 8 }}>{svc.description}</div>}
                                        <div style={{ fontSize: 11, color: theme.textMuted }}>⏱ ~{svc.estimated_minutes} min · Cola: {svc.queue_prefix}</div>
                                    </button>
                                ))}
                            </div>
                        </>
                    )}

                    {/* ── Step 2: Confirm / Enter info ── */}
                    {step === 'confirm' && selected && (
                        <div style={{ maxWidth: 480, margin: '0 auto' }}>
                            <button onClick={() => setStep('select')} style={{ background: 'none', border: 'none', color: theme.accent, cursor: 'pointer', fontSize: 13, marginBottom: 24, fontFamily: 'inherit' }}>
                                ← Volver a servicios
                            </button>

                            <div style={{ background: theme.cardBg, borderRadius: 16, padding: 28, border: `1px solid ${theme.border}`, borderTop: `4px solid ${selected.color}`, marginBottom: 24 }}>
                                <div style={{ fontSize: 13, color: theme.textMuted, marginBottom: 4 }}>Servicio seleccionado</div>
                                <div style={{ fontSize: 22, fontWeight: 700, marginBottom: 4 }}>{selected.name}</div>
                                <div style={{ fontSize: 12, color: theme.textMuted }}>Cola: {selected.queue_name} · Tiempo estimado: ~{selected.estimated_minutes} min</div>
                            </div>

                            <div style={{ background: theme.cardBg, borderRadius: 16, padding: 28, border: `1px solid ${theme.border}`, marginBottom: 24 }}>
                                <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 16 }}>Datos opcionales</div>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                    <div>
                                        <label style={{ fontSize: 11, color: theme.textMuted, display: 'block', marginBottom: 4 }}>NOMBRE</label>
                                        <input value={data.customer_name} onChange={e => setData('customer_name', e.target.value)}
                                            placeholder="Su nombre (opcional)" style={{ width: '100%', background: theme.bg, color: theme.textPrimary, border: `1px solid ${theme.border}`, borderRadius: 10, padding: '14px 16px', fontSize: 15, outline: 'none', fontFamily: 'inherit' }} />
                                    </div>
                                    <div>
                                        <label style={{ fontSize: 11, color: theme.textMuted, display: 'block', marginBottom: 4 }}>TELÉFONO</label>
                                        <input value={data.customer_phone} onChange={e => setData('customer_phone', e.target.value)}
                                            placeholder="Para notificarle (opcional)" type="tel" style={{ width: '100%', background: theme.bg, color: theme.textPrimary, border: `1px solid ${theme.border}`, borderRadius: 10, padding: '14px 16px', fontSize: 15, outline: 'none', fontFamily: 'inherit' }} />
                                    </div>
                                </div>
                            </div>

                            {Object.keys(errors).length > 0 && (
                                <div style={{ background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)', borderRadius: 10, padding: 14, marginBottom: 16 }}>
                                    {Object.values(errors).map((e, i) => <div key={i} style={{ fontSize: 13, color: '#F87171' }}>{e}</div>)}
                                </div>
                            )}

                            <button onClick={submit} disabled={processing} style={{
                                width: '100%', padding: '18px', background: selected.color || theme.accent,
                                color: '#fff', border: 'none', borderRadius: 14, fontSize: 18, fontWeight: 700,
                                cursor: processing ? 'wait' : 'pointer', opacity: processing ? 0.7 : 1,
                                transition: 'all 0.2s', fontFamily: 'inherit',
                            }}>
                                {processing ? 'Emitiendo turno...' : '🎫 Tomar Turno'}
                            </button>
                        </div>
                    )}
                </div>

                <style>{`* { margin: 0; padding: 0; box-sizing: border-box; }`}</style>
            </div>
        </>
    );
}
