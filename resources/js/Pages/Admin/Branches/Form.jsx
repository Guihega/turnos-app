// resources/js/Pages/Admin/Branches/Form.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { PageHeader, Input, Btn, Card, theme } from '@/Components/TurnosUI';

export default function BranchForm({ branch }) {
    const isEdit = !!branch;
    const { data, setData, post, put, processing, errors } = useForm({
        name: branch?.name || '',
        code: branch?.code || '',
        address: branch?.address || '',
        city: branch?.city || '',
        state: branch?.state || '',
        phone: branch?.phone || '',
        email: branch?.email || '',
        max_daily_tickets: branch?.max_daily_tickets || 500,
        max_concurrent_waiting: branch?.max_concurrent_waiting || 50,
        accepts_walkins: branch?.accepts_walkins ?? true,
        accepts_appointments: branch?.accepts_appointments ?? true,
        is_active: branch?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.sucursales.update', branch.id));
        } else {
            post(route('admin.sucursales.store'));
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">{isEdit ? 'Editar' : 'Nueva'} Sucursal</h2>}>
            <Head title={isEdit ? 'Editar Sucursal' : 'Nueva Sucursal'} />
            <div style={{ padding: '24px 32px', background: theme.bg, minHeight: '100vh' }}>
                <PageHeader title={isEdit ? `Editar: ${branch.name}` : 'Nueva Sucursal'}
                    actions={<Link href={route('admin.sucursales.index')}><Btn variant="ghost">← Volver</Btn></Link>} />

                <form onSubmit={submit}>
                    <Card style={{ maxWidth: 700, marginBottom: 20 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 16, color: theme.textPrimary }}>Información General</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <Input label="Nombre" value={data.name} onChange={e => setData('name', e.target.value)} error={errors.name} required />
                            <Input label="Código" value={data.code} onChange={e => setData('code', e.target.value.toUpperCase())} error={errors.code} required maxLength={10} placeholder="Ej: SUC1" />
                            <div style={{ gridColumn: '1 / -1' }}><Input label="Dirección" value={data.address} onChange={e => setData('address', e.target.value)} error={errors.address} /></div>
                            <Input label="Ciudad" value={data.city} onChange={e => setData('city', e.target.value)} />
                            <Input label="Estado" value={data.state} onChange={e => setData('state', e.target.value)} />
                            <Input label="Teléfono" value={data.phone} onChange={e => setData('phone', e.target.value)} />
                            <Input label="Email" value={data.email} onChange={e => setData('email', e.target.value)} type="email" />
                        </div>
                    </Card>

                    <Card style={{ maxWidth: 700, marginBottom: 20 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 16, color: theme.textPrimary }}>Configuración</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <Input label="Máx. turnos diarios" type="number" value={data.max_daily_tickets} onChange={e => setData('max_daily_tickets', parseInt(e.target.value))} />
                            <Input label="Máx. en espera simultáneo" type="number" value={data.max_concurrent_waiting} onChange={e => setData('max_concurrent_waiting', parseInt(e.target.value))} />
                        </div>
                        <div style={{ display: 'flex', gap: 24, marginTop: 16 }}>
                            {[
                                { key: 'accepts_walkins', label: 'Acepta turnos sin cita' },
                                { key: 'accepts_appointments', label: 'Acepta citas' },
                                isEdit && { key: 'is_active', label: 'Sucursal activa' },
                            ].filter(Boolean).map(opt => (
                                <label key={opt.key} style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', fontSize: 13, color: theme.textSecondary }}>
                                    <input type="checkbox" checked={data[opt.key]} onChange={e => setData(opt.key, e.target.checked)}
                                        style={{ width: 16, height: 16, accentColor: theme.accent }} />
                                    {opt.label}
                                </label>
                            ))}
                        </div>
                    </Card>

                    <div style={{ display: 'flex', gap: 12 }}>
                        <Btn type="submit" variant="primary" size="lg" disabled={processing}>
                            {processing ? 'Guardando...' : isEdit ? 'Actualizar Sucursal' : 'Crear Sucursal'}
                        </Btn>
                        <Link href={route('admin.sucursales.index')}><Btn variant="ghost" size="lg">Cancelar</Btn></Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
