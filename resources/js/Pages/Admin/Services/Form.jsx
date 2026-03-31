// resources/js/Pages/Admin/Services/Form.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { PageHeader, Input, Btn, Card, theme } from '@/Components/TurnosUI';

export default function ServiceForm({ service }) {
    const isEdit = !!service;
    const { data, setData, post, put, processing, errors } = useForm({
        name: service?.name || '', code: service?.code || '', color: service?.color || '#3B82F6',
        description: service?.description || '', estimated_duration_minutes: service?.estimated_duration_minutes || 15,
        requires_appointment: service?.requires_appointment ?? false, is_active: service?.is_active ?? true,
    });

    const submit = (e) => { e.preventDefault(); isEdit ? put(route('admin.servicios.update', service.id)) : post(route('admin.servicios.store')); };
    const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#F97316'];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">{isEdit ? 'Editar' : 'Nuevo'} Servicio</h2>}>
            <Head title={isEdit ? 'Editar Servicio' : 'Nuevo Servicio'} />
            <div style={{ padding: '24px 32px', background: theme.bg, minHeight: '100vh' }}>
                <PageHeader title={isEdit ? `Editar: ${service.name}` : 'Nuevo Servicio'} actions={<Link href={route('admin.servicios.index')}><Btn variant="ghost">← Volver</Btn></Link>} />
                <form onSubmit={submit}>
                    <Card style={{ maxWidth: 600, marginBottom: 20 }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <Input label="Nombre" value={data.name} onChange={e => setData('name', e.target.value)} error={errors.name} required />
                            <Input label="Código" value={data.code} onChange={e => setData('code', e.target.value.toUpperCase())} error={errors.code} required maxLength={5} />
                            <div style={{ gridColumn: '1 / -1' }}>
                                <label style={{ fontSize: 11, fontWeight: 500, color: theme.textMuted, textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block', marginBottom: 6 }}>Color</label>
                                <div style={{ display: 'flex', gap: 8 }}>
                                    {colors.map(c => (
                                        <div key={c} onClick={() => setData('color', c)} style={{
                                            width: 32, height: 32, borderRadius: 8, background: c, cursor: 'pointer',
                                            border: data.color === c ? '3px solid #fff' : '3px solid transparent', transition: 'all 0.15s',
                                        }} />
                                    ))}
                                    <input type="color" value={data.color} onChange={e => setData('color', e.target.value)} style={{ width: 32, height: 32, border: 'none', borderRadius: 8, cursor: 'pointer' }} />
                                </div>
                            </div>
                            <Input label="Duración estimada (min)" type="number" value={data.estimated_duration_minutes} onChange={e => setData('estimated_duration_minutes', parseInt(e.target.value))} min={1} />
                            <div style={{ display: 'flex', alignItems: 'end' }}>
                                <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', fontSize: 13, color: theme.textSecondary }}>
                                    <input type="checkbox" checked={data.requires_appointment} onChange={e => setData('requires_appointment', e.target.checked)} style={{ width: 16, height: 16, accentColor: theme.accent }} />
                                    Requiere cita previa
                                </label>
                            </div>
                            <div style={{ gridColumn: '1 / -1' }}>
                                <label style={{ fontSize: 11, fontWeight: 500, color: theme.textMuted, textTransform: 'uppercase', letterSpacing: '0.05em', display: 'block', marginBottom: 4 }}>Descripción</label>
                                <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={3}
                                    style={{ width: '100%', background: theme.bg, color: theme.textPrimary, border: `1px solid ${theme.border}`, borderRadius: 8, padding: 10, fontSize: 13, fontFamily: 'inherit', resize: 'vertical' }} />
                            </div>
                        </div>
                    </Card>
                    <div style={{ display: 'flex', gap: 12 }}>
                        <Btn type="submit" variant="primary" size="lg" disabled={processing}>{processing ? 'Guardando...' : isEdit ? 'Actualizar' : 'Crear Servicio'}</Btn>
                        <Link href={route('admin.servicios.index')}><Btn variant="ghost" size="lg">Cancelar</Btn></Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
