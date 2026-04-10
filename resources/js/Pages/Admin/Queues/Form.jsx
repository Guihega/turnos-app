// resources/js/Pages/Admin/Queues/Form.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { Btn, Card, T } from '@/Components/TurnosUI';

const ALGORITHMS = [
    { value: 'fifo', label: 'FIFO', desc: 'Primero en llegar, primero en ser atendido', icon: '→' },
    { value: 'priority', label: 'Prioridad', desc: 'Atiende según nivel de prioridad del turno', icon: '↑' },
    { value: 'weighted_fair', label: 'Balanceado', desc: 'Equilibra prioridad con tiempo de espera', icon: '⇄' },
];

const inputStyle = {
    width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
    borderRadius: 10, padding: '12px 14px', fontSize: 14, outline: 'none', fontFamily: T.font,
};
const labelStyle = { fontSize: 11, fontWeight: 700, color: T.textSoft, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6 };

export default function QueueForm({ queue, branches = [], services = [] }) {
    const isEdit = !!queue;
    const { data, setData, post, put, processing, errors } = useForm({
        branch_id: queue?.branch_id || branches[0]?.id || '',
        name: queue?.name || '',
        prefix: queue?.prefix || '',
        priority_algorithm: queue?.priority_algorithm || 'fifo',
        max_capacity: queue?.max_capacity || 80,
        is_active: queue?.is_active ?? true,
        service_ids: queue?.service_ids || [],
    });

    const toggleService = (id) => {
        const ids = data.service_ids.includes(id) ? data.service_ids.filter(x => x !== id) : [...data.service_ids, id];
        setData('service_ids', ids);
    };

    const submit = (e) => { e.preventDefault(); isEdit ? put(route('admin.colas.update', queue.id)) : post(route('admin.colas.store')); };

    return (
        <AuthenticatedLayout>
            <Head title={isEdit ? `Editar: ${queue.name}` : 'Nueva Cola'} />
            <div className="t-page-shell" style={{ padding: T.pagePadding, background: T.bg, minHeight: '100vh', fontFamily: T.font, color: T.text }}>
                <div style={{ maxWidth: 700, margin: '0 auto' }}>

                <div className="t-fade-up" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 28 }}>
                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 900, margin: 0, letterSpacing: '-0.02em' }}>
                            {isEdit ? `Editar: ${queue.name}` : 'Nueva Cola'}
                        </h1>
                        <p style={{ fontSize: 13, color: T.textMuted, margin: '4px 0 0' }}>
                            {isEdit ? 'Modifica la configuración de la cola' : 'Configura una nueva fila de atención'}
                        </p>
                    </div>
                    <Link href={route('admin.colas.index')}><Btn variant="ghost">← Volver</Btn></Link>
                </div>

                <form onSubmit={submit}>

                    {/* Status */}
                    {isEdit && (
                        <div className="t-fade-up t-stagger-1" style={{
                            display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                            background: data.is_active ? `color-mix(in srgb, ${T.green} 5%, transparent)` : `color-mix(in srgb, ${T.red} 5%, transparent)`,
                            border: `1px solid color-mix(in srgb, ${data.is_active ? T.green : T.red} 15%, transparent)`,
                            borderRadius: 14, padding: '14px 18px', marginBottom: 16,
                        }}>
                            <div>
                                <div style={{ fontSize: 13, fontWeight: 700, color: data.is_active ? T.green : T.red }}>
                                    {data.is_active ? '◆ Cola Activa' : '◇ Cola Inactiva'}
                                </div>
                                <div style={{ fontSize: 11, color: T.textMuted }}>{data.is_active ? 'Recibiendo turnos' : 'No acepta turnos'}</div>
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
                    <Card className="t-fade-up t-stagger-2" accent={T.blue} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Identificación</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 100px', gap: 16 }}>
                            {!isEdit && (
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <label style={labelStyle}>Sucursal</label>
                                    <select value={data.branch_id} onChange={e => setData('branch_id', e.target.value)} style={{ ...inputStyle, cursor: 'pointer' }}>
                                        {branches.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
                                    </select>
                                    {errors.branch_id && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.branch_id}</div>}
                                </div>
                            )}
                            <div>
                                <label style={labelStyle}>Nombre</label>
                                <input style={inputStyle} value={data.name} onChange={e => setData('name', e.target.value)} required placeholder="Ej: Cola General" />
                                {errors.name && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.name}</div>}
                            </div>
                            <div>
                                <label style={labelStyle}>Prefijo</label>
                                <input style={{ ...inputStyle, fontFamily: T.mono, fontWeight: 800, textTransform: 'uppercase', textAlign: 'center', fontSize: 20 }}
                                    value={data.prefix} onChange={e => setData('prefix', e.target.value.toUpperCase())} required maxLength={3} placeholder="A" />
                                {errors.prefix && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.prefix}</div>}
                            </div>
                            <div>
                                <label style={labelStyle}>Capacidad</label>
                                <input type="number" style={{ ...inputStyle, fontFamily: T.mono, textAlign: 'center' }}
                                    value={data.max_capacity} onChange={e => setData('max_capacity', parseInt(e.target.value) || 0)} min={1} />
                            </div>
                        </div>
                    </Card>

                    {/* Algorithm */}
                    <Card className="t-fade-up t-stagger-3" accent={T.amber} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Algoritmo de Atención</div>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10 }}>
                            {ALGORITHMS.map(alg => (
                                <button key={alg.value} type="button" onClick={() => setData('priority_algorithm', alg.value)}
                                    style={{
                                        background: data.priority_algorithm === alg.value ? `color-mix(in srgb, ${T.amber} 8%, transparent)` : T.surface,
                                        border: `1px solid ${data.priority_algorithm === alg.value ? T.amber : T.border}`,
                                        borderRadius: 12, padding: '16px 14px', cursor: 'pointer', textAlign: 'left',
                                        transition: 'all 0.2s', color: T.text, fontFamily: T.font,
                                    }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                                        <span style={{ fontSize: 18, color: data.priority_algorithm === alg.value ? T.amber : T.textMuted }}>{alg.icon}</span>
                                        <span style={{ fontSize: 14, fontWeight: 700, color: data.priority_algorithm === alg.value ? T.amber : T.text }}>{alg.label}</span>
                                    </div>
                                    <div style={{ fontSize: 11, color: T.textMuted, lineHeight: 1.4 }}>{alg.desc}</div>
                                </button>
                            ))}
                        </div>
                    </Card>

                    {/* Services */}
                    <Card className="t-fade-up t-stagger-4" accent={T.green} style={{ marginBottom: 16 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 }}>
                            <div>
                                <div style={{ fontSize: 15, fontWeight: 700 }}>Servicios Asignados</div>
                                <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>{data.service_ids.length} seleccionado{data.service_ids.length !== 1 ? 's' : ''}</div>
                            </div>
                            <button type="button" onClick={() => setData('service_ids', data.service_ids.length === services.length ? [] : services.map(s => s.id))}
                                style={{ background: 'none', border: `1px solid ${T.border}`, borderRadius: 8, padding: '6px 12px', cursor: 'pointer', color: T.textMuted, fontSize: 11, fontFamily: T.font }}>
                                {data.service_ids.length === services.length ? 'Ninguno' : 'Todos'}
                            </button>
                        </div>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                            {services.map(s => {
                                const selected = data.service_ids.includes(s.id);
                                return (
                                    <button key={s.id} type="button" onClick={() => toggleService(s.id)} style={{
                                        display: 'flex', alignItems: 'center', gap: 8, padding: '10px 16px', borderRadius: 10,
                                        background: selected ? `color-mix(in srgb, ${s.color} 10%, transparent)` : T.surface,
                                        border: `1px solid ${selected ? s.color : T.border}`,
                                        cursor: 'pointer', transition: 'all 0.2s', color: T.text, fontFamily: T.font,
                                    }}>
                                        <span style={{ width: 10, height: 10, borderRadius: '50%', background: s.color, boxShadow: selected ? `0 0 8px ${s.color}50` : 'none' }} />
                                        <span style={{ fontSize: 13, fontWeight: selected ? 700 : 500 }}>{s.name}</span>
                                        {selected && <span style={{ fontSize: 12, color: T.green, marginLeft: 2 }}>✓</span>}
                                    </button>
                                );
                            })}
                        </div>
                    </Card>

                    {/* Actions */}
                    <div className="t-fade-up t-stagger-5" style={{ display: 'flex', gap: 12 }}>
                        <Btn type="submit" variant="primary" size="lg" disabled={processing}>
                            {processing ? 'Guardando...' : isEdit ? '◆ Actualizar Cola' : '◆ Crear Cola'}
                        </Btn>
                        <Link href={route('admin.colas.index')}><Btn variant="ghost" size="lg">Cancelar</Btn></Link>
                    </div>
                </form>

                </div>{/* end maxWidth */}
            </div>
        </AuthenticatedLayout>
    );
}
