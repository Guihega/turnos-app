// resources/js/Pages/Admin/Services/Form.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Btn, Card, T, theme } from '@/Components/TurnosUI';

const COLORS = [
    { value: '#3D7AFF', name: 'Azul' },
    { value: '#00D68F', name: 'Verde' },
    { value: '#FFB020', name: 'Ámbar' },
    { value: '#FF4757', name: 'Rojo' },
    { value: '#9D5CFF', name: 'Púrpura' },
    { value: '#EC4899', name: 'Rosa' },
    { value: '#00D4FF', name: 'Cian' },
    { value: '#F97316', name: 'Naranja' },
    { value: '#6366F1', name: 'Índigo' },
    { value: '#14B8A6', name: 'Teal' },
];

const ICONS = ['⬡', '◈', '◉', '◆', '▣', '◧', '⊕', '⊗', '⬢', '◎', '⊞', '⊠'];

const inputStyle = {
    width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
    borderRadius: 10, padding: '12px 14px', fontSize: 14, outline: 'none', fontFamily: T.font,
    transition: 'border-color 0.2s, box-shadow 0.2s',
};
const labelStyle = { fontSize: 11, fontWeight: 700, color: T.textSoft, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6 };

function Field({ label, error, children, span }) {
    return (
        <div style={span ? { gridColumn: span } : {}}>
            <label style={labelStyle}>{label}</label>
            {children}
            {error && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{error}</div>}
        </div>
    );
}

export default function ServiceForm({ service }) {
    const isEdit = !!service;
    const [showCustomColor, setShowCustomColor] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        name: service?.name || '',
        code: service?.code || '',
        color: service?.color || '#3D7AFF',
        icon: service?.icon || '⬡',
        description: service?.description || '',
        estimated_duration_minutes: service?.estimated_duration_minutes || 15,
        requires_appointment: service?.requires_appointment ?? false,
        is_active: service?.is_active ?? true,
    });

    const submit = (e) => { e.preventDefault(); isEdit ? put(route('admin.servicios.update', service.id)) : post(route('admin.servicios.store')); };

    return (
        <AuthenticatedLayout>
            <Head title={isEdit ? `Editar: ${service.name}` : 'Nuevo Servicio'} />
            <div style={{ padding: '24px 28px', background: T.bg, minHeight: '100vh', fontFamily: T.font, color: T.text }}>

                {/* Header */}
                <div className="t-fade-up" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 28 }}>
                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 900, margin: 0, letterSpacing: '-0.02em' }}>
                            {isEdit ? `Editar: ${service.name}` : 'Nuevo Servicio'}
                        </h1>
                        <p style={{ fontSize: 13, color: T.textMuted, margin: '4px 0 0' }}>
                            {isEdit ? 'Modifica la configuración del servicio' : 'Define un nuevo tipo de atención'}
                        </p>
                    </div>
                    <Link href={route('admin.servicios.index')}><Btn variant="ghost">← Volver</Btn></Link>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 300px', gap: 20, maxWidth: 900 }} className="t-grid-responsive">
                    {/* ── Form ── */}
                    <form onSubmit={submit}>

                        {/* Status toggle */}
                        {isEdit && (
                            <div className="t-fade-up t-stagger-1" style={{
                                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                background: data.is_active ? `${T.green}08` : `${T.red}08`,
                                border: `1px solid ${data.is_active ? T.green : T.red}20`,
                                borderRadius: 14, padding: '14px 18px', marginBottom: 16, transition: 'all 0.3s',
                            }}>
                                <div>
                                    <div style={{ fontSize: 13, fontWeight: 700, color: data.is_active ? T.green : T.red }}>
                                        {data.is_active ? '◆ Servicio Activo' : '◇ Servicio Inactivo'}
                                    </div>
                                    <div style={{ fontSize: 11, color: T.textMuted }}>
                                        {data.is_active ? 'Visible en kiosco' : 'Oculto para clientes'}
                                    </div>
                                </div>
                                <button type="button" onClick={() => setData('is_active', !data.is_active)} style={{
                                    width: 48, height: 26, borderRadius: 13, border: 'none', cursor: 'pointer',
                                    background: data.is_active ? T.green : T.border, position: 'relative', transition: 'background 0.3s',
                                }}>
                                    <div style={{ width: 20, height: 20, borderRadius: '50%', background: '#fff', position: 'absolute', top: 3, left: data.is_active ? 25 : 3, transition: 'left 0.3s', boxShadow: '0 1px 3px rgba(0,0,0,0.2)' }} />
                                </button>
                            </div>
                        )}

                        {/* Identity */}
                        <Card className="t-fade-up t-stagger-2" accent={data.color} style={{ marginBottom: 16 }}>
                            <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Identidad del Servicio</div>
                            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 16 }}>
                                <Field label="Nombre del servicio" error={errors.name}>
                                    <input style={inputStyle} value={data.name} onChange={e => setData('name', e.target.value)} required placeholder="Ej: Consulta General" />
                                </Field>
                                <Field label="Código" error={errors.code}>
                                    <input style={{ ...inputStyle, fontFamily: T.mono, fontWeight: 700, textTransform: 'uppercase', textAlign: 'center', fontSize: 16 }}
                                        value={data.code} onChange={e => setData('code', e.target.value.toUpperCase())} required maxLength={5} placeholder="CG" />
                                </Field>
                            </div>

                            {/* Color picker */}
                            <div style={{ marginTop: 18 }}>
                                <label style={labelStyle}>Color identificador</label>
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
                                    {COLORS.map(c => (
                                        <button key={c.value} type="button" onClick={() => setData('color', c.value)}
                                            title={c.name}
                                            style={{
                                                width: 36, height: 36, borderRadius: 10, background: c.value, border: 'none',
                                                cursor: 'pointer', transition: 'all 0.2s', position: 'relative',
                                                outline: data.color === c.value ? `2px solid ${c.value}` : 'none',
                                                outlineOffset: 3,
                                                transform: data.color === c.value ? 'scale(1.15)' : 'scale(1)',
                                                boxShadow: data.color === c.value ? `0 4px 12px ${c.value}40` : 'none',
                                            }}>
                                            {data.color === c.value && <span style={{ color: '#fff', fontSize: 14, fontWeight: 900 }}>✓</span>}
                                        </button>
                                    ))}
                                    {/* Custom color */}
                                    <div style={{ position: 'relative' }}>
                                        <button type="button" onClick={() => setShowCustomColor(!showCustomColor)} style={{
                                            width: 36, height: 36, borderRadius: 10, border: `2px dashed ${T.border}`,
                                            background: !COLORS.find(c => c.value === data.color) ? data.color : 'transparent',
                                            cursor: 'pointer', fontSize: 14, color: T.textMuted, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        }}>+</button>
                                        {showCustomColor && (
                                            <div style={{ position: 'absolute', top: 42, left: 0, zIndex: 10, background: T.card, border: `1px solid ${T.border}`, borderRadius: 10, padding: 12, boxShadow: T.shadow }}>
                                                <input type="color" value={data.color} onChange={e => setData('color', e.target.value)}
                                                    style={{ width: 120, height: 40, border: 'none', borderRadius: 8, cursor: 'pointer' }} />
                                                <div style={{ fontSize: 10, color: T.textMuted, marginTop: 6, fontFamily: T.mono, textAlign: 'center' }}>{data.color}</div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Icon picker */}
                            <div style={{ marginTop: 18 }}>
                                <label style={labelStyle}>Ícono</label>
                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                    {ICONS.map(icon => (
                                        <button key={icon} type="button" onClick={() => setData('icon', icon)} style={{
                                            width: 38, height: 38, borderRadius: 8, border: `1px solid ${data.icon === icon ? data.color : T.border}`,
                                            background: data.icon === icon ? `${data.color}15` : T.surface, cursor: 'pointer',
                                            fontSize: 18, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                            color: data.icon === icon ? data.color : T.textMuted, transition: 'all 0.2s',
                                        }}>{icon}</button>
                                    ))}
                                </div>
                            </div>
                        </Card>

                        {/* Configuration */}
                        <Card className="t-fade-up t-stagger-3" accent={T.purple} style={{ marginBottom: 16 }}>
                            <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Configuración</div>

                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 18 }}>
                                <Field label="Duración estimada (min)">
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        <input type="range" min={5} max={120} step={5} value={data.estimated_duration_minutes}
                                            onChange={e => setData('estimated_duration_minutes', parseInt(e.target.value))}
                                            style={{ flex: 1, accentColor: data.color }} />
                                        <span style={{ fontSize: 18, fontWeight: 800, fontFamily: T.mono, color: data.color, minWidth: 44, textAlign: 'right' }}>
                                            {data.estimated_duration_minutes}
                                        </span>
                                    </div>
                                </Field>
                                <div style={{ display: 'flex', alignItems: 'center' }}>
                                    <div style={{
                                        flex: 1, display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                        background: T.surface, borderRadius: 10, padding: '12px 14px', border: `1px solid ${data.requires_appointment ? `${T.amber}30` : T.border}`,
                                    }}>
                                        <div>
                                            <div style={{ fontSize: 12, fontWeight: 600, color: data.requires_appointment ? T.text : T.textMuted }}>Requiere cita</div>
                                            <div style={{ fontSize: 9, color: T.textMuted }}>Agenda previa</div>
                                        </div>
                                        <button type="button" onClick={() => setData('requires_appointment', !data.requires_appointment)} style={{
                                            width: 40, height: 22, borderRadius: 11, border: 'none', cursor: 'pointer',
                                            background: data.requires_appointment ? T.amber : T.border, position: 'relative', transition: 'background 0.3s',
                                        }}>
                                            <div style={{ width: 16, height: 16, borderRadius: '50%', background: '#fff', position: 'absolute', top: 3, left: data.requires_appointment ? 21 : 3, transition: 'left 0.3s', boxShadow: '0 1px 2px rgba(0,0,0,0.2)' }} />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <Field label="Descripción" span="1 / -1">
                                <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={3} placeholder="Describe brevemente este servicio para los clientes del kiosco..."
                                    style={{ ...inputStyle, resize: 'vertical', minHeight: 80, lineHeight: 1.5 }} />
                            </Field>
                        </Card>

                        {/* Actions */}
                        <div className="t-fade-up t-stagger-4" style={{ display: 'flex', gap: 12 }}>
                            <Btn type="submit" variant="primary" size="lg" disabled={processing}>
                                {processing ? 'Guardando...' : isEdit ? '◆ Actualizar Servicio' : '◆ Crear Servicio'}
                            </Btn>
                            <Link href={route('admin.servicios.index')}><Btn variant="ghost" size="lg">Cancelar</Btn></Link>
                        </div>
                    </form>

                    {/* ── Live Preview ── */}
                    <div className="t-fade-up t-stagger-3">
                        <div style={{ position: 'sticky', top: 80 }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 10 }}>Vista previa — Kiosco</div>

                            {/* Preview: kiosk card */}
                            <div style={{
                                background: T.card, border: `2px solid ${T.border}`, borderRadius: 20,
                                padding: '24px 20px', position: 'relative', overflow: 'hidden',
                                transition: 'border-color 0.3s',
                            }}>
                                <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 3, background: data.color, transition: 'background 0.3s' }} />
                                <div style={{ fontSize: 28, marginBottom: 14, opacity: 0.7, transition: 'color 0.3s', color: data.color }}>{data.icon || '⬡'}</div>
                                <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 6 }}>{data.name || 'Nombre del servicio'}</div>
                                {data.description && <div style={{ fontSize: 12, color: T.textMuted, marginBottom: 10, lineHeight: 1.4 }}>{data.description}</div>}
                                <div style={{ fontSize: 11, color: T.textMuted, fontFamily: T.mono }}>⏱ ~{data.estimated_duration_minutes}min · Cola: {data.code || '??'}</div>
                                {data.requires_appointment && <div style={{ fontSize: 10, color: T.amber, marginTop: 8, fontWeight: 600 }}>⚠ Requiere cita previa</div>}
                            </div>

                            {/* Preview: badge */}
                            <div style={{ marginTop: 16, fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8 }}>Badge en tabla</div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, background: T.card, borderRadius: 10, padding: '10px 14px', border: `1px solid ${T.border}` }}>
                                <span style={{ width: 8, height: 8, borderRadius: '50%', background: data.color, boxShadow: `0 0 8px ${data.color}40`, transition: 'all 0.3s' }} />
                                <span style={{ fontSize: 13, fontWeight: 600 }}>{data.name || 'Servicio'}</span>
                                <span style={{ fontSize: 10, fontFamily: T.mono, color: T.textMuted, background: T.surface, padding: '2px 6px', borderRadius: 4 }}>{data.code || '??'}</span>
                            </div>

                            {/* Color value */}
                            <div style={{ marginTop: 16, display: 'flex', alignItems: 'center', gap: 8 }}>
                                <div style={{ width: 24, height: 24, borderRadius: 6, background: data.color, transition: 'background 0.3s' }} />
                                <span style={{ fontSize: 12, fontFamily: T.mono, color: T.textMuted }}>{data.color}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
