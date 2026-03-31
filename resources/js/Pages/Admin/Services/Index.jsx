// resources/js/Pages/Admin/Services/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, FlashMessages, theme } from '@/Components/TurnosUI';

export default function ServicesIndex({ services }) {
    const { flash } = usePage().props;
    const columns = [
        { key: 'color', label: '', render: r => <div style={{ width: 12, height: 12, borderRadius: '50%', background: r.color }} /> },
        { key: 'name', label: 'Servicio', render: r => <span style={{ fontWeight: 600 }}>{r.name}</span> },
        { key: 'code', label: 'Código', render: r => <span style={{ fontFamily: 'monospace', fontSize: 12, color: theme.accent }}>{r.code}</span> },
        { key: 'estimated_duration_minutes', label: 'Duración', render: r => `${r.estimated_duration_minutes} min` },
        { key: 'requires_appointment', label: 'Cita requerida', render: r => r.requires_appointment ? 'Sí' : 'No' },
        { key: 'is_active', label: 'Estado', render: r => <span style={{ color: r.is_active ? theme.success : theme.danger, fontSize: 11, fontWeight: 600 }}>{r.is_active ? '● Activo' : '○ Inactivo'}</span> },
        { key: 'actions', label: '', render: r => <Link href={route('admin.servicios.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link> },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">Servicios</h2>}>
            <Head title="Servicios" />
            <div style={{ padding: '24px 32px', background: theme.bg, minHeight: '100vh' }}>
                <FlashMessages flash={flash} />
                <PageHeader title="Servicios" subtitle="Catálogo de servicios que ofrece tu organización"
                    actions={<Link href={route('admin.servicios.create')}><Btn variant="primary">+ Nuevo Servicio</Btn></Link>} />
                <DataTable columns={columns} rows={services} />
            </div>
        </AuthenticatedLayout>
    );
}
