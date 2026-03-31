// resources/js/Pages/Admin/Users/Form.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { PageHeader, Input, Select, Btn, Card, theme } from '@/Components/TurnosUI';

export default function UserForm({ user, roles, branches }) {
    const isEdit = !!user;
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name || '', email: user?.email || '', phone: user?.phone || '',
        password: '', password_confirmation: '',
        role: user?.role || 'operator', is_active: user?.is_active ?? true,
        branch_ids: user?.branch_ids || [],
    });

    const submit = (e) => { e.preventDefault(); isEdit ? put(route('admin.usuarios.update', user.id)) : post(route('admin.usuarios.store')); };
    const toggleBranch = (id) => {
        setData('branch_ids', data.branch_ids.includes(id) ? data.branch_ids.filter(b => b !== id) : [...data.branch_ids, id]);
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">{isEdit ? 'Editar' : 'Nuevo'} Usuario</h2>}>
            <Head title={isEdit ? 'Editar Usuario' : 'Nuevo Usuario'} />
            <div style={{ padding: '24px 32px', background: theme.bg, minHeight: '100vh' }}>
                <PageHeader title={isEdit ? `Editar: ${user.name}` : 'Nuevo Usuario'} actions={<Link href={route('admin.usuarios.index')}><Btn variant="ghost">← Volver</Btn></Link>} />
                <form onSubmit={submit}>
                    <Card style={{ maxWidth: 600, marginBottom: 20 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 16, color: theme.textPrimary }}>Datos Personales</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <Input label="Nombre completo" value={data.name} onChange={e => setData('name', e.target.value)} error={errors.name} required />
                            <Input label="Email" type="email" value={data.email} onChange={e => setData('email', e.target.value)} error={errors.email} required />
                            <Input label="Teléfono" value={data.phone} onChange={e => setData('phone', e.target.value)} />
                            <Select label="Rol" value={data.role} onChange={e => setData('role', e.target.value)} error={errors.role}
                                options={roles.map(r => ({ value: r.value, label: r.label }))} />
                            <Input label={isEdit ? "Nueva contraseña (dejar vacío para no cambiar)" : "Contraseña"} type="password" value={data.password} onChange={e => setData('password', e.target.value)} error={errors.password} {...(!isEdit && { required: true })} />
                            <Input label="Confirmar contraseña" type="password" value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)} />
                        </div>
                    </Card>
                    <Card style={{ maxWidth: 600, marginBottom: 20 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 16, color: theme.textPrimary }}>Sucursales Asignadas</div>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                            {branches.map(b => (
                                <button key={b.id} type="button" onClick={() => toggleBranch(b.id)} style={{
                                    padding: '8px 16px', borderRadius: 8, fontSize: 13, fontWeight: 500, cursor: 'pointer', transition: 'all 0.15s', fontFamily: 'inherit',
                                    background: data.branch_ids.includes(b.id) ? theme.accentSoft : theme.bg,
                                    color: data.branch_ids.includes(b.id) ? theme.accent : theme.textMuted,
                                    border: `1px solid ${data.branch_ids.includes(b.id) ? theme.accent : theme.border}`,
                                }}>{b.name} ({b.code})</button>
                            ))}
                        </div>
                    </Card>
                    <div style={{ display: 'flex', gap: 12 }}>
                        <Btn type="submit" variant="primary" size="lg" disabled={processing}>{processing ? 'Guardando...' : isEdit ? 'Actualizar' : 'Crear Usuario'}</Btn>
                        <Link href={route('admin.usuarios.index')}><Btn variant="ghost" size="lg">Cancelar</Btn></Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
