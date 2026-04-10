// resources/js/Pages/Admin/Services/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, Badge, FlashMessages, T } from '@/Components/TurnosUI';

export default function ServicesIndex({ services }) {
    const { flash } = usePage().props;

    const columns = [
        { key: 'color', label: '', render: r => (
            <div style={{ width: 10, height: 10, borderRadius: '50%', background: r.color, boxShadow: `0 0 6px ${r.color}40` }} />
        )},
        { key: 'name', label: 'Servicio', render: r => <span style={{ fontWeight: 600 }}>{r.name}</span> },
        { key: 'code', label: 'Código', render: r => (
            <span style={{ fontFamily: T.mono, fontSize: 11, fontWeight: 600, color: T.blue }}>{r.code}</span>
        )},
        { key: 'estimated_duration_minutes', label: 'Duración', render: r => (
            <span style={{ fontFamily: T.mono, fontSize: 12 }}>{r.estimated_duration_minutes} min</span>
        )},
        { key: 'requires_appointment', label: 'Cita', render: r => (
            r.requires_appointment
                ? <Badge color={T.purple}>Requerida</Badge>
                : <span style={{ color: T.textMuted, fontSize: 12 }}>No</span>
        )},
        { key: 'is_active', label: 'Estado', render: r => (
            <Badge color={r.is_active ? T.green : T.red}>{r.is_active ? '● Activo' : '○ Inactivo'}</Badge>
        )},
        { key: 'actions', label: '', align: 'right', render: r => (
            <Link href={route('admin.servicios.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link>
        )},
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Servicios" />
            <div className="t-page-shell" style={{ padding: T.pagePadding, background: T.bg, minHeight: '100vh', fontFamily: T.font }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>
                    <FlashMessages flash={flash} />
                    <PageHeader
                        title="Servicios"
                        subtitle="Catálogo de servicios que ofrece tu organización"
                        actions={<Link href={route('admin.servicios.create')}><Btn variant="primary">+ Nuevo Servicio</Btn></Link>}
                    />
                    <DataTable
                        columns={columns}
                        rows={services}
                        emptyIcon="⬡"
                        emptyTitle="Sin servicios"
                        emptySubtitle="Agrega los servicios que ofrece tu organización"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
