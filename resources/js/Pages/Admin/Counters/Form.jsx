// resources/js/Pages/Admin/Counters/Form.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { Btn, Card, T, theme } from '@/Components/TurnosUI';

const STATUSES = [
    { value: 'closed', label: 'Cerrada', color: T.textMuted, icon: '◇', desc: 'No disponible para atención' },
    { value: 'open', label: 'Abierta', color: T.green, icon: '◆', desc: 'Lista para recibir turnos' },
    { value: 'serving', label: 'Atendiendo', color: T.blue, icon: '◉', desc: 'Actualmente sirviendo un turno' },
    { value: 'paused', label: 'Pausada', color: T.amber, icon: '◷', desc: 'Temporalmente sin atención' },
];

const inputStyle = {
    width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
    borderRadius: 10, padding: '12px 14px', fontSize: 14, outline: 'none', fontFamily: T.font,
};
const labelStyle = { fontSize: 11, fontWeight: 700, color: T.textSoft, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6 };

export default function CounterForm({ counter, branches = [] }) {
    const isEdit = !!counter;
    const { data, setData, post, put, processing, errors } = useForm({
        branch_id: counter?.branch_id || branches[0]?.id || '',
        name: counter?.name || '',
        number: counter?.number || '',
        status: counter?.status || 'closed',
    });

    const submit = (e) => {
        e.preventDefault();
        isEdit ? put(route('admin.ventanillas.update', counter.id)) : post(route('admin.ventanillas.store'));
    };

    const currentStatus = STATUSES.find(s => s.value === data.status) || STATUSES[0];

    return (
        <AuthenticatedLayout>
            <Head title={isEdit ? `Editar: ${counter.name}` : 'Nueva Ventanilla'} />
            <div style={{ padding: '24px 28px', background: T.bg, minHeight: '100vh', fontFamily: T.font, color: T.text }}>

                <div className="t-fade-up" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 28 }}>
                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 900, margin: 0, letterSpacing: '-0.02em' }}>
                            {isEdit ? `Editar: ${counter.name}` : 'Nueva Ventanilla'}
                        </h1>
                        <p style={{ fontSize: 13, color: T.textMuted, margin: '4px 0 0' }}>Punto de atención al cliente</p>
                    </div>
                    <Link href={route('admin.ventanillas.index')}><Btn variant="ghost">← Volver</Btn></Link>
                </div>

                <form onSubmit={submit} style={{ maxWidth: 600 }}>

                    {/* Identity */}
                    <Card className="t-fade-up t-stagger-1" accent={T.purple} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Identificación</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 100px', gap: 16 }}>
                            {!isEdit && (
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <label style={labelStyle}>Sucursal</label>
                                    <select value={data.branch_id} onChange={e => setData('branch_id', e.target.value)} style={{ ...inputStyle, cursor: 'pointer' }}>
                                        {branches.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
                                    </select>
                                </div>
                            )}
                            <div>
                                <label style={labelStyle}>Nombre</label>
                                <input style={inputStyle} value={data.name} onChange={e => setData('name', e.target.value)} required placeholder="Ej: Ventanilla 1" />
                                {errors.name && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.name}</div>}
                            </div>
                            <div>
                                <label style={labelStyle}>Número</label>
                                <input style={{ ...inputStyle, fontFamily: T.mono, fontWeight: 800, textAlign: 'center', fontSize: 24 }}
                                    value={data.number} onChange={e => setData('number', e.target.value)} required maxLength={10} placeholder="1" />
                                {errors.number && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.number}</div>}
                            </div>
                        </div>

                        {/* Preview */}
                        <div style={{ marginTop: 20, background: T.surface, borderRadius: 12, padding: 16, display: 'flex', alignItems: 'center', gap: 16 }}>
                            <div style={{ width: 56, height: 56, borderRadius: 14, background: `${currentStatus.color}15`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 28, fontWeight: 900, color: currentStatus.color, fontFamily: T.mono }}>
                                {data.number || '?'}
                            </div>
                            <div>
                                <div style={{ fontSize: 15, fontWeight: 700 }}>{data.name || 'Ventanilla'}</div>
                                <div style={{ fontSize: 12, color: currentStatus.color, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 4 }}>
                                    <span style={{ width: 6, height: 6, borderRadius: '50%', background: currentStatus.color }} />
                                    {currentStatus.label}
                                </div>
                            </div>
                        </div>
                    </Card>

                    {/* Status */}
                    {isEdit && (
                        <Card className="t-fade-up t-stagger-2" accent={currentStatus.color} style={{ marginBottom: 16 }}>
                            <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Estado de la Ventanilla</div>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 10 }}>
                                {STATUSES.filter(s => s.value !== 'serving').map(st => (
                                    <button key={st.value} type="button" onClick={() => setData('status', st.value)} style={{
                                        display: 'flex', alignItems: 'center', gap: 10, padding: '14px 16px', borderRadius: 12,
                                        background: data.status === st.value ? `${st.color}12` : T.surface,
                                        border: `1px solid ${data.status === st.value ? st.color : T.border}`,
                                        cursor: 'pointer', transition: 'all 0.2s', color: T.text, fontFamily: T.font, textAlign: 'left',
                                    }}>
                                        <span style={{ fontSize: 20, color: data.status === st.value ? st.color : T.textMuted }}>{st.icon}</span>
                                        <div>
                                            <div style={{ fontSize: 13, fontWeight: 700, color: data.status === st.value ? st.color : T.text }}>{st.label}</div>
                                            <div style={{ fontSize: 10, color: T.textMuted }}>{st.desc}</div>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </Card>
                    )}

                    <div className="t-fade-up t-stagger-3" style={{ display: 'flex', gap: 12 }}>
                        <Btn type="submit" variant="primary" size="lg" disabled={processing}>
                            {processing ? 'Guardando...' : isEdit ? '◆ Actualizar' : '◆ Crear Ventanilla'}
                        </Btn>
                        <Link href={route('admin.ventanillas.index')}><Btn variant="ghost" size="lg">Cancelar</Btn></Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
