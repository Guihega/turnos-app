// resources/js/Pages/Public/Kiosk.jsx
import { useForm, Head } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import { T } from '@/Components/TurnosUI';
import useTenantBranding from '@/Hooks/useTenantBranding';

function QRDisplay({ url, size = 160 }) {
    const [qrUrl, setQrUrl] = useState('');

    useEffect(() => {
        setQrUrl(`https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(url)}&bgcolor=111520&color=E4E8F1&format=svg`);
    }, [url, size]);

    return (
        <div style={{ textAlign: 'center' }}>
            {qrUrl && (
                <img src={qrUrl} alt="QR Code" width={size} height={size}
                    style={{ borderRadius: 12, border: `2px solid ${T.border}`, background: T.card }}
                    onError={(e) => { e.target.style.display = 'none'; }} />
            )}
            <div style={{ fontSize: 10, color: T.textMuted, marginTop: 8, fontFamily: T.mono, wordBreak: 'break-all', maxWidth: size }}>
                {url}
            </div>
        </div>
    );
}

export default function Kiosk({ branch, services, waitingCount, avgWaitMinutes }) {
    const { branding, kiosk, tickets, logoUrl, tenantName, cssVars } = useTenantBranding();
    const [step, setStep] = useState('select');
    const [selected, setSelected] = useState(null);
    const [hovered, setHovered] = useState(null);
    const [showQR, setShowQR] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ service_id: '', queue_id: '', customer_name: '', customer_phone: '' });
    const kioskUrl = typeof window !== 'undefined' ? window.location.href : '';

    // Tenant branding colors (fallback to theme defaults)
    const primaryColor = branding.primary_color || T.blue;
    const secondaryColor = branding.secondary_color || T.purple;
    const welcomeText = kiosk.welcome_text || 'Toma tu turno';
    const showPriority = kiosk.show_priority_option ?? false;
    const showEstimatedWait = kiosk.show_estimated_wait ?? true;

    const selectService = (svc) => { setSelected(svc); setData({ ...data, service_id: svc.id, queue_id: svc.queue_id }); setStep('confirm'); };
    const submit = () => post(route('kiosk.store', branch.id));

    if (!branch.is_open || !branch.accepts_walkins) {
        return (<>
            <Head title={`Kiosco — ${branch.name}`} />
            <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', padding: 40, ...cssVars }}>
                {logoUrl ? (
                    <img src={logoUrl} alt={tenantName} style={{ width: 56, height: 56, borderRadius: branding.logo_shape === 'circle' ? '50%' : branding.logo_shape === 'rounded' ? 14 : 4, objectFit: 'cover', marginBottom: 20 }} />
                ) : (
                    <div style={{ fontSize: 56, marginBottom: 20, opacity: 0.4 }}>⬡</div>
                )}
                <h1 style={{ fontSize: 24, fontWeight: 800 }}>{branch.name}</h1>
                <p style={{ fontSize: 15, color: T.textMuted, marginTop: 8 }}>La sucursal no está disponible</p>
            </div>
        </>);
    }

    return (<>
        <Head title={`Kiosco — ${branch.name}`} />
        <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100vh', display: 'flex', flexDirection: 'column', ...cssVars }}>
            {/* Header */}
            <div style={{ padding: '16px 24px', borderBottom: `1px solid ${T.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    {logoUrl ? (
                        <img src={logoUrl} alt={tenantName} style={{
                            width: 40, height: 40,
                            borderRadius: branding.logo_shape === 'circle' ? '50%' : branding.logo_shape === 'rounded' ? 12 : 4,
                            objectFit: 'cover',
                        }} />
                    ) : (
                        <div style={{ width: 40, height: 40, borderRadius: 12, background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 16, color: '#fff' }}>
                            {(tenantName || 'O')[0]}
                        </div>
                    )}
                    <div>
                        <div style={{ fontSize: 16, fontWeight: 800 }}>{branch.name}</div>
                        <div style={{ fontSize: 11, color: T.textMuted }}>{welcomeText}</div>
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
                    <div style={{ textAlign: 'center' }}>
                        <div style={{ fontSize: 20, fontWeight: 900, color: T.amber, fontFamily: T.mono }}>{waitingCount}</div>
                        <div style={{ fontSize: 8, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em' }}>en espera</div>
                    </div>
                    {showEstimatedWait && (
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ fontSize: 20, fontWeight: 900, fontFamily: T.mono }}>~{avgWaitMinutes}</div>
                            <div style={{ fontSize: 8, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em' }}>min. aprox.</div>
                        </div>
                    )}
                    <button onClick={() => setShowQR(!showQR)} style={{
                        background: showQR ? `color-mix(in srgb, ${primaryColor} 15%, transparent)` : 'transparent',
                        border: `1px solid ${T.border}`, borderRadius: 8, padding: '6px 12px', cursor: 'pointer',
                        color: showQR ? primaryColor : T.textMuted, fontSize: 11, fontFamily: T.font, fontWeight: 600, transition: 'all 0.2s',
                    }}>
                        ⬡ QR
                    </button>
                </div>
            </div>

            {showQR && (
                <div style={{ background: T.card, borderBottom: `1px solid ${T.border}`, padding: '20px 24px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 24, animation: 'tFadeUp 0.3s ease' }}>
                    <QRDisplay url={kioskUrl} size={140} />
                    <div>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 6 }}>Escanea para tomar turno</div>
                        <div style={{ fontSize: 12, color: T.textMuted, lineHeight: 1.5, maxWidth: 240 }}>
                            Los clientes pueden escanear este código QR con su celular para seleccionar su servicio y tomar turno automáticamente.
                        </div>
                        <div style={{ fontSize: 10, color: T.textMuted, marginTop: 8, fontFamily: T.mono, background: T.surface, padding: '6px 10px', borderRadius: 6, display: 'inline-block' }}>
                            {branch.code}
                        </div>
                    </div>
                </div>
            )}

            <div style={{ flex: 1, padding: 24, maxWidth: 880, margin: '0 auto', width: '100%' }}>
                {step === 'select' && (
                    <div className="t-fade-up">
                        <h2 style={{ fontSize: 24, fontWeight: 900, marginBottom: 4, letterSpacing: '-0.02em' }}>Seleccione su servicio</h2>
                        <p style={{ fontSize: 14, color: T.textMuted, marginBottom: 28 }}>Toque el servicio que necesita</p>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 14 }}>
                            {services.map((svc, i) => (
                                <button key={svc.id} onClick={() => selectService(svc)}
                                    onMouseEnter={() => setHovered(svc.id)} onMouseLeave={() => setHovered(null)}
                                    className={`t-fade-up t-stagger-${Math.min(i + 1, 8)}`}
                                    style={{
                                        background: T.card, border: `2px solid ${hovered === svc.id ? svc.color : T.border}`,
                                        borderRadius: T.radius, padding: '24px 20px', cursor: 'pointer', textAlign: 'left',
                                        transition: 'all 0.3s cubic-bezier(0.4,0,0.2,1)', position: 'relative', overflow: 'hidden',
                                        color: T.text, fontFamily: T.font,
                                        transform: hovered === svc.id ? 'translateY(-4px)' : 'none',
                                    }}>
                                    <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 3, background: svc.color }} />
                                    <div style={{ fontSize: 28, marginBottom: 14, opacity: hovered === svc.id ? 1 : 0.6, transition: 'opacity 0.3s' }}>⬡</div>
                                    <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 6 }}>{svc.name}</div>
                                    {svc.description && <div style={{ fontSize: 12, color: T.textMuted, marginBottom: 10, lineHeight: 1.4 }}>{svc.description}</div>}
                                    <div style={{ fontSize: 11, color: T.textMuted, fontFamily: T.mono }}>
                                        {showEstimatedWait && <>⏱ ~{svc.estimated_minutes}min · </>}Cola: {svc.queue_prefix}
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {step === 'confirm' && selected && (
                    <div className="t-fade-up" style={{ maxWidth: 460, margin: '0 auto' }}>
                        <button onClick={() => setStep('select')} style={{ background: 'none', border: 'none', color: primaryColor, cursor: 'pointer', fontSize: 13, marginBottom: 24, fontFamily: T.font, fontWeight: 600 }}>← Volver</button>
                        <div style={{ background: T.card, borderRadius: T.radius, padding: 28, border: `1px solid ${T.border}`, borderTop: `3px solid ${selected.color}`, marginBottom: 20 }}>
                            <div style={{ fontSize: 12, color: T.textMuted, marginBottom: 4, textTransform: 'uppercase', letterSpacing: '0.08em' }}>Servicio seleccionado</div>
                            <div style={{ fontSize: 22, fontWeight: 900, marginBottom: 4 }}>{selected.name}</div>
                            <div style={{ fontSize: 12, color: T.textMuted, fontFamily: T.mono }}>
                                Cola: {selected.queue_name}
                                {showEstimatedWait && <> · ~{selected.estimated_minutes}min</>}
                            </div>
                        </div>
                        <div style={{ background: T.card, borderRadius: T.radius, padding: 28, border: `1px solid ${T.border}`, marginBottom: 20 }}>
                            <div style={{ fontSize: 14, fontWeight: 700, marginBottom: 16 }}>Datos opcionales</div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                <div>
                                    <label style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 5 }}>Nombre</label>
                                    <input value={data.customer_name} onChange={e => setData('customer_name', e.target.value)} placeholder="Su nombre (opcional)"
                                        style={{ width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`, borderRadius: 10, padding: '14px 16px', fontSize: 15, outline: 'none', fontFamily: T.font }} />
                                </div>
                                <div>
                                    <label style={{ fontSize: 10, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 5 }}>Teléfono</label>
                                    <input value={data.customer_phone} onChange={e => setData('customer_phone', e.target.value)} placeholder="Para notificarle (opcional)" type="tel"
                                        style={{ width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`, borderRadius: 10, padding: '14px 16px', fontSize: 15, outline: 'none', fontFamily: T.font }} />
                                </div>
                            </div>
                        </div>
                        {Object.keys(errors).length > 0 && (
                            <div style={{ background: `color-mix(in srgb, ${T.red} 8%, transparent)`, border: `1px solid color-mix(in srgb, ${T.red} 20%, transparent)`, borderRadius: 10, padding: 14, marginBottom: 16 }}>
                                {Object.values(errors).map((e, i) => <div key={i} style={{ fontSize: 13, color: T.red }}>{e}</div>)}
                            </div>
                        )}
                        <button onClick={submit} disabled={processing} style={{
                            width: '100%', padding: 18, background: `linear-gradient(135deg, ${selected.color}, color-mix(in srgb, ${selected.color} 80%, black))`,
                            color: '#fff', border: 'none', borderRadius: 14, fontSize: 18, fontWeight: 800,
                            cursor: processing ? 'wait' : 'pointer', opacity: processing ? 0.6 : 1,
                            transition: 'all 0.3s', fontFamily: T.font,
                        }}>
                            {processing ? 'Emitiendo...' : '⬡ Tomar Turno'}
                        </button>
                    </div>
                )}
            </div>
        </div>
    </>);
}
