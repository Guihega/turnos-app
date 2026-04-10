// resources/js/Pages/Public/Kiosk.jsx
import { useForm, Head } from '@inertiajs/react';
import { useState } from 'react';
import { T } from '@/Components/TurnosUI';
import useTenantBranding from '@/Hooks/useTenantBranding';

export default function Kiosk({ branch, services, waitingCount, avgWaitMinutes }) {
    const { branding, kiosk, tickets, logoUrl, tenantName, cssVars } = useTenantBranding();
    const [step, setStep] = useState('select');
    const [selected, setSelected] = useState(null);
    const [pressed, setPressed] = useState(null);
    const { data, setData, post, processing, errors } = useForm({ service_id: '', queue_id: '', customer_name: '', customer_phone: '' });

    const primaryColor = branding.primary_color || T.blue;
    const secondaryColor = branding.secondary_color || T.purple;
    const welcomeText = kiosk.welcome_text || 'Bienvenido';
    const showEstimatedWait = kiosk.show_estimated_wait ?? true;

    const selectService = (svc) => {
        setSelected(svc);
        setData({ ...data, service_id: svc.id, queue_id: svc.queue_id });
        setStep('confirm');
    };
    const submit = () => post(route('kiosk.store', branch.id));

    const inputStyle = {
        width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
        borderRadius: 12, padding: '14px 16px', fontSize: 16, outline: 'none', fontFamily: T.font, boxSizing: 'border-box',
    };

    // Branch closed
    if (!branch.is_open || !branch.accepts_walkins) {
        return (<>
            <Head title={`${branch.name} — Turno`} />
            <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100dvh', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', padding: '40px 24px', textAlign: 'center', ...cssVars }}>
                {logoUrl ? (
                    <img src={logoUrl} alt={tenantName} style={{ width: 56, height: 56, borderRadius: branding.logo_shape === 'circle' ? '50%' : 14, objectFit: 'cover', marginBottom: 20 }} />
                ) : (
                    <div style={{ width: 56, height: 56, borderRadius: 14, background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 22, color: '#fff', marginBottom: 20 }}>{(tenantName || 'O')[0]}</div>
                )}
                <h1 style={{ fontSize: 20, fontWeight: 800, marginBottom: 8 }}>{branch.name}</h1>
                <p style={{ fontSize: 14, color: T.textMuted, lineHeight: 1.5 }}>La sucursal no está disponible en este momento</p>
            </div>
        </>);
    }

    return (<>
        <Head title={`${branch.name} — Tomar turno`} />
        <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100dvh', display: 'flex', flexDirection: 'column', ...cssVars }}>

            {/* ── Header ── */}
            <div style={{
                padding: '12px 18px', borderBottom: `1px solid ${T.border}`,
                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                position: 'sticky', top: 0, background: T.bg, zIndex: 10,
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    {logoUrl ? (
                        <img src={logoUrl} alt={tenantName} style={{
                            width: 34, height: 34,
                            borderRadius: branding.logo_shape === 'circle' ? '50%' : branding.logo_shape === 'rounded' ? 8 : 4,
                            objectFit: 'cover',
                        }} />
                    ) : (
                        <div style={{ width: 34, height: 34, borderRadius: 8, background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 13, color: '#fff' }}>
                            {(tenantName || 'O')[0]}
                        </div>
                    )}
                    <div>
                        <div style={{ fontSize: 14, fontWeight: 800, lineHeight: 1.2 }}>{branch.name}</div>
                        <div style={{ fontSize: 10, color: T.textMuted }}>{welcomeText}</div>
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                    <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: 16, fontWeight: 900, color: T.amber, fontFamily: T.mono }}>{waitingCount}</div>
                        <div style={{ fontSize: 7, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em' }}>espera</div>
                    </div>
                    {showEstimatedWait && (
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: 16, fontWeight: 900, fontFamily: T.mono }}>~{avgWaitMinutes}</div>
                            <div style={{ fontSize: 7, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em' }}>min</div>
                        </div>
                    )}
                </div>
            </div>

            {/* ── Content ── */}
            <div style={{ flex: 1, padding: '18px 16px', maxWidth: 640, margin: '0 auto', width: '100%' }}>

                {/* ══ STEP 1: Select Service ══ */}
                {step === 'select' && (
                    <div style={{ animation: 'kioskFadeIn 0.3s ease both' }}>
                        <h2 style={{ fontSize: 19, fontWeight: 900, marginBottom: 4, letterSpacing: '-0.02em' }}>¿Qué servicio necesita?</h2>
                        <p style={{ fontSize: 13, color: T.textMuted, marginBottom: 18 }}>Seleccione una opción</p>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                            {services.map((svc) => (
                                <button key={svc.id}
                                    onClick={() => selectService(svc)}
                                    onTouchStart={() => setPressed(svc.id)}
                                    onTouchEnd={() => setPressed(null)}
                                    onMouseDown={() => setPressed(svc.id)}
                                    onMouseUp={() => setPressed(null)}
                                    onMouseLeave={() => setPressed(null)}
                                    style={{
                                        background: T.card,
                                        border: `1px solid ${T.border}`,
                                        borderLeft: `4px solid ${svc.color || primaryColor}`,
                                        borderRadius: 14, padding: '16px 18px', cursor: 'pointer', textAlign: 'left',
                                        transition: 'all 0.2s cubic-bezier(0.4,0,0.2,1)',
                                        color: T.text, fontFamily: T.font, display: 'flex', alignItems: 'center', gap: 14,
                                        transform: pressed === svc.id ? 'scale(0.98)' : 'scale(1)',
                                        boxShadow: pressed === svc.id ? 'none' : undefined,
                                    }}>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 2 }}>{svc.name}</div>
                                        {svc.description && <div style={{ fontSize: 11, color: T.textMuted, lineHeight: 1.4, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{svc.description}</div>}
                                    </div>
                                    {showEstimatedWait && svc.estimated_minutes && (
                                        <div style={{ textAlign: 'center', flexShrink: 0 }}>
                                            <div style={{ fontSize: 14, fontWeight: 800, fontFamily: T.mono, color: T.textMuted }}>~{svc.estimated_minutes}</div>
                                            <div style={{ fontSize: 7, color: T.textMuted, textTransform: 'uppercase' }}>min</div>
                                        </div>
                                    )}
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" style={{ opacity: 0.3, flexShrink: 0 }}><path d="m9 18 6-6-6-6"/></svg>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* ══ STEP 2: Confirm ══ */}
                {step === 'confirm' && selected && (
                    <div style={{ animation: 'kioskSlideIn 0.3s ease both' }}>
                        <button onClick={() => setStep('select')} style={{
                            background: 'none', border: 'none', color: primaryColor, cursor: 'pointer',
                            fontSize: 13, marginBottom: 18, fontFamily: T.font, fontWeight: 600, padding: 0,
                            display: 'flex', alignItems: 'center', gap: 4,
                        }}>
                            ← Cambiar servicio
                        </button>

                        {/* Selected service summary */}
                        <div style={{
                            background: T.card, borderRadius: 16, padding: '20px 18px', border: `1px solid ${T.border}`,
                            borderTop: `3px solid ${selected.color || primaryColor}`, marginBottom: 14,
                        }}>
                            <div style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 4, fontFamily: T.mono }}>Servicio seleccionado</div>
                            <div style={{ fontSize: 18, fontWeight: 900, marginBottom: 4 }}>{selected.name}</div>
                            <div style={{ fontSize: 12, color: T.textMuted, fontFamily: T.mono }}>
                                {selected.queue_name}
                                {showEstimatedWait && selected.estimated_minutes && <> · ~{selected.estimated_minutes} min</>}
                            </div>
                        </div>

                        {/* Optional data */}
                        <div style={{ background: T.card, borderRadius: 16, padding: '18px', border: `1px solid ${T.border}`, marginBottom: 14 }}>
                            <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 14 }}>Datos opcionales</div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                <div>
                                    <label style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 5, fontFamily: T.mono }}>Nombre</label>
                                    <input value={data.customer_name} onChange={e => setData('customer_name', e.target.value)}
                                        placeholder="Su nombre (opcional)" style={inputStyle}
                                        onFocus={e => { e.target.style.borderColor = primaryColor; e.target.style.boxShadow = `0 0 0 3px color-mix(in srgb, ${primaryColor} 15%, transparent)`; }}
                                        onBlur={e => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; }} />
                                </div>
                                <div>
                                    <label style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 5, fontFamily: T.mono }}>Teléfono</label>
                                    <input value={data.customer_phone} onChange={e => setData('customer_phone', e.target.value)}
                                        placeholder="Para notificarle (opcional)" type="tel" inputMode="tel" style={inputStyle}
                                        onFocus={e => { e.target.style.borderColor = primaryColor; e.target.style.boxShadow = `0 0 0 3px color-mix(in srgb, ${primaryColor} 15%, transparent)`; }}
                                        onBlur={e => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; }} />
                                </div>
                            </div>
                        </div>

                        {/* Errors */}
                        {Object.keys(errors).length > 0 && (
                            <div style={{
                                background: `color-mix(in srgb, ${T.red} 8%, transparent)`,
                                border: `1px solid color-mix(in srgb, ${T.red} 20%, transparent)`,
                                borderRadius: 10, padding: 14, marginBottom: 14,
                            }}>
                                {Object.values(errors).map((e, i) => <div key={i} style={{ fontSize: 13, color: T.red }}>{e}</div>)}
                            </div>
                        )}

                        {/* Submit */}
                        <button onClick={submit} disabled={processing}
                            onTouchStart={e => { if (!processing) e.currentTarget.style.transform = 'scale(0.97)'; }}
                            onTouchEnd={e => { e.currentTarget.style.transform = 'none'; }}
                            style={{
                                width: '100%', padding: 18,
                                background: `linear-gradient(135deg, ${selected.color || primaryColor}, color-mix(in srgb, ${selected.color || primaryColor} 75%, black))`,
                                color: '#fff', border: 'none', borderRadius: 14, fontSize: 17, fontWeight: 800,
                                cursor: processing ? 'wait' : 'pointer', opacity: processing ? 0.6 : 1,
                                transition: 'all 0.2s', fontFamily: T.font,
                                boxShadow: `0 4px 20px color-mix(in srgb, ${selected.color || primaryColor} 30%, transparent)`,
                                WebkitTapHighlightColor: 'transparent',
                            }}>
                            {processing ? 'Emitiendo turno...' : 'Tomar turno →'}
                        </button>
                    </div>
                )}
            </div>

            {/* Footer */}
            <div style={{ padding: '10px 18px', borderTop: `1px solid ${T.border}`, textAlign: 'center' }}>
                <span style={{ fontSize: 9, color: T.textMuted, letterSpacing: '0.06em' }}>Powered by Olinora</span>
            </div>

            <style>{`
                @keyframes kioskFadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes kioskSlideIn { from { opacity: 0; transform: translateX(12px); } to { opacity: 1; transform: translateX(0); } }
                * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
            `}</style>
        </div>
    </>);
}
