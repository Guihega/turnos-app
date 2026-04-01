// resources/js/Pages/Admin/Branches/Form.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Btn, Card, T, theme } from '@/Components/TurnosUI';

const DAYS = [
    { key: 'mon', label: 'Lunes', short: 'L' },
    { key: 'tue', label: 'Martes', short: 'M' },
    { key: 'wed', label: 'Miércoles', short: 'Mi' },
    { key: 'thu', label: 'Jueves', short: 'J' },
    { key: 'fri', label: 'Viernes', short: 'V' },
    { key: 'sat', label: 'Sábado', short: 'S' },
    { key: 'sun', label: 'Domingo', short: 'D' },
];

const DEFAULT_HOURS = { open: '08:00', close: '18:00' };

const inputStyle = {
    width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
    borderRadius: 10, padding: '12px 14px', fontSize: 14, outline: 'none', fontFamily: T.font,
    transition: 'border-color 0.2s',
};

const labelStyle = {
    fontSize: 11, fontWeight: 700, color: T.textSoft, textTransform: 'uppercase',
    letterSpacing: '0.08em', display: 'block', marginBottom: 6, fontFamily: T.font,
};

const errorStyle = { fontSize: 11, color: T.red, marginTop: 4 };

function Field({ label, error, children, span }) {
    return (
        <div style={span ? { gridColumn: span } : {}}>
            <label style={labelStyle}>{label}</label>
            {children}
            {error && <div style={errorStyle}>{error}</div>}
        </div>
    );
}

export default function BranchForm({ branch }) {
    const isEdit = !!branch;
    const initialHours = branch?.operating_hours || {};

    const { data, setData, post, put, processing, errors } = useForm({
        name: branch?.name || '',
        code: branch?.code || '',
        address: branch?.address || '',
        city: branch?.city || '',
        state: branch?.state || '',
        phone: branch?.phone || '',
        email: branch?.email || '',
        timezone: branch?.timezone || 'America/Mexico_City',
        max_daily_tickets: branch?.max_daily_tickets || 500,
        max_concurrent_waiting: branch?.max_concurrent_waiting || 50,
        accepts_walkins: branch?.accepts_walkins ?? true,
        accepts_appointments: branch?.accepts_appointments ?? true,
        is_active: branch?.is_active ?? true,
        operating_hours: initialHours,
    });

    const toggleDay = (dayKey) => {
        const hours = { ...data.operating_hours };
        if (hours[dayKey]) {
            delete hours[dayKey];
        } else {
            hours[dayKey] = { ...DEFAULT_HOURS };
        }
        setData('operating_hours', hours);
    };

    const setDayTime = (dayKey, field, value) => {
        const hours = { ...data.operating_hours };
        hours[dayKey] = { ...hours[dayKey], [field]: value };
        setData('operating_hours', hours);
    };

    const applyToAll = (dayKey) => {
        const src = data.operating_hours[dayKey];
        if (!src) return;
        const hours = { ...data.operating_hours };
        DAYS.forEach(d => { if (hours[d.key]) hours[d.key] = { ...src }; });
        setData('operating_hours', hours);
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(route('admin.sucursales.update', branch.id));
        else post(route('admin.sucursales.store'));
    };

    const activeDays = Object.keys(data.operating_hours).length;

    return (
        <AuthenticatedLayout>
            <Head title={isEdit ? `Editar: ${branch.name}` : 'Nueva Sucursal'} />
            <div style={{ padding: '24px 28px', background: T.bg, minHeight: '100vh', fontFamily: T.font, color: T.text }}>

                {/* Header */}
                <div className="t-fade-up" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 28 }}>
                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 900, margin: 0, letterSpacing: '-0.02em' }}>
                            {isEdit ? `Editar: ${branch.name}` : 'Nueva Sucursal'}
                        </h1>
                        <p style={{ fontSize: 13, color: T.textMuted, margin: '4px 0 0' }}>
                            {isEdit ? 'Modifica la configuración de esta sucursal' : 'Configura una nueva ubicación'}
                        </p>
                    </div>
                    <Link href={route('admin.sucursales.index')}><Btn variant="ghost">← Volver</Btn></Link>
                </div>

                <form onSubmit={submit} style={{ maxWidth: 780 }}>

                    {/* ── Status Toggle (Edit only) ── */}
                    {isEdit && (
                        <div className="t-fade-up t-stagger-1" style={{
                            display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                            background: data.is_active ? `${T.green}08` : `${T.red}08`,
                            border: `1px solid ${data.is_active ? T.green : T.red}20`,
                            borderRadius: 14, padding: '16px 20px', marginBottom: 20,
                            transition: 'all 0.3s',
                        }}>
                            <div>
                                <div style={{ fontSize: 14, fontWeight: 700, color: data.is_active ? T.green : T.red }}>
                                    {data.is_active ? '◆ Sucursal Activa' : '◇ Sucursal Inactiva'}
                                </div>
                                <div style={{ fontSize: 12, color: T.textMuted, marginTop: 2 }}>
                                    {data.is_active ? 'Visible en kiosco y operadores' : 'No visible para clientes ni operadores'}
                                </div>
                            </div>
                            <button type="button" onClick={() => setData('is_active', !data.is_active)} style={{
                                width: 52, height: 28, borderRadius: 14, border: 'none', cursor: 'pointer',
                                background: data.is_active ? T.green : T.border, position: 'relative',
                                transition: 'background 0.3s',
                            }}>
                                <div style={{
                                    width: 22, height: 22, borderRadius: '50%', background: '#fff',
                                    position: 'absolute', top: 3, transition: 'left 0.3s',
                                    left: data.is_active ? 27 : 3,
                                    boxShadow: '0 2px 4px rgba(0,0,0,0.2)',
                                }} />
                            </button>
                        </div>
                    )}

                    {/* ── Información General ── */}
                    <Card className="t-fade-up t-stagger-2" accent={T.blue} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Información General</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 16 }}>
                            <Field label="Nombre" error={errors.name}>
                                <input style={inputStyle} value={data.name} onChange={e => setData('name', e.target.value)} required placeholder="Ej: Sede Centro" />
                            </Field>
                            <Field label="Código" error={errors.code}>
                                <input style={{ ...inputStyle, fontFamily: T.mono, fontWeight: 600, textTransform: 'uppercase' }} value={data.code} onChange={e => setData('code', e.target.value.toUpperCase())} required maxLength={10} placeholder="CTR" />
                            </Field>
                            <Field label="Dirección" error={errors.address} span="1 / -1">
                                <input style={inputStyle} value={data.address} onChange={e => setData('address', e.target.value)} placeholder="Calle, número, colonia..." />
                            </Field>
                            <Field label="Ciudad" error={errors.city}>
                                <input style={inputStyle} value={data.city} onChange={e => setData('city', e.target.value)} />
                            </Field>
                            <Field label="Estado">
                                <input style={inputStyle} value={data.state} onChange={e => setData('state', e.target.value)} />
                            </Field>
                            <Field label="Teléfono">
                                <input style={inputStyle} value={data.phone} onChange={e => setData('phone', e.target.value)} type="tel" placeholder="+52 222..." />
                            </Field>
                            <Field label="Email">
                                <input style={inputStyle} value={data.email} onChange={e => setData('email', e.target.value)} type="email" placeholder="sucursal@ejemplo.com" />
                            </Field>
                        </div>
                    </Card>

                    {/* ── Horarios de Operación ── */}
                    <Card className="t-fade-up t-stagger-3" accent={T.amber} style={{ marginBottom: 16 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 }}>
                            <div>
                                <div style={{ fontSize: 15, fontWeight: 700 }}>Horarios de Operación</div>
                                <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                                    {activeDays === 0 ? 'Sin horario — kiosco siempre abierto' : `${activeDays} día${activeDays > 1 ? 's' : ''} configurado${activeDays > 1 ? 's' : ''}`}
                                </div>
                            </div>
                            <select value={data.timezone} onChange={e => setData('timezone', e.target.value)}
                                style={{ ...inputStyle, width: 'auto', fontSize: 11, padding: '8px 12px', fontFamily: T.mono }}>
                                <option value="America/Mexico_City">CDMX (CST)</option>
                                <option value="America/Tijuana">Tijuana (PST)</option>
                                <option value="America/Monterrey">Monterrey (CST)</option>
                                <option value="America/Cancun">Cancún (EST)</option>
                            </select>
                        </div>

                        {/* Day toggles */}
                        <div style={{ display: 'flex', gap: 6, marginBottom: 16 }}>
                            {DAYS.map(day => {
                                const active = !!data.operating_hours[day.key];
                                return (
                                    <button key={day.key} type="button" onClick={() => toggleDay(day.key)} style={{
                                        flex: 1, padding: '10px 4px', borderRadius: 10, border: `1px solid ${active ? T.amber : T.border}`,
                                        background: active ? `${T.amber}12` : T.surface, color: active ? T.amber : T.textMuted,
                                        cursor: 'pointer', fontSize: 12, fontWeight: 700, fontFamily: T.font,
                                        transition: 'all 0.2s', textAlign: 'center',
                                    }}>
                                        <div style={{ fontSize: 14, fontWeight: 800 }}>{day.short}</div>
                                        <div style={{ fontSize: 8, marginTop: 2, opacity: 0.7 }}>{day.label.substring(0, 3)}</div>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Time inputs for active days */}
                        {DAYS.filter(d => data.operating_hours[d.key]).map(day => (
                            <div key={day.key} style={{
                                display: 'flex', alignItems: 'center', gap: 12, marginBottom: 8,
                                background: T.surface, borderRadius: 10, padding: '10px 14px',
                            }}>
                                <span style={{ fontSize: 13, fontWeight: 600, width: 80, color: T.amber }}>{day.label}</span>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <span style={{ fontSize: 10, color: T.textMuted }}>Abre</span>
                                    <input type="time" value={data.operating_hours[day.key]?.open || '08:00'}
                                        onChange={e => setDayTime(day.key, 'open', e.target.value)}
                                        style={{ ...inputStyle, width: 110, padding: '8px 10px', fontSize: 13, fontFamily: T.mono }} />
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <span style={{ fontSize: 10, color: T.textMuted }}>Cierra</span>
                                    <input type="time" value={data.operating_hours[day.key]?.close || '18:00'}
                                        onChange={e => setDayTime(day.key, 'close', e.target.value)}
                                        style={{ ...inputStyle, width: 110, padding: '8px 10px', fontSize: 13, fontFamily: T.mono }} />
                                </div>
                                <button type="button" onClick={() => applyToAll(day.key)} title="Aplicar a todos los días activos"
                                    style={{ background: 'none', border: `1px solid ${T.border}`, borderRadius: 6, padding: '4px 8px', cursor: 'pointer', color: T.textMuted, fontSize: 10, fontFamily: T.font, whiteSpace: 'nowrap' }}>
                                    Aplicar a todos
                                </button>
                            </div>
                        ))}

                        {activeDays === 0 && (
                            <div style={{ textAlign: 'center', padding: '16px 0', color: T.textMuted, fontSize: 12 }}>
                                Haz clic en un día para configurar su horario. Sin horario, el kiosco estará siempre disponible.
                            </div>
                        )}
                    </Card>

                    {/* ── Configuración de Capacidad ── */}
                    <Card className="t-fade-up t-stagger-4" accent={T.purple} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Capacidad y Permisos</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 18 }}>
                            <Field label="Máx. turnos por día">
                                <input type="number" style={{ ...inputStyle, fontFamily: T.mono }} value={data.max_daily_tickets} onChange={e => setData('max_daily_tickets', parseInt(e.target.value) || 0)} min={1} />
                            </Field>
                            <Field label="Máx. espera simultánea">
                                <input type="number" style={{ ...inputStyle, fontFamily: T.mono }} value={data.max_concurrent_waiting} onChange={e => setData('max_concurrent_waiting', parseInt(e.target.value) || 0)} min={1} />
                            </Field>
                        </div>

                        {/* Toggle switches */}
                        <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
                            {[
                                { key: 'accepts_walkins', label: 'Acepta turnos sin cita', desc: 'Clientes pueden tomar turno desde el kiosco', color: T.green },
                                { key: 'accepts_appointments', label: 'Acepta citas', desc: 'Permite agendar citas programadas', color: T.blue },
                            ].map(opt => (
                                <div key={opt.key} style={{
                                    flex: '1 1 260px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    background: T.surface, borderRadius: 10, padding: '14px 16px',
                                    border: `1px solid ${data[opt.key] ? `${opt.color}20` : T.border}`,
                                }}>
                                    <div>
                                        <div style={{ fontSize: 13, fontWeight: 600, color: data[opt.key] ? T.text : T.textMuted }}>{opt.label}</div>
                                        <div style={{ fontSize: 10, color: T.textMuted, marginTop: 2 }}>{opt.desc}</div>
                                    </div>
                                    <button type="button" onClick={() => setData(opt.key, !data[opt.key])} style={{
                                        width: 44, height: 24, borderRadius: 12, border: 'none', cursor: 'pointer', flexShrink: 0,
                                        background: data[opt.key] ? opt.color : T.border, position: 'relative', transition: 'background 0.3s',
                                    }}>
                                        <div style={{
                                            width: 18, height: 18, borderRadius: '50%', background: '#fff',
                                            position: 'absolute', top: 3, transition: 'left 0.3s',
                                            left: data[opt.key] ? 23 : 3, boxShadow: '0 1px 3px rgba(0,0,0,0.2)',
                                        }} />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </Card>

                    {/* ── Actions ── */}
                    <div className="t-fade-up t-stagger-5" style={{ display: 'flex', gap: 12, marginTop: 24 }}>
                        <Btn type="submit" variant="primary" size="lg" disabled={processing}>
                            {processing ? 'Guardando...' : isEdit ? '◆ Actualizar Sucursal' : '◆ Crear Sucursal'}
                        </Btn>
                        <Link href={route('admin.sucursales.index')}><Btn variant="ghost" size="lg">Cancelar</Btn></Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
