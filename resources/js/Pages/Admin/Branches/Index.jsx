// resources/js/Pages/Admin/Branches/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, Badge, FlashMessages, T } from '@/Components/TurnosUI';

export default function BranchesIndex({ branches }) {
    const { flash } = usePage().props;

    const columns = [
        { key: 'name', label: 'Sucursal', render: r => <span style={{ fontWeight: 600 }}>{r.name}</span> },
        { key: 'code', label: 'Código', render: r => <Badge color={T.blue}>{r.code}</Badge> },
        { key: 'city', label: 'Ciudad' },
        { key: 'phone', label: 'Teléfono', render: r => (
            <span style={{ fontFamily: T.mono, fontSize: 12 }}>{r.phone || '—'}</span>
        )},
        { key: 'is_active', label: 'Estado', render: r => (
            <Badge color={r.is_active ? T.green : T.red}>{r.is_active ? '● Activa' : '○ Inactiva'}</Badge>
        )},
        { key: 'is_open', label: 'Abierta', render: r => (
            <Badge color={r.is_open ? T.green : T.textMuted} variant="outline">{r.is_open ? 'Abierta' : 'Cerrada'}</Badge>
        )},
        { key: 'today_tickets', label: 'Hoy', align: 'center', render: r => (
            <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums', fontFamily: T.mono }}>
                {r.today_tickets} <span style={{ color: T.textMuted, fontWeight: 400, fontSize: 11 }}>/ {r.max_daily_tickets}</span>
            </span>
        )},
        { key: 'actions', label: '', align: 'right', render: r => (
            <Link href={route('admin.sucursales.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link>
        )},
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Sucursales" />
            <div className="t-page-shell" style={{ padding: T.pagePadding, background: T.bg, minHeight: '100vh', fontFamily: T.font }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>
                    <FlashMessages flash={flash} />
                    <PageHeader title="Sucursales" subtitle="Gestión de sucursales del sistema"
                        actions={<Link href={route('admin.sucursales.create')}><Btn variant="primary">+ Nueva Sucursal</Btn></Link>} />
                    <DataTable
                        columns={columns}
                        rows={branches}
                        emptyIcon="◈"
                        emptyTitle="Sin sucursales"
                        emptySubtitle="Crea tu primera sucursal para comenzar"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
