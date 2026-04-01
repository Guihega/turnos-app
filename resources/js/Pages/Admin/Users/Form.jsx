// resources/js/Pages/Admin/Users/Form.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { Btn, Card, T, theme } from '@/Components/TurnosUI';

const ROLE_COLORS = {
    tenant_admin: T.red, branch_manager: T.amber, operator: T.blue, receptionist: T.green, viewer: T.textMuted,
};

const inputStyle = {
    width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
    borderRadius: 10, padding: '12px 14px', fontSize: 14, outline: 'none', fontFamily: T.font,
};
const labelStyle = { fontSize: 11, fontWeight: 700, color: T.textSoft, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6 };

export default function UserForm({ user, roles = [], branches = [] }) {
    const isEdit = !!user;
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name || '', email: user?.email || '', phone: user?.phone || '',
        password: '', password_confirmation: '',
        role: user?.role || 'operator', is_active: user?.is_active ?? true,
        branch_ids: user?.branch_ids || [],
    });

    const submit = (e) => { e.preventDefault(); isEdit ? put(route('admin.usuarios.update', user.id)) : post(route('admin.usuarios.store')); };
    const toggleBranch = (id) => setData('branch_ids', data.branch_ids.includes(id) ? data.branch_ids.filter(b => b !== id) : [...data.branch_ids, id]);
    const roleColor = ROLE_COLORS[data.role] || T.blue;

    return (
        <AuthenticatedLayout>
            <Head title={isEdit ? `Editar: ${user.name}` : 'Nuevo Usuario'} />
            <div style={{ padding: '24px 28px', background: T.bg, minHeight: '100vh', fontFamily: T.font, color: T.text }}>

                <div className="t-fade-up" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 28 }}>
                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 900, margin: 0, letterSpacing: '-0.02em' }}>{isEdit ? `Editar: ${user.name}` : 'Nuevo Usuario'}</h1>
                        <p style={{ fontSize: 13, color: T.textMuted, margin: '4px 0 0' }}>Operadores, recepcionistas y administradores</p>
                    </div>
                    <Link href={route('admin.usuarios.index')}><Btn variant="ghost">← Volver</Btn></Link>
                </div>

                <form onSubmit={submit} style={{ maxWidth: 680 }}>

                    {/* Status */}
                    {isEdit && (
                        <div className="t-fade-up t-stagger-1" style={{
                            display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                            background: data.is_active ? `${T.green}08` : `${T.red}08`,
                            border: `1px solid ${data.is_active ? T.green : T.red}20`,
                            borderRadius: 14, padding: '14px 18px', marginBottom: 16,
                        }}>
                            <div>
                                <div style={{ fontSize: 13, fontWeight: 700, color: data.is_active ? T.green : T.red }}>{data.is_active ? '◆ Usuario Activo' : '◇ Usuario Inactivo'}</div>
                                <div style={{ fontSize: 11, color: T.textMuted }}>{data.is_active ? 'Puede iniciar sesión y operar' : 'Acceso deshabilitado'}</div>
                            </div>
                            <button type="button" onClick={() => setData('is_active', !data.is_active)} style={{
                                width: 48, height: 26, borderRadius: 13, border: 'none', cursor: 'pointer',
                                background: data.is_active ? T.green : T.border, position: 'relative', transition: 'background 0.3s',
                            }}>
                                <div style={{ width: 20, height: 20, borderRadius: '50%', background: '#fff', position: 'absolute', top: 3, left: data.is_active ? 25 : 3, transition: 'left 0.3s', boxShadow: '0 1px 3px rgba(0,0,0,0.2)' }} />
                            </button>
                        </div>
                    )}

                    {/* Personal info */}
                    <Card className="t-fade-up t-stagger-2" accent={T.blue} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Datos Personales</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <div>
                                <label style={labelStyle}>Nombre completo</label>
                                <input style={inputStyle} value={data.name} onChange={e => setData('name', e.target.value)} required placeholder="Juan Pérez" />
                                {errors.name && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.name}</div>}
                            </div>
                            <div>
                                <label style={labelStyle}>Email</label>
                                <input type="email" style={inputStyle} value={data.email} onChange={e => setData('email', e.target.value)} required placeholder="juan@ejemplo.com" />
                                {errors.email && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.email}</div>}
                            </div>
                            <div>
                                <label style={labelStyle}>Teléfono</label>
                                <input style={inputStyle} value={data.phone} onChange={e => setData('phone', e.target.value)} placeholder="+52 222..." />
                            </div>
                        </div>
                    </Card>

                    {/* Role */}
                    <Card className="t-fade-up t-stagger-3" accent={roleColor} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 18 }}>Rol del Usuario</div>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(140px, 1fr))', gap: 8 }}>
                            {(Array.isArray(roles) ? roles : []).map(r => {
                                const rc = ROLE_COLORS[r.value] || T.blue;
                                const selected = data.role === r.value;
                                return (
                                    <button key={r.value} type="button" onClick={() => setData('role', r.value)} style={{
                                        padding: '14px 12px', borderRadius: 12, cursor: 'pointer', textAlign: 'center',
                                        background: selected ? `${rc}15` : T.surface,
                                        border: `1px solid ${selected ? rc : T.border}`,
                                        transition: 'all 0.2s', color: T.text, fontFamily: T.font,
                                    }}>
                                        <div style={{ fontSize: 13, fontWeight: 700, color: selected ? rc : T.text }}>{r.label}</div>
                                    </button>
                                );
                            })}
                        </div>
                        {errors.role && <div style={{ fontSize: 11, color: T.red, marginTop: 8 }}>{errors.role}</div>}
                    </Card>

                    {/* Password */}
                    <Card className="t-fade-up t-stagger-4" accent={T.purple} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 4 }}>Contraseña</div>
                        {isEdit && <div style={{ fontSize: 11, color: T.textMuted, marginBottom: 16 }}>Dejar vacío para mantener la actual</div>}
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <div>
                                <label style={labelStyle}>{isEdit ? 'Nueva contraseña' : 'Contraseña'}</label>
                                <input type="password" style={inputStyle} value={data.password} onChange={e => setData('password', e.target.value)} {...(!isEdit && { required: true })} placeholder="Mínimo 8 caracteres" />
                                {errors.password && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.password}</div>}
                            </div>
                            <div>
                                <label style={labelStyle}>Confirmar</label>
                                <input type="password" style={inputStyle} value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)} placeholder="Repetir contraseña" />
                            </div>
                        </div>
                    </Card>

                    {/* Branches */}
                    <Card className="t-fade-up t-stagger-5" accent={T.green} style={{ marginBottom: 16 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 }}>
                            <div>
                                <div style={{ fontSize: 15, fontWeight: 700 }}>Sucursales Asignadas</div>
                                <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>{data.branch_ids.length} seleccionada{data.branch_ids.length !== 1 ? 's' : ''}</div>
                            </div>
                            <button type="button" onClick={() => setData('branch_ids', data.branch_ids.length === branches.length ? [] : branches.map(b => b.id))}
                                style={{ background: 'none', border: `1px solid ${T.border}`, borderRadius: 8, padding: '6px 12px', cursor: 'pointer', color: T.textMuted, fontSize: 11, fontFamily: T.font }}>
                                {data.branch_ids.length === branches.length ? 'Ninguna' : 'Todas'}
                            </button>
                        </div>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                            {branches.map(b => {
                                const selected = data.branch_ids.includes(b.id);
                                return (
                                    <button key={b.id} type="button" onClick={() => toggleBranch(b.id)} style={{
                                        display: 'flex', alignItems: 'center', gap: 8, padding: '10px 16px', borderRadius: 10,
                                        background: selected ? `${T.green}15` : T.surface,
                                        border: `1px solid ${selected ? T.green : T.border}`,
                                        cursor: 'pointer', transition: 'all 0.2s', color: T.text, fontFamily: T.font,
                                    }}>
                                        <span style={{ fontSize: 13, fontWeight: selected ? 700 : 500 }}>{b.name}</span>
                                        <span style={{ fontSize: 10, fontFamily: T.mono, color: T.textMuted }}>{b.code}</span>
                                        {selected && <span style={{ fontSize: 12, color: T.green }}>✓</span>}
                                    </button>
                                );
                            })}
                        </div>
                    </Card>

                    <div className="t-fade-up t-stagger-6" style={{ display: 'flex', gap: 12 }}>
                        <Btn type="submit" variant="primary" size="lg" disabled={processing}>
                            {processing ? 'Guardando...' : isEdit ? '◆ Actualizar Usuario' : '◆ Crear Usuario'}
                        </Btn>
                        <Link href={route('admin.usuarios.index')}><Btn variant="ghost" size="lg">Cancelar</Btn></Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
