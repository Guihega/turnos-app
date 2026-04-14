// resources/js/Pages/Admin/Branches/Form.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import { Btn, Card, T } from '@/Components/TurnosUI';

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

const COUNTRIES = [
    { code: 'MX', name: 'México', flag: '🇲🇽' },
    { code: 'CO', name: 'Colombia', flag: '🇨🇴' },
    { code: 'PE', name: 'Perú', flag: '🇵🇪' },
    { code: 'CL', name: 'Chile', flag: '🇨🇱' },
    { code: 'AR', name: 'Argentina', flag: '🇦🇷' },
    { code: 'EC', name: 'Ecuador', flag: '🇪🇨' },
    { code: 'GT', name: 'Guatemala', flag: '🇬🇹' },
    { code: 'CR', name: 'Costa Rica', flag: '🇨🇷' },
    { code: 'PA', name: 'Panamá', flag: '🇵🇦' },
    { code: 'DO', name: 'Rep. Dominicana', flag: '🇩🇴' },
    { code: 'SV', name: 'El Salvador', flag: '🇸🇻' },
    { code: 'HN', name: 'Honduras', flag: '🇭🇳' },
    { code: 'NI', name: 'Nicaragua', flag: '🇳🇮' },
    { code: 'BO', name: 'Bolivia', flag: '🇧🇴' },
    { code: 'PY', name: 'Paraguay', flag: '🇵🇾' },
    { code: 'UY', name: 'Uruguay', flag: '🇺🇾' },
    { code: 'VE', name: 'Venezuela', flag: '🇻🇪' },
    { code: 'BR', name: 'Brasil', flag: '🇧🇷' },
    { code: 'US', name: 'Estados Unidos', flag: '🇺🇸' },
    { code: 'ES', name: 'España', flag: '🇪🇸' },
];

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

function Field({ label, error, children, span, hint }) {
    return (
        <div style={span ? { gridColumn: span } : {}}>
            <label style={labelStyle}>{label}</label>
            {children}
            {hint && !error && <div style={{ fontSize: 10, color: T.textMuted, marginTop: 4 }}>{hint}</div>}
            {error && <div style={errorStyle}>{error}</div>}
        </div>
    );
}

// ── Componente de búsqueda con autocompletado ──
function SearchableSelect({ value, onChange, options, loading, placeholder, onSearch, disabled }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const filtered = search
        ? options.filter(o => o.name.toLowerCase().includes(search.toLowerCase()))
        : options;

    const handleSelect = (option) => {
        onChange(option);
        setSearch('');
        setOpen(false);
    };

    return (
        <div style={{ position: 'relative' }}>
            <div style={{
                ...inputStyle,
                display: 'flex', alignItems: 'center', cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.5 : 1,
            }} onClick={() => !disabled && setOpen(!open)}>
                <span style={{ flex: 1, color: value ? T.text : T.textMuted }}>
                    {value || placeholder || 'Seleccionar...'}
                </span>
                {loading && <span style={{ fontSize: 11 }}>⏳</span>}
                <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" style={{ opacity: 0.4, flexShrink: 0 }}>
                    <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
            </div>

            {open && !disabled && (
                <>
                    <div onClick={() => setOpen(false)} style={{ position: 'fixed', inset: 0, zIndex: 40 }} />
                    <div style={{
                        position: 'absolute', top: '100%', left: 0, right: 0, zIndex: 50,
                        marginTop: 4, background: T.card, border: `1px solid ${T.border}`,
                        borderRadius: 10, boxShadow: '0 8px 24px rgba(0,0,0,0.15)',
                        maxHeight: 240, overflow: 'hidden', display: 'flex', flexDirection: 'column',
                    }}>
                        {/* Buscador */}
                        <input
                            type="text"
                            value={search}
                            onChange={e => { setSearch(e.target.value); onSearch?.(e.target.value); }}
                            placeholder="Buscar..."
                            autoFocus
                            style={{
                                ...inputStyle, borderRadius: 0, border: 'none',
                                borderBottom: `1px solid ${T.border}`, fontSize: 13,
                            }}
                        />
                        <div style={{ overflow: 'auto', flex: 1 }}>
                            {loading ? (
                                <div style={{ padding: 16, textAlign: 'center', color: T.textMuted, fontSize: 12 }}>
                                    Cargando...
                                </div>
                            ) : filtered.length === 0 ? (
                                <div style={{ padding: 16, textAlign: 'center', color: T.textMuted, fontSize: 12 }}>
                                    {search ? 'Sin resultados' : 'Sin opciones disponibles'}
                                </div>
                            ) : (
                                filtered.map((option, i) => (
                                    <div key={option.id || option.name || i}
                                        onClick={() => handleSelect(option)}
                                        style={{
                                            padding: '10px 14px', cursor: 'pointer', fontSize: 13,
                                            color: T.text, transition: 'background 0.1s',
                                            borderBottom: i < filtered.length - 1 ? `1px solid ${T.border}33` : 'none',
                                        }}
                                        onMouseEnter={e => e.currentTarget.style.background = T.surface}
                                        onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                        {option.name}
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </>
            )}
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
        country: branch?.country || 'MX',
        latitude: branch?.latitude || '',
        longitude: branch?.longitude || '',
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

    // Estados y ciudades cargados desde GeoNames
    const [states, setStates] = useState([]);
    const [cities, setCities] = useState([]);
    const [loadingStates, setLoadingStates] = useState(false);
    const [loadingCities, setLoadingCities] = useState(false);
    const [selectedStateId, setSelectedStateId] = useState(null);
    const [geoAvailable, setGeoAvailable] = useState(true);

    // Cargar estados cuando cambia el país
    useEffect(() => {
        if (!data.country) return;

        setLoadingStates(true);
        setStates([]);
        setCities([]);

        fetch(`/api/geo/states/${data.country}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(res => {
                if (!res.ok) throw new Error('API no disponible');
                return res.json();
            })
            .then(data => {
                if (Array.isArray(data)) {
                    setStates(data);
                    setGeoAvailable(true);
                } else {
                    setGeoAvailable(false);
                }
            })
            .catch(() => setGeoAvailable(false))
            .finally(() => setLoadingStates(false));
    }, [data.country]);

    // Cargar ciudades cuando se selecciona un estado
    useEffect(() => {
        if (!selectedStateId || !data.country) return;

        setLoadingCities(true);
        setCities([]);

        fetch(`/api/geo/cities/${data.country}/${selectedStateId}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(res => {
                if (!res.ok) throw new Error('API no disponible');
                return res.json();
            })
            .then(data => {
                if (Array.isArray(data)) setCities(data);
            })
            .catch(() => {})
            .finally(() => setLoadingCities(false));
    }, [selectedStateId, data.country]);

    const handleCountryChange = (countryCode) => {
        setData(prev => ({
            ...prev,
            country: countryCode,
            state: '',
            city: '',
            latitude: '',
            longitude: '',
        }));
        setSelectedStateId(null);
    };

    const handleStateSelect = (stateOption) => {
        setData(prev => ({
            ...prev,
            state: stateOption.name,
            city: '',
            latitude: '',
            longitude: '',
        }));
        setSelectedStateId(stateOption.id);
    };

    const handleCitySelect = (cityOption) => {
        setData(prev => ({
            ...prev,
            city: cityOption.name,
            latitude: cityOption.lat || prev.latitude,
            longitude: cityOption.lng || prev.longitude,
        }));
    };

    const toggleDay = (dayKey) => {
        const hours = { ...data.operating_hours };
        if (hours[dayKey]) delete hours[dayKey];
        else hours[dayKey] = { ...DEFAULT_HOURS };
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
    const currentCountry = COUNTRIES.find(c => c.code === data.country);

    return (
        <AuthenticatedLayout>
            <Head title={isEdit ? `Editar: ${branch.name}` : 'Nueva Sucursal'} />
            <div className="t-page-shell" style={{ padding: T.pagePadding, background: T.bg, minHeight: '100vh', fontFamily: T.font, color: T.text }}>
                <div style={{ maxWidth: 780, margin: '0 auto' }}>

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

                <form onSubmit={submit}>

                    {/* Status Toggle */}
                    {isEdit && (
                        <div className="t-fade-up t-stagger-1" style={{
                            display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                            background: data.is_active ? `color-mix(in srgb, ${T.green} 5%, transparent)` : `color-mix(in srgb, ${T.red} 5%, transparent)`,
                            border: `1px solid color-mix(in srgb, ${data.is_active ? T.green : T.red} 15%, transparent)`,
                            borderRadius: 14, padding: '16px 20px', marginBottom: 20,
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
                                background: data.is_active ? T.green : T.border, position: 'relative', transition: 'background 0.3s',
                            }}>
                                <div style={{ width: 22, height: 22, borderRadius: '50%', background: '#fff', position: 'absolute', top: 3, left: data.is_active ? 27 : 3, transition: 'left 0.3s', boxShadow: '0 1px 3px rgba(0,0,0,0.2)' }} />
                            </button>
                        </div>
                    )}

                    {/* Identification */}
                    <Card className="t-fade-up t-stagger-2" accent={T.blue} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Identificación</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 200px', gap: 16, marginBottom: 16 }}>
                            <Field label="Nombre" error={errors.name}>
                                <input style={inputStyle} value={data.name} onChange={e => setData('name', e.target.value)} placeholder="Sucursal Centro" />
                            </Field>
                            <Field label="Código" error={errors.code}>
                                <input style={{ ...inputStyle, fontFamily: T.mono, fontWeight: 700, textTransform: 'uppercase', textAlign: 'center' }}
                                    value={data.code} onChange={e => setData('code', e.target.value.toUpperCase())} placeholder="CTR" maxLength={10} />
                            </Field>
                        </div>
                        <Field label="Dirección" error={errors.address}>
                            <input style={inputStyle} value={data.address} onChange={e => setData('address', e.target.value)} placeholder="Calle, Número, Colonia" />
                        </Field>
                    </Card>

                    {/* Ubicación (GeoNames) */}
                    <Card className="t-fade-up t-stagger-2b" accent={T.cyan || T.blue} style={{ marginBottom: 16 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 }}>
                            <div>
                                <div style={{ fontSize: 15, fontWeight: 700 }}>Ubicación</div>
                                <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                                    País, estado y ciudad — se usa para el clima y la zona horaria
                                </div>
                            </div>
                            {!geoAvailable && (
                                <div style={{
                                    fontSize: 10, padding: '4px 10px', borderRadius: 6,
                                    background: `color-mix(in srgb, ${T.amber} 10%, transparent)`,
                                    color: T.amber, fontWeight: 600,
                                }}>
                                    Modo manual
                                </div>
                            )}
                        </div>

                        {/* País */}
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr', gap: 16, marginBottom: 16 }}>
                            <Field label="País">
                                <select style={inputStyle} value={data.country} onChange={e => handleCountryChange(e.target.value)}>
                                    {COUNTRIES.map(c => (
                                        <option key={c.code} value={c.code}>{c.flag} {c.name}</option>
                                    ))}
                                </select>
                            </Field>
                        </div>

                        {/* Estado y Ciudad — con autocompletado si GeoNames disponible */}
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <Field label="Estado / Provincia" hint={geoAvailable ? 'Selecciona de la lista' : 'Escribe manualmente'}>
                                {geoAvailable ? (
                                    <SearchableSelect
                                        value={data.state}
                                        onChange={handleStateSelect}
                                        options={states}
                                        loading={loadingStates}
                                        placeholder="Seleccionar estado..."
                                    />
                                ) : (
                                    <input style={inputStyle} value={data.state}
                                        onChange={e => setData('state', e.target.value)}
                                        placeholder="Ej: Puebla" />
                                )}
                            </Field>

                            <Field label="Ciudad" hint={geoAvailable && selectedStateId ? 'Selecciona de la lista' : geoAvailable ? 'Primero selecciona un estado' : 'Escribe manualmente'}>
                                {geoAvailable && selectedStateId ? (
                                    <SearchableSelect
                                        value={data.city}
                                        onChange={handleCitySelect}
                                        options={cities}
                                        loading={loadingCities}
                                        placeholder="Seleccionar ciudad..."
                                    />
                                ) : geoAvailable ? (
                                    <SearchableSelect
                                        value={data.city}
                                        onChange={() => {}}
                                        options={[]}
                                        loading={false}
                                        placeholder="Selecciona un estado primero..."
                                        disabled
                                    />
                                ) : (
                                    <input style={inputStyle} value={data.city}
                                        onChange={e => setData('city', e.target.value)}
                                        placeholder="Ej: Puebla" />
                                )}
                            </Field>
                        </div>

                        {/* Coordenadas (auto-llenadas al seleccionar ciudad, o manuales) */}
                        {(data.latitude || data.longitude) && (
                            <div style={{
                                marginTop: 12, padding: '8px 12px', borderRadius: 8,
                                background: T.surface, fontSize: 11, color: T.textMuted,
                                fontFamily: T.mono, display: 'flex', gap: 16,
                            }}>
                                <span>📍 Lat: {data.latitude}</span>
                                <span>Lon: {data.longitude}</span>
                            </div>
                        )}
                    </Card>

                    {/* Contacto */}
                    <Card className="t-fade-up t-stagger-2c" accent={T.green} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Contacto</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <Field label="Teléfono"><input style={inputStyle} value={data.phone} onChange={e => setData('phone', e.target.value)} type="tel" placeholder="+52 222..." /></Field>
                            <Field label="Email"><input style={inputStyle} value={data.email} onChange={e => setData('email', e.target.value)} type="email" placeholder="sucursal@ejemplo.com" /></Field>
                        </div>
                    </Card>

                    {/* Operating Hours */}
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
                                <optgroup label="México">
                                    <option value="America/Mexico_City">CDMX (CST)</option>
                                    <option value="America/Monterrey">Monterrey (CST)</option>
                                    <option value="America/Cancun">Cancún (EST)</option>
                                    <option value="America/Tijuana">Tijuana (PST)</option>
                                    <option value="America/Hermosillo">Hermosillo (MST)</option>
                                    <option value="America/Mazatlan">Mazatlán (MST)</option>
                                    <option value="America/Chihuahua">Chihuahua (MST)</option>
                                    <option value="America/Merida">Mérida (CST)</option>
                                </optgroup>
                                <optgroup label="Centroamérica y Caribe">
                                    <option value="America/Guatemala">Guatemala (CST)</option>
                                    <option value="America/Costa_Rica">Costa Rica (CST)</option>
                                    <option value="America/Panama">Panamá (EST)</option>
                                    <option value="America/El_Salvador">El Salvador (CST)</option>
                                    <option value="America/Tegucigalpa">Honduras (CST)</option>
                                    <option value="America/Managua">Nicaragua (CST)</option>
                                    <option value="America/Santo_Domingo">Rep. Dominicana (AST)</option>
                                </optgroup>
                                <optgroup label="Sudamérica">
                                    <option value="America/Bogota">Colombia (COT)</option>
                                    <option value="America/Lima">Perú (PET)</option>
                                    <option value="America/Santiago">Chile (CLT)</option>
                                    <option value="America/Argentina/Buenos_Aires">Argentina (ART)</option>
                                    <option value="America/Guayaquil">Ecuador (ECT)</option>
                                    <option value="America/La_Paz">Bolivia (BOT)</option>
                                    <option value="America/Asuncion">Paraguay (PYT)</option>
                                    <option value="America/Montevideo">Uruguay (UYT)</option>
                                    <option value="America/Caracas">Venezuela (VET)</option>
                                    <option value="America/Sao_Paulo">Brasil (BRT)</option>
                                </optgroup>
                                <optgroup label="Norteamérica">
                                    <option value="America/New_York">Este EEUU (EST)</option>
                                    <option value="America/Chicago">Centro EEUU (CST)</option>
                                    <option value="America/Denver">Montaña EEUU (MST)</option>
                                    <option value="America/Los_Angeles">Pacífico EEUU (PST)</option>
                                </optgroup>
                                <optgroup label="Europa">
                                    <option value="Europe/Madrid">España (CET)</option>
                                    <option value="Europe/London">Reino Unido (GMT)</option>
                                </optgroup>
                            </select>
                        </div>

                        <div style={{ display: 'flex', gap: 6, marginBottom: 16 }}>
                            {DAYS.map(day => {
                                const active = !!data.operating_hours[day.key];
                                return (
                                    <button key={day.key} type="button" onClick={() => toggleDay(day.key)} style={{
                                        flex: 1, padding: '10px 4px', borderRadius: 10, border: `1px solid ${active ? T.amber : T.border}`,
                                        background: active ? `color-mix(in srgb, ${T.amber} 8%, transparent)` : T.surface,
                                        color: active ? T.amber : T.textMuted,
                                        cursor: 'pointer', fontSize: 12, fontWeight: 700, fontFamily: T.font, textAlign: 'center',
                                    }}>
                                        <div style={{ fontSize: 14, fontWeight: 800 }}>{day.short}</div>
                                        <div style={{ fontSize: 8, marginTop: 2, opacity: 0.7 }}>{day.label.substring(0, 3)}</div>
                                    </button>
                                );
                            })}
                        </div>

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

                    {/* Capacity */}
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

                        <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
                            {[
                                { key: 'accepts_walkins', label: 'Acepta turnos sin cita', desc: 'Clientes pueden tomar turno desde el kiosco', color: T.green },
                                { key: 'accepts_appointments', label: 'Acepta citas', desc: 'Permite agendar citas programadas', color: T.blue },
                            ].map(opt => (
                                <div key={opt.key} style={{
                                    flex: '1 1 260px', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    background: T.surface, borderRadius: 10, padding: '14px 16px',
                                    border: `1px solid ${data[opt.key] ? `color-mix(in srgb, ${opt.color} 20%, transparent)` : T.border}`,
                                }}>
                                    <div>
                                        <div style={{ fontSize: 13, fontWeight: 600, color: data[opt.key] ? T.text : T.textMuted }}>{opt.label}</div>
                                        <div style={{ fontSize: 10, color: T.textMuted, marginTop: 2 }}>{opt.desc}</div>
                                    </div>
                                    <button type="button" onClick={() => setData(opt.key, !data[opt.key])} style={{
                                        width: 44, height: 24, borderRadius: 12, border: 'none', cursor: 'pointer', flexShrink: 0,
                                        background: data[opt.key] ? opt.color : T.border, position: 'relative', transition: 'background 0.3s',
                                    }}>
                                        <div style={{ width: 18, height: 18, borderRadius: '50%', background: '#fff', position: 'absolute', top: 3, left: data[opt.key] ? 23 : 3, transition: 'left 0.3s', boxShadow: '0 1px 3px rgba(0,0,0,0.2)' }} />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </Card>

                    {/* Actions */}
                    <div className="t-fade-up t-stagger-5" style={{ display: 'flex', gap: 12, marginTop: 24 }}>
                        <Btn type="submit" variant="primary" size="lg" disabled={processing}>
                            {processing ? 'Guardando...' : isEdit ? '◆ Actualizar Sucursal' : '◆ Crear Sucursal'}
                        </Btn>
                        <Link href={route('admin.sucursales.index')}><Btn variant="ghost" size="lg">Cancelar</Btn></Link>
                    </div>
                </form>

                </div>{/* end maxWidth */}
            </div>
        </AuthenticatedLayout>
    );
}
