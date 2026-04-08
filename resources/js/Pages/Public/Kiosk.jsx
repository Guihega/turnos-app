// resources/js/Pages/Public/Kiosk.jsx
import { useForm, Head } from '@inertiajs/react';
import { useState } from 'react';
import { T } from '@/Components/TurnosUI';
import useTenantBranding from '@/Hooks/useTenantBranding';

export default function Kiosk({ branch, services, waitingCount, avgWaitMinutes }) {
    const { branding, kiosk, tickets, logoUrl, tenantName, cssVars } = useTenantBranding();
    const [step, setStep] = useState('select');
    const [selected, setSelected] = useState(null);
    const [hovered, setHovered] = useState(null);
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

    // Branch closed
    if (!branch.is_open || !branch.accepts_walkins) {
        return (<>
            <Head title={`${branch.name} — Turno`} />
            <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100dvh', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', padding: '40px 24px', textAlign: 'center', ...cssVars }}>
                {logoUrl ? (
                    <img src={logoUrl} alt={tenantName} style={{ width: 64, height: 64, borderRadius: branding.logo_shape === 'circle' ? '50%' : 14, objectFit: 'cover', marginBottom: 20 }} />
                ) : (
                    <div style={{ width: 64, height: 64, borderRadius: 16, background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 24, color: '#fff', marginBottom: 20 }}>{(tenantName || 'O')[0]}</div>
                )}
                <h1 style={{ fontSize: 22, fontWeight: 800, marginBottom: 8 }}>{branch.name}</h1>
                <p style={{ fontSize: 15, color: T.textMuted }}>La sucursal no está disponible en este momento</p>
            </div>
        </>);
    }

    return (<>
        <Head title={`${branch.name} — Tomar turno`} />
        <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100dvh', display: 'flex', flexDirection: 'column', ...cssVars }}>

            {/* ── Header (compact, mobile-friendly) ── */}
            <div style={{
                padding: '14px 20px', borderBottom: `1px solid ${T.border}`,
                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                position: 'sticky', top: 0, background: T.bg, zIndex: 10,
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    {logoUrl ? (
                        <img src={logoUrl} alt={tenantName} style={{
                            width: 36, height: 36,
                            borderRadius: branding.logo_shape === 'circle' ? '50%' : branding.logo_shape === 'rounded' ? 10 : 4,
                            objectFit: 'cover',
                        }} />
                    ) : (
                        <div style={{ width: 36, height: 36, borderRadius: 10, background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 14, color: '#fff' }}>
                            {(tenantName || 'O')[0]}
                        </div>
                    )}
                    <div>
                        <div style={{ fontSize: 15, fontWeight: 800, lineHeight: 1.2 }}>{branch.name}</div>
                        <div style={{ fontSize: 11, color: T.textMuted }}>{welcomeText}</div>
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 14, alignItems: 'center' }}>
                    <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: 18, fontWeight: 900, color: T.amber, fontFamily: T.mono }}>{waitingCount}</div>
                        <div style={{ fontSize: 7, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em' }}>espera</div>
                    </div>
                    {showEstimatedWait && (
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: 18, fontWeight: 900, fontFamily: T.mono }}>~{avgWaitMinutes}</div>
                            <div style={{ fontSize: 7, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em' }}>min</div>
                        </div>
                    )}
                </div>
            </div>

            {/* ── Content ── */}
            <div style={{ flex: 1, padding: '20px 16px', maxWidth: 640, margin: '0 auto', width: '100%' }}>

                {/* ══ STEP 1: Select Service ══ */}
                {step === 'select' && (
                    <div>
                        <h2 style={{ fontSize: 20, fontWeight: 900, marginBottom: 4, letterSpacing: '-0.02em' }}>¿Qué servicio necesita?</h2>
                        <p style={{ fontSize: 13, color: T.textMuted, marginBottom: 20 }}>Seleccione una opción</p>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                            {services.map((svc, i) => (
                                <button key={svc.id} onClick={() => selectService(svc)}
                                    onMouseEnter={() => setHovered(svc.id)} onMouseLeave={() => setHovered(null)}
                                    style={{
                                        background: T.card,
                                        border: `2px solid ${hovered === svc.id ? svc.color || primaryColor : T.border}`,
                                        borderRadius: 14, padding: '18px 20px', cursor: 'pointer', textAlign: 'left',
                                        transition: 'all 0.25s cubic-bezier(0.4,0,0.2,1)',
                                        color: T.text, fontFamily: T.font, display: 'flex', alignItems: 'center', gap: 16,
                                        borderLeftWidth: 4, borderLeftColor: svc.color || primaryColor,
                                    }}>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 3 }}>{svc.name}</div>
                                        {svc.description && <div style={{ fontSize: 12, color: T.textMuted, lineHeight: 1.4 }}>{svc.description}</div>}
                                    </div>
                                    {showEstimatedWait && (
                                        <div style={{ textAlign: 'center', flexShrink: 0 }}>
                                            <div style={{ fontSize: 16, fontWeight: 800, fontFamily: T.mono, color: T.textMuted }}>~{svc.estimated_minutes}</div>
                                            <div style={{ fontSize: 8, color: T.textMuted, textTransform: 'uppercase' }}>min</div>
                                        </div>
                                    )}
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2" strokeLinecap="round"><path d="m9 18 6-6-6-6"/></svg>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* ══ STEP 2: Confirm ══ */}
                {step === 'confirm' && selected && (
                    <div>
                        <button onClick={() => setStep('select')} style={{ background: 'none', border: 'none', color: primaryColor, cursor: 'pointer', fontSize: 13, marginBottom: 20, fontFamily: T.font, fontWeight: 600, padding: 0 }}>← Cambiar servicio</button>

                        {/* Selected service summary */}
                        <div style={{
                            background: T.card, borderRadius: 16, padding: '22px 20px', border: `1px solid ${T.border}`,
                            borderTop: `3px solid ${selected.color || primaryColor}`, marginBottom: 16,
                        }}>
                            <div style={{ fontSize: 11, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 4 }}>Servicio</div>
                            <div style={{ fontSize: 20, fontWeight: 900, marginBottom: 4 }}>{selected.name}</div>
                            <div style={{ fontSize: 12, color: T.textMuted, fontFamily: T.mono }}>
                                {selected.queue_name}
                                {showEstimatedWait && <> · ~{selected.estimated_minutes} min</>}
                            </div>
                        </div>

                        {/* Optional data */}
                        <div style={{ background: T.card, borderRadius: 16, padding: '22px 20px', border: `1px solid ${T.border}`, marginBottom: 16 }}>
                            <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 14 }}>Datos opcionales</div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                <div>
                                    <label style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 5 }}>Nombre</label>
                                    <input value={data.customer_name} onChange={e => setData('customer_name', e.target.value)} placeholder="Su nombre (opcional)"
                                        style={{ width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`, borderRadius: 10, padding: '14px 16px', fontSize: 16, outline: 'none', fontFamily: T.font, boxSizing: 'border-box' }} />
                                </div>
                                <div>
                                    <label style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 5 }}>Teléfono</label>
                                    <input value={data.customer_phone} onChange={e => setData('customer_phone', e.target.value)} placeholder="Para notificarle (opcional)" type="tel"
                                        style={{ width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`, borderRadius: 10, padding: '14px 16px', fontSize: 16, outline: 'none', fontFamily: T.font, boxSizing: 'border-box' }} />
                                </div>
                            </div>
                        </div>

                        {/* Errors */}
                        {Object.keys(errors).length > 0 && (
                            <div style={{ background: `color-mix(in srgb, ${T.red} 8%, transparent)`, border: `1px solid color-mix(in srgb, ${T.red} 20%, transparent)`, borderRadius: 10, padding: 14, marginBottom: 16 }}>
                                {Object.values(errors).map((e, i) => <div key={i} style={{ fontSize: 13, color: T.red }}>{e}</div>)}
                            </div>
                        )}

                        {/* Submit */}
                        <button onClick={submit} disabled={processing} style={{
                            width: '100%', padding: 18,
                            background: `linear-gradient(135deg, ${selected.color || primaryColor}, color-mix(in srgb, ${selected.color || primaryColor} 75%, black))`,
                            color: '#fff', border: 'none', borderRadius: 14, fontSize: 18, fontWeight: 800,
                            cursor: processing ? 'wait' : 'pointer', opacity: processing ? 0.6 : 1,
                            transition: 'all 0.3s', fontFamily: T.font,
                            boxShadow: `0 4px 20px color-mix(in srgb, ${selected.color || primaryColor} 30%, transparent)`,
                        }}>
                            {processing ? 'Emitiendo turno...' : 'Tomar turno →'}
                        </button>
                    </div>
                )}
            </div>

            {/* Footer */}
            <div style={{ padding: '12px 20px', borderTop: `1px solid ${T.border}`, textAlign: 'center' }}>
                <span style={{ fontSize: 10, color: T.textMuted, letterSpacing: '0.05em' }}>Powered by Olinora</span>
            </div>
        </div>
    </>);
}
