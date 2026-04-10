import { useState, useRef } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

/* ═══════════════════════════════════════════════════════════════
   OLINORA — Tenant Customization Panel
   Aesthetic: Refined Industrial (matches existing Olinora dark UI)
   Fonts: Outfit (display) + JetBrains Mono (data/mono)
   ═══════════════════════════════════════════════════════════════ */

// ── Theme variable shorthand ────────────────────────────────────
const V = (n) => `var(${n})`;

// ── Tab definitions ─────────────────────────────────────────────
const TABS = [
    { id: 'branding', label: 'Marca', desc: 'Logo, colores e identidad visual',
      icon: <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg> },
    { id: 'display', label: 'Pantalla', desc: 'Configuración de la pantalla pública',
      icon: <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg> },
    { id: 'kiosk', label: 'Kiosco', desc: 'Experiencia de autoservicio',
      icon: <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><line x1="12" x2="12.01" y1="18" y2="18"/></svg> },
    { id: 'tickets', label: 'Turnos', desc: 'Prefijos, numeración y tiempos',
      icon: <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg> },
    { id: 'security', label: 'Seguridad', desc: 'Límites, protección anti-bots y control de acceso',
      icon: <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg> },
];

const SOUNDS = [
    { value: 'chime', label: 'Chime' },
    { value: 'bell', label: 'Campana' },
    { value: 'ding', label: 'Ding' },
    { value: 'none', label: 'Sin sonido' },
];

const SHAPES = [
    { value: 'rounded', label: 'Redondeado' },
    { value: 'square', label: 'Cuadrado' },
    { value: 'circle', label: 'Circular' },
];

// ── Reusable Components ─────────────────────────────────────────

function Toggle({ value, onChange, label, hint }) {
    return (
        <div style={{
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            padding: '14px 0',
            borderBottom: `1px solid color-mix(in srgb, ${V('--t-border')} 50%, transparent)`,
        }}>
            <div>
                <div style={{ fontSize: 13, fontWeight: 600, color: V('--t-text') }}>{label}</div>
                {hint && <div style={{ fontSize: 11, color: V('--t-text-muted'), marginTop: 2 }}>{hint}</div>}
            </div>
            <button type="button" onClick={() => onChange(!value)} aria-pressed={value} style={{
                width: 44, height: 24, borderRadius: 12, border: 'none', cursor: 'pointer',
                background: value ? V('--t-blue') : `color-mix(in srgb, ${V('--t-text-muted')} 25%, transparent)`,
                position: 'relative', transition: 'background 0.25s cubic-bezier(.4,0,.2,1)', flexShrink: 0,
            }}>
                <span style={{
                    position: 'absolute', top: 3, width: 18, height: 18, borderRadius: '50%', background: '#fff',
                    left: value ? 23 : 3, transition: 'left 0.25s cubic-bezier(.4,0,.2,1)',
                    boxShadow: '0 1px 3px rgba(0,0,0,0.3)',
                }} />
            </button>
        </div>
    );
}

function ColorField({ label, value, onChange }) {
    return (
        <div style={{ marginBottom: 20 }}>
            <label style={{
                display: 'block', fontSize: 10, fontWeight: 700, color: V('--t-text-muted'),
                textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8,
                fontFamily: "'JetBrains Mono', monospace",
            }}>{label}</label>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <div style={{ position: 'relative', width: 44, height: 44, flexShrink: 0 }}>
                    <div style={{
                        width: 44, height: 44, borderRadius: 10, background: value,
                        border: `2px solid color-mix(in srgb, ${V('--t-border')} 80%, transparent)`,
                        transition: 'border-color 0.2s, box-shadow 0.2s',
                        boxShadow: `0 0 0 0 ${value}00`,
                    }} />
                    <input type="color" value={value} onChange={(e) => onChange(e.target.value)} style={{
                        position: 'absolute', inset: 0, opacity: 0, cursor: 'pointer', width: '100%', height: '100%',
                    }} />
                </div>
                <input type="text" value={value} onChange={(e) => onChange(e.target.value)} maxLength={7} style={{
                    flex: 1, padding: '10px 14px',
                    background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
                    color: V('--t-text'), fontSize: 14, fontFamily: "'JetBrains Mono', monospace",
                    fontWeight: 600, outline: 'none', transition: 'border-color 0.2s',
                    letterSpacing: '0.05em',
                }} />
            </div>
        </div>
    );
}

function Field({ label, hint, error, children }) {
    return (
        <div style={{ marginBottom: 20 }}>
            <label style={{
                display: 'block', fontSize: 10, fontWeight: 700, color: V('--t-text-muted'),
                textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8,
                fontFamily: "'JetBrains Mono', monospace",
            }}>{label}</label>
            {children}
            {hint && <div style={{ fontSize: 11, color: V('--t-text-muted'), marginTop: 6 }}>{hint}</div>}
            {error && <div style={{ fontSize: 11, color: V('--t-red'), marginTop: 6 }}>{error}</div>}
        </div>
    );
}

function InputText({ value, onChange, placeholder, mono, ...props }) {
    return (
        <input type="text" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} {...props} style={{
            width: '100%', padding: '10px 14px',
            background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
            color: V('--t-text'), fontSize: 14, outline: 'none', transition: 'border-color 0.2s',
            fontFamily: mono ? "'JetBrains Mono', monospace" : "'Outfit', sans-serif",
            fontWeight: mono ? 700 : 400,
            ...(props.style || {}),
        }} />
    );
}

function InputNumber({ value, onChange, min, max }) {
    return (
        <input type="number" value={value} onChange={(e) => onChange(parseInt(e.target.value) || min || 0)}
            min={min} max={max} style={{
            width: 120, padding: '10px 14px',
            background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
            color: V('--t-text'), fontSize: 14, fontFamily: "'JetBrains Mono', monospace", fontWeight: 700,
            outline: 'none', transition: 'border-color 0.2s',
        }} />
    );
}

function Select({ value, onChange, options }) {
    return (
        <select value={value} onChange={(e) => onChange(e.target.value)} style={{
            width: '100%', padding: '10px 14px',
            background: V('--t-surface'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
            color: V('--t-text'), fontSize: 14, fontFamily: "'Outfit', sans-serif",
            outline: 'none', cursor: 'pointer', transition: 'border-color 0.2s',
            appearance: 'none',
            backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7280' viewBox='0 0 20 20'%3E%3Cpath d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3E%3C/svg%3E")`,
            backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center',
        }}>
            {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
    );
}

function SaveButton({ processing, onClick, label = 'Guardar cambios' }) {
    return (
        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 28, paddingTop: 20,
            borderTop: `1px solid ${V('--t-border')}` }}>
            <button type="button" onClick={onClick} disabled={processing} style={{
                padding: '11px 28px', borderRadius: 10, border: 'none', cursor: processing ? 'wait' : 'pointer',
                background: V('--t-blue'), color: '#fff', fontSize: 13, fontWeight: 700,
                fontFamily: "'Outfit', sans-serif", transition: 'all 0.2s',
                opacity: processing ? 0.6 : 1,
                boxShadow: `0 2px 12px color-mix(in srgb, ${V('--t-blue')} 30%, transparent)`,
            }}
            onMouseEnter={e => { if (!processing) e.currentTarget.style.transform = 'translateY(-1px)'; }}
            onMouseLeave={e => { e.currentTarget.style.transform = 'none'; }}>
                {processing ? '⏳ Guardando...' : label}
            </button>
        </div>
    );
}

function Divider() {
    return <div style={{ height: 1, background: V('--t-border'), margin: '4px 0', opacity: 0.5 }} />;
}

// ── Live Preview ────────────────────────────────────────────────

function LivePreview({ settings, logoUrl, tenantName }) {
    const b = settings.branding || {};
    const d = settings.display || {};
    const k = settings.kiosk || {};
    const t = settings.tickets || {};

    const isDark = b.dark_mode_default !== false;
    const primary = b.primary_color || '#3B82F6';
    const secondary = b.secondary_color || '#8B5CF6';
    const accent = b.accent_color || '#10B981';
    const logoRadius = b.logo_shape === 'circle' ? '50%' : b.logo_shape === 'rounded' ? 6 : 2;

    // Preview palette (independent from page theme)
    const P = {
        bg:      isDark ? '#080a10' : '#f0f2f5',
        card:    isDark ? '#10131c' : '#ffffff',
        text:    isDark ? '#dfe3ea' : '#111827',
        muted:   isDark ? '#4e5564' : '#9ca3af',
        border:  isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.09)',
        surface: isDark ? 'rgba(255,255,255,0.025)' : 'rgba(0,0,0,0.02)',
    };

    const SectionLabel = ({ children }) => (
        <div style={{
            fontSize: 9, fontWeight: 700, color: V('--t-text-muted'), textTransform: 'uppercase',
            letterSpacing: '0.1em', fontFamily: "'JetBrains Mono', monospace",
            padding: '10px 14px 6px', background: V('--t-surface'),
            borderBottom: `1px solid ${V('--t-border')}`,
        }}>{children}</div>
    );

    return (
        <div style={{ position: 'sticky', top: 80 }}>
            {/* Outer shell */}
            <div style={{
                background: V('--t-card'), border: `1px solid ${V('--t-border')}`, borderRadius: 16,
                overflow: 'hidden', boxShadow: '0 4px 24px rgba(0,0,0,0.12)',
            }}>
                {/* Preview chrome bar */}
                <div style={{
                    padding: '10px 16px', display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    background: V('--t-surface'), borderBottom: `1px solid ${V('--t-border')}`,
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                        <div style={{ display: 'flex', gap: 5 }}>
                            <span style={{ width: 8, height: 8, borderRadius: '50%', background: '#ff5f57' }} />
                            <span style={{ width: 8, height: 8, borderRadius: '50%', background: '#febc2e' }} />
                            <span style={{ width: 8, height: 8, borderRadius: '50%', background: '#28c840' }} />
                        </div>
                    </div>
                    <span style={{
                        fontSize: 9, fontWeight: 700, color: V('--t-text-muted'), textTransform: 'uppercase',
                        letterSpacing: '0.12em', fontFamily: "'JetBrains Mono', monospace",
                    }}>Vista previa</span>
                    <div style={{
                        fontSize: 8, padding: '2px 7px', borderRadius: 4,
                        background: `${accent}18`, color: accent, fontWeight: 800,
                        letterSpacing: '0.06em', textTransform: 'uppercase',
                    }}>Live</div>
                </div>

                {/* ── PANTALLA SECTION ── */}
                <SectionLabel>Pantalla pública</SectionLabel>
                <div style={{
                    padding: 14, background: P.bg,
                    transition: 'background 0.3s',
                }}>
                    {/* Screen header */}
                    <div style={{
                        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                        marginBottom: 10, padding: '0 2px',
                    }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
                            {logoUrl ? (
                                <img src={logoUrl} alt="" style={{ width: 22, height: 22, borderRadius: logoRadius, objectFit: 'cover' }} />
                            ) : (
                                <div style={{
                                    width: 22, height: 22, borderRadius: logoRadius,
                                    background: `linear-gradient(135deg, ${primary}, ${secondary})`,
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    fontSize: 9, fontWeight: 900, color: '#fff',
                                }}>{(tenantName || 'O')[0]}</div>
                            )}
                            <div>
                                <div style={{ fontSize: 10, fontWeight: 700, color: P.text, lineHeight: 1.1 }}>{tenantName}</div>
                                <div style={{ fontSize: 7, color: P.muted }}>Sede Centro</div>
                            </div>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                            <span style={{ width: 5, height: 5, borderRadius: '50%', background: accent, boxShadow: `0 0 6px ${accent}` }} />
                            <span style={{ fontSize: 7, color: P.muted, fontWeight: 600 }}>En vivo</span>
                        </div>
                    </div>

                    {/* Announcement */}
                    {d.announcement_text && (
                        <div style={{
                            padding: '4px 8px', borderRadius: 5, marginBottom: 8, textAlign: 'center',
                            background: isDark ? `${primary}15` : `${primary}0a`,
                            border: `1px solid ${isDark ? `${primary}30` : `${primary}18`}`,
                            fontSize: 8, color: primary, fontWeight: 600,
                        }}>📢 {d.announcement_text}</div>
                    )}

                    {/* Current ticket card */}
                    <div style={{
                        background: `linear-gradient(135deg, ${primary}, color-mix(in srgb, ${primary} 75%, ${secondary}))`,
                        borderRadius: 10, padding: '14px 16px', textAlign: 'center', marginBottom: 8,
                        boxShadow: `0 6px 24px ${primary}35`,
                        position: 'relative', overflow: 'hidden',
                    }}>
                        {/* Subtle pattern overlay */}
                        <div style={{
                            position: 'absolute', inset: 0, opacity: 0.06,
                            backgroundImage: 'radial-gradient(circle at 20% 80%, #fff 1px, transparent 1px), radial-gradient(circle at 80% 20%, #fff 1px, transparent 1px)',
                            backgroundSize: '20px 20px',
                        }} />
                        <div style={{ position: 'relative' }}>
                            <div style={{ fontSize: 7, color: 'rgba(255,255,255,0.55)', textTransform: 'uppercase',
                                letterSpacing: '0.18em', fontWeight: 700 }}>Turno actual</div>
                            <div style={{
                                fontSize: 32, fontWeight: 900, color: '#fff', fontFamily: "'JetBrains Mono', monospace",
                                lineHeight: 1.15, letterSpacing: '-0.03em', margin: '2px 0',
                            }}>{t.prefix || 'A'}-042</div>
                            {d.show_queue_name !== false && (
                                <div style={{ fontSize: 8, color: 'rgba(255,255,255,0.5)', fontWeight: 600 }}>Ventanilla 3</div>
                            )}
                        </div>
                    </div>

                    {/* Stats row */}
                    <div style={{
                        display: 'grid', gridTemplateColumns: d.show_wait_time !== false ? '1fr 1fr' : '1fr',
                        gap: 6, marginBottom: 8,
                    }}>
                        <div style={{
                            background: P.card, borderRadius: 7, padding: '8px 10px', textAlign: 'center',
                            border: `1px solid ${P.border}`,
                        }}>
                            <div style={{ fontSize: 18, fontWeight: 900, color: '#f59e0b', fontFamily: "'JetBrains Mono', monospace" }}>5</div>
                            <div style={{ fontSize: 7, color: P.muted, textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 600 }}>En espera</div>
                        </div>
                        {d.show_wait_time !== false && (
                            <div style={{
                                background: P.card, borderRadius: 7, padding: '8px 10px', textAlign: 'center',
                                border: `1px solid ${P.border}`,
                            }}>
                                <div style={{ fontSize: 18, fontWeight: 900, color: P.text, fontFamily: "'JetBrains Mono', monospace" }}>~6</div>
                                <div style={{ fontSize: 7, color: P.muted, textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 600 }}>Min.</div>
                            </div>
                        )}
                    </div>

                    {/* Recent list */}
                    <div style={{
                        borderRadius: 7, overflow: 'hidden',
                        border: `1px solid ${P.border}`, background: P.card,
                    }}>
                        <div style={{
                            fontSize: 7, fontWeight: 700, color: P.muted, textTransform: 'uppercase',
                            letterSpacing: '0.1em', padding: '5px 10px', borderBottom: `1px solid ${P.border}`,
                        }}>Últimos atendidos</div>
                        {Array.from({ length: Math.min(d.show_recent_count || 3, 3) }).map((_, i) => (
                            <div key={i} style={{
                                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                padding: '5px 10px', fontSize: 9, color: P.muted,
                                background: i % 2 === 0 ? P.surface : 'transparent',
                                borderBottom: i < 2 ? `1px solid ${P.border}` : 'none',
                            }}>
                                <span style={{ fontFamily: "'JetBrains Mono', monospace", fontWeight: 700, color: isDark ? '#8892a4' : '#6b7280' }}>
                                    {t.prefix || 'A'}-{String(41 - i).padStart(3, '0')}
                                </span>
                                {d.show_queue_name !== false && <span style={{ fontSize: 8 }}>V{i + 1}</span>}
                                {d.show_wait_time !== false && <span style={{ fontSize: 8 }}>{3 + i * 2}m</span>}
                            </div>
                        ))}
                    </div>
                </div>

                {/* ── KIOSCO SECTION ── */}
                <SectionLabel>Kiosco</SectionLabel>
                <div style={{
                    padding: '16px 14px 18px', background: P.bg,
                    transition: 'background 0.3s',
                }}>
                    <div style={{
                        background: P.card, borderRadius: 10, padding: '16px 14px',
                        border: `1px solid ${P.border}`, textAlign: 'center',
                    }}>
                        {/* Mini kiosk header */}
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6, marginBottom: 10 }}>
                            {logoUrl ? (
                                <img src={logoUrl} alt="" style={{ width: 18, height: 18, borderRadius: logoRadius, objectFit: 'cover' }} />
                            ) : (
                                <div style={{
                                    width: 18, height: 18, borderRadius: logoRadius,
                                    background: `linear-gradient(135deg, ${primary}, ${secondary})`,
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    fontSize: 8, fontWeight: 900, color: '#fff',
                                }}>{(tenantName || 'O')[0]}</div>
                            )}
                            <span style={{ fontSize: 10, fontWeight: 700, color: P.text }}>{tenantName}</span>
                        </div>

                        <div style={{ fontSize: 15, fontWeight: 800, color: P.text, marginBottom: 10, letterSpacing: '-0.01em' }}>
                            {k.welcome_text || 'Bienvenido'}
                        </div>

                        {/* Service chips */}
                        <div style={{ display: 'flex', gap: 4, justifyContent: 'center', marginBottom: 10, flexWrap: 'wrap' }}>
                            {['General', 'Urgencias', 'Farmacia'].map((s, i) => (
                                <div key={s} style={{
                                    fontSize: 7, padding: '3px 8px', borderRadius: 5,
                                    border: `1px solid ${P.border}`, color: P.muted, fontWeight: 600,
                                    background: i === 0 ? `${primary}12` : 'transparent',
                                    ...(i === 0 ? { color: primary, borderColor: `${primary}30` } : {}),
                                }}>{s}</div>
                            ))}
                        </div>

                        <div style={{
                            display: 'inline-block', padding: '7px 24px',
                            background: `linear-gradient(135deg, ${primary}, color-mix(in srgb, ${primary} 80%, ${secondary}))`,
                            color: '#fff', borderRadius: 8, fontSize: 10, fontWeight: 800,
                            boxShadow: `0 3px 12px ${primary}30`,
                        }}>⬡ Tomar turno</div>

                        {k.show_estimated_wait !== false && (
                            <div style={{ fontSize: 8, color: P.muted, marginTop: 8 }}>
                                Tiempo estimado: ~12 min
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

// ── Main Component ──────────────────────────────────────────────

export default function TenantSettings({ tenant, settings: initialSettings, logoUrl: initialLogoUrl }) {
    const { flash } = usePage().props;
    const [activeTab, setActiveTab] = useState('branding');
    const [localSettings, setLocalSettings] = useState(initialSettings);
    const [logoPreviewUrl, setLogoPreviewUrl] = useState(initialLogoUrl);
    const fileRef = useRef(null);

    // Forms
    const brandingForm = useForm({
        primary_color: initialSettings.branding?.primary_color || '#3B82F6',
        secondary_color: initialSettings.branding?.secondary_color || '#8B5CF6',
        accent_color: initialSettings.branding?.accent_color || '#10B981',
        logo_shape: initialSettings.branding?.logo_shape || 'rounded',
        dark_mode_default: initialSettings.branding?.dark_mode_default ?? true,
    });

    const displayForm = useForm({
        show_queue_name: initialSettings.display?.show_queue_name ?? true,
        show_service_name: initialSettings.display?.show_service_name ?? true,
        show_wait_time: initialSettings.display?.show_wait_time ?? true,
        show_recent_count: initialSettings.display?.show_recent_count || 5,
        announcement_text: initialSettings.display?.announcement_text || '',
        call_sound: initialSettings.display?.call_sound || 'chime',
    });

    const kioskForm = useForm({
        welcome_text: initialSettings.kiosk?.welcome_text || 'Bienvenido',
        show_priority_option: initialSettings.kiosk?.show_priority_option ?? false,
        show_estimated_wait: initialSettings.kiosk?.show_estimated_wait ?? true,
        print_ticket: initialSettings.kiosk?.print_ticket ?? false,
    });

    const ticketsForm = useForm({
        prefix: initialSettings.tickets?.prefix || 'A',
        daily_reset: initialSettings.tickets?.daily_reset ?? true,
        auto_close_minutes: initialSettings.tickets?.auto_close_minutes || 120,
        no_show_minutes: initialSettings.tickets?.no_show_minutes || 15,
    });

    const securityForm = useForm({
        max_tickets_per_hour: initialSettings.security?.max_tickets_per_hour || 60,
        max_tickets_per_ip_minute: initialSettings.security?.max_tickets_per_ip_minute || 3,
        max_concurrent_waiting: initialSettings.security?.max_concurrent_waiting || 50,
        max_daily_tickets: initialSettings.security?.max_daily_tickets || 500,
        bot_protection: initialSettings.security?.bot_protection ?? true,
        require_customer_name: initialSettings.security?.require_customer_name ?? false,
    });

    // Live preview sync
    const sync = (section, form, field, value) => {
        form.setData(field, value);
        setLocalSettings(prev => ({ ...prev, [section]: { ...prev[section], [field]: value } }));
    };

    const sb = (f, v) => sync('branding', brandingForm, f, v);
    const sd = (f, v) => sync('display', displayForm, f, v);
    const sk = (f, v) => sync('kiosk', kioskForm, f, v);
    const st = (f, v) => sync('tickets', ticketsForm, f, v);
    const ss = (f, v) => sync('security', securityForm, f, v);

    // Submits
    const submitBranding = () => brandingForm.put(route('admin.settings.branding'), { preserveScroll: true });
    const submitDisplay = () => displayForm.put(route('admin.settings.display'), { preserveScroll: true });
    const submitKiosk = () => kioskForm.put(route('admin.settings.kiosk'), { preserveScroll: true });
    const submitTickets = () => ticketsForm.put(route('admin.settings.tickets'), { preserveScroll: true });
    const submitSecurity = () => securityForm.put(route('admin.settings.security'), { preserveScroll: true });

    // Logo
    const handleLogoUpload = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setLogoPreviewUrl(URL.createObjectURL(file));
        router.post(route('admin.settings.logo.upload'), { logo: file }, { preserveScroll: true, forceFormData: true });
    };
    const handleLogoRemove = () => {
        if (!confirm('¿Eliminar el logo?')) return;
        setLogoPreviewUrl(null);
        router.delete(route('admin.settings.logo.remove'), { preserveScroll: true });
    };

    const currentForm = { branding: brandingForm, display: displayForm, kiosk: kioskForm, tickets: ticketsForm }[activeTab];

    return (
        <AuthenticatedLayout>
            <Head title="Personalización" />

            <div style={{ maxWidth: 1200, margin: '0 auto', padding: '32px 24px', fontFamily: "'Outfit', sans-serif" }}>

                {/* ── Page header ── */}
                <div style={{ marginBottom: 32 }}>
                    <h1 style={{
                        fontSize: 28, fontWeight: 800, color: V('--t-text'), margin: 0,
                        letterSpacing: '-0.03em',
                    }}>Personalización</h1>
                    <p style={{ fontSize: 14, color: V('--t-text-muted'), marginTop: 4 }}>
                        Configura la identidad visual y comportamiento de tu instancia
                    </p>
                </div>

                {/* ── Flash ── */}
                {flash?.success && (
                    <div style={{
                        display: 'flex', alignItems: 'center', gap: 8, padding: '12px 16px', marginBottom: 24,
                        background: `color-mix(in srgb, ${V('--t-green')} 8%, transparent)`,
                        border: `1px solid color-mix(in srgb, ${V('--t-green')} 25%, transparent)`,
                        borderRadius: 10, color: V('--t-green'), fontSize: 13, fontWeight: 600,
                        animation: 'fadeSlideIn 0.3s ease',
                    }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        {flash.success}
                    </div>
                )}

                {/* ── Tabs ── */}
                <div style={{
                    display: 'flex', gap: 4, marginBottom: 28,
                    background: V('--t-surface'), borderRadius: 12, padding: 4,
                    border: `1px solid ${V('--t-border')}`,
                }}>
                    {TABS.map(tab => {
                        const active = activeTab === tab.id;
                        return (
                            <button key={tab.id} onClick={() => setActiveTab(tab.id)} style={{
                                flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
                                padding: '12px 16px', borderRadius: 10, border: 'none', cursor: 'pointer',
                                background: active ? V('--t-card') : 'transparent',
                                color: active ? V('--t-text') : V('--t-text-muted'),
                                fontWeight: active ? 700 : 500, fontSize: 13,
                                fontFamily: "'Outfit', sans-serif",
                                transition: 'all 0.2s cubic-bezier(.4,0,.2,1)',
                                boxShadow: active ? `0 1px 4px rgba(0,0,0,0.15)` : 'none',
                            }}
                            onMouseEnter={e => { if (!active) e.currentTarget.style.color = V('--t-text-soft'); }}
                            onMouseLeave={e => { if (!active) e.currentTarget.style.color = V('--t-text-muted'); }}>
                                <span style={{ opacity: active ? 1 : 0.5, transition: 'opacity 0.2s' }}>{tab.icon}</span>
                                <span className="settings-tab-label">{tab.label}</span>
                            </button>
                        );
                    })}
                </div>

                {/* ── Content Grid ── */}
                <div style={{
                    display: 'grid', gridTemplateColumns: '1fr 340px', gap: 28, alignItems: 'start',
                }}>
                    {/* Left: Form */}
                    <div style={{
                        background: V('--t-card'), border: `1px solid ${V('--t-border')}`, borderRadius: 16,
                        padding: '28px 28px 20px',
                        animation: 'fadeSlideIn 0.25s ease',
                    }} key={activeTab}>

                        {/* Section header */}
                        <div style={{ marginBottom: 24 }}>
                            <h2 style={{ fontSize: 18, fontWeight: 700, color: V('--t-text'), margin: 0 }}>
                                {TABS.find(t => t.id === activeTab)?.label}
                            </h2>
                            <p style={{ fontSize: 12, color: V('--t-text-muted'), marginTop: 4 }}>
                                {TABS.find(t => t.id === activeTab)?.desc}
                            </p>
                        </div>

                        {/* ═══ BRANDING ═══ */}
                        {activeTab === 'branding' && (<>
                            {/* Logo upload */}
                            <div style={{
                                display: 'flex', alignItems: 'center', gap: 20, padding: 20,
                                background: V('--t-surface'), borderRadius: 14,
                                border: `1px dashed color-mix(in srgb, ${V('--t-border')} 80%, transparent)`,
                                marginBottom: 24, transition: 'border-color 0.2s',
                            }}>
                                {logoPreviewUrl ? (
                                    <img src={logoPreviewUrl} alt="Logo" style={{
                                        width: 72, height: 72, objectFit: 'cover', flexShrink: 0,
                                        borderRadius: brandingForm.data.logo_shape === 'circle' ? '50%' :
                                            brandingForm.data.logo_shape === 'rounded' ? 14 : 4,
                                        border: `2px solid ${V('--t-border')}`,
                                    }} />
                                ) : (
                                    <div style={{
                                        width: 72, height: 72, flexShrink: 0,
                                        borderRadius: brandingForm.data.logo_shape === 'circle' ? '50%' :
                                            brandingForm.data.logo_shape === 'rounded' ? 14 : 4,
                                        background: `linear-gradient(135deg, ${brandingForm.data.primary_color}, ${brandingForm.data.secondary_color})`,
                                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        fontSize: 28, fontWeight: 900, color: '#fff',
                                    }}>
                                        {(tenant.name || 'O')[0]}
                                    </div>
                                )}
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: V('--t-text'), marginBottom: 4 }}>
                                        Logo de la empresa
                                    </div>
                                    <div style={{ fontSize: 11, color: V('--t-text-muted'), marginBottom: 12, lineHeight: 1.4 }}>
                                        PNG, JPG, SVG o WebP. Máximo 2MB.
                                    </div>
                                    <div style={{ display: 'flex', gap: 8 }}>
                                        <button type="button" onClick={() => fileRef.current?.click()} style={{
                                            padding: '7px 16px', borderRadius: 8,
                                            background: V('--t-blue'), color: '#fff', border: 'none',
                                            fontSize: 12, fontWeight: 700, cursor: 'pointer',
                                            fontFamily: "'Outfit', sans-serif",
                                        }}>Subir logo</button>
                                        {logoPreviewUrl && (
                                            <button type="button" onClick={handleLogoRemove} style={{
                                                padding: '7px 16px', borderRadius: 8,
                                                background: `color-mix(in srgb, ${V('--t-red')} 10%, transparent)`,
                                                color: V('--t-red'), border: `1px solid color-mix(in srgb, ${V('--t-red')} 25%, transparent)`,
                                                fontSize: 12, fontWeight: 600, cursor: 'pointer',
                                                fontFamily: "'Outfit', sans-serif",
                                            }}>Eliminar</button>
                                        )}
                                    </div>
                                    <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                        onChange={handleLogoUpload} style={{ display: 'none' }} />
                                </div>
                            </div>

                            <ColorField label="Color primario" value={brandingForm.data.primary_color} onChange={v => sb('primary_color', v)} />
                            <ColorField label="Color secundario" value={brandingForm.data.secondary_color} onChange={v => sb('secondary_color', v)} />
                            <ColorField label="Color de acento" value={brandingForm.data.accent_color} onChange={v => sb('accent_color', v)} />

                            <Field label="Forma del logo">
                                <Select value={brandingForm.data.logo_shape} onChange={v => sb('logo_shape', v)} options={SHAPES} />
                            </Field>

                            <Toggle label="Modo oscuro por defecto" hint="Los usuarios aún pueden cambiar su tema"
                                value={brandingForm.data.dark_mode_default} onChange={v => sb('dark_mode_default', v)} />

                            <SaveButton processing={brandingForm.processing} onClick={submitBranding} />
                        </>)}

                        {/* ═══ DISPLAY ═══ */}
                        {activeTab === 'display' && (<>
                            <Toggle label="Mostrar nombre de cola" hint="Nombre de la cola o ventanilla en cada turno"
                                value={displayForm.data.show_queue_name} onChange={v => sd('show_queue_name', v)} />
                            <Toggle label="Mostrar nombre de servicio" hint="Tipo de servicio en las tarjetas de turno"
                                value={displayForm.data.show_service_name} onChange={v => sd('show_service_name', v)} />
                            <Toggle label="Mostrar tiempo de espera" hint="Estimación de minutos de espera"
                                value={displayForm.data.show_wait_time} onChange={v => sd('show_wait_time', v)} />

                            <div style={{ marginTop: 8 }}>
                                <Field label="Turnos recientes visibles" hint="Cantidad de turnos atendidos en el panel lateral">
                                    <InputNumber value={displayForm.data.show_recent_count} onChange={v => sd('show_recent_count', v)} min={1} max={20} />
                                </Field>

                                <Field label="Texto de anuncio" hint="Se muestra como banner en la pantalla pública (opcional)">
                                    <InputText value={displayForm.data.announcement_text || ''} onChange={v => sd('announcement_text', v)}
                                        placeholder="Ej: Horario especial este viernes" maxLength={500} />
                                </Field>

                                <Field label="Sonido al llamar turno">
                                    <Select value={displayForm.data.call_sound} onChange={v => sd('call_sound', v)} options={SOUNDS} />
                                </Field>
                            </div>

                            <SaveButton processing={displayForm.processing} onClick={submitDisplay} />
                        </>)}

                        {/* ═══ KIOSK ═══ */}
                        {activeTab === 'kiosk' && (<>
                            <Field label="Texto de bienvenida" hint="Saludo que ven los clientes al abrir el kiosco">
                                <InputText value={kioskForm.data.welcome_text} onChange={v => sk('welcome_text', v)}
                                    placeholder="Bienvenido" maxLength={200} />
                            </Field>

                            <Divider />

                            <Toggle label="Permitir selección de prioridad" hint="Los clientes pueden marcar si necesitan atención prioritaria"
                                value={kioskForm.data.show_priority_option} onChange={v => sk('show_priority_option', v)} />
                            <Toggle label="Mostrar tiempo estimado" hint="Estimación visible de espera al tomar turno"
                                value={kioskForm.data.show_estimated_wait} onChange={v => sk('show_estimated_wait', v)} />
                            <Toggle label="Imprimir ticket físico" hint="Requiere impresora térmica conectada"
                                value={kioskForm.data.print_ticket} onChange={v => sk('print_ticket', v)} />

                            <SaveButton processing={kioskForm.processing} onClick={submitKiosk} />
                        </>)}

                        {/* ═══ TICKETS ═══ */}
                        {activeTab === 'tickets' && (<>
                            <Field label="Prefijo de turno" hint={`Los tickets se verán como: ${ticketsForm.data.prefix || 'A'}-001`}
                                error={ticketsForm.errors.prefix}>
                                <InputText value={ticketsForm.data.prefix} onChange={v => st('prefix', v.toUpperCase())}
                                    placeholder="A" maxLength={5} mono style={{ maxWidth: 140, fontSize: 18, textAlign: 'center' }} />
                            </Field>

                            <Divider />

                            <Toggle label="Reiniciar numeración diariamente" hint="La numeración vuelve a 001 cada día"
                                value={ticketsForm.data.daily_reset} onChange={v => st('daily_reset', v)} />

                            <Divider />

                            <Field label="Auto-cerrar turnos después de (minutos)"
                                hint="Turnos que excedan este tiempo se cierran automáticamente"
                                error={ticketsForm.errors.auto_close_minutes}>
                                <InputNumber value={ticketsForm.data.auto_close_minutes} onChange={v => st('auto_close_minutes', v)} min={5} max={1440} />
                            </Field>

                            <Field label="Marcar como 'no presentado' después de (minutos)"
                                hint="Tiempo de espera antes de declarar no-show"
                                error={ticketsForm.errors.no_show_minutes}>
                                <InputNumber value={ticketsForm.data.no_show_minutes} onChange={v => st('no_show_minutes', v)} min={1} max={120} />
                            </Field>

                            <SaveButton processing={ticketsForm.processing} onClick={submitTickets} />
                        </>)}

                        {/* ═══ SECURITY ═══ */}
                        {activeTab === 'security' && (<>
                            <div style={{
                                padding: '12px 16px', borderRadius: 10, marginBottom: 20,
                                background: `color-mix(in srgb, ${V('--t-amber')} 8%, transparent)`,
                                border: `1px solid color-mix(in srgb, ${V('--t-amber')} 20%, transparent)`,
                            }}>
                                <div style={{ fontSize: 12, fontWeight: 600, color: V('--t-amber'), marginBottom: 4 }}>Protección contra abuso</div>
                                <div style={{ fontSize: 11, color: V('--t-text-muted'), lineHeight: 1.5 }}>
                                    Estos límites controlan cuántos turnos pueden generarse desde el kiosco y la API pública.
                                    Ajústalos según el volumen real de tu operación.
                                </div>
                            </div>

                            <Field label="Máximo turnos por hora (por sucursal)"
                                hint="Límite global: aplica a todas las IPs combinadas. Si 100 personas escanean el QR, no se generarán más de este número por hora."
                                error={securityForm.errors.max_tickets_per_hour}>
                                <InputNumber value={securityForm.data.max_tickets_per_hour} onChange={v => ss('max_tickets_per_hour', v)} min={10} max={500} />
                            </Field>

                            <Field label="Máximo turnos por minuto (por IP)"
                                hint="Cuántos turnos puede generar una misma IP por minuto para cada sucursal"
                                error={securityForm.errors.max_tickets_per_ip_minute}>
                                <InputNumber value={securityForm.data.max_tickets_per_ip_minute} onChange={v => ss('max_tickets_per_ip_minute', v)} min={1} max={30} />
                            </Field>

                            <Field label="Máximo turnos en espera simultánea"
                                hint="Si se alcanza este número, el kiosco dejará de emitir turnos hasta que se atiendan los actuales"
                                error={securityForm.errors.max_concurrent_waiting}>
                                <InputNumber value={securityForm.data.max_concurrent_waiting} onChange={v => ss('max_concurrent_waiting', v)} min={5} max={200} />
                            </Field>

                            <Field label="Límite diario de turnos (por sucursal)"
                                hint="Máximo absoluto de turnos que puede emitir una sucursal en un día"
                                error={securityForm.errors.max_daily_tickets}>
                                <InputNumber value={securityForm.data.max_daily_tickets} onChange={v => ss('max_daily_tickets', v)} min={50} max={5000} />
                            </Field>

                            <Divider />

                            <Toggle label="Protección anti-bots" hint="Detecta y rechaza solicitudes automatizadas (honeypot + validación de tiempo)"
                                value={securityForm.data.bot_protection} onChange={v => ss('bot_protection', v)} />

                            <Toggle label="Nombre obligatorio en kiosco" hint="Los clientes deben ingresar su nombre para tomar turno"
                                value={securityForm.data.require_customer_name} onChange={v => ss('require_customer_name', v)} />

                            <SaveButton processing={securityForm.processing} onClick={submitSecurity} />
                        </>)}
                    </div>

                    {/* Right: Preview */}
                    <LivePreview settings={localSettings} logoUrl={logoPreviewUrl} tenantName={tenant.name} />
                </div>
            </div>

            <style>{`
                @keyframes fadeSlideIn {
                    from { opacity: 0; transform: translateY(6px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @media (max-width: 900px) {
                    .settings-tab-label { display: none; }
                }
                @media (max-width: 768px) {
                    div[style*="grid-template-columns: 1fr 340px"] {
                        grid-template-columns: 1fr !important;
                    }
                }
            `}</style>
        </AuthenticatedLayout>
    );
}
