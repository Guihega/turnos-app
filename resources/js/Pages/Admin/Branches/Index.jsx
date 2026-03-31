// resources/js/Pages/Admin/Branches/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, Card, StatusBadge, FlashMessages, theme } from '@/Components/TurnosUI';
import { usePage } from '@inertiajs/react';

export default function BranchesIndex({ branches }) {
    const { flash } = usePage().props;

    const columns = [
        { key: 'name', label: 'Sucursal', render: r => <span style={{ fontWeight: 600 }}>{r.name}</span> },
        { key: 'code', label: 'Código', render: r => <span style={{ background: theme.accentSoft, color: theme.accent, padding: '2px 8px', borderRadius: 4, fontSize: 11, fontWeight: 600 }}>{r.code}</span> },
        { key: 'city', label: 'Ciudad' },
        { key: 'phone', label: 'Teléfono' },
        { key: 'is_active', label: 'Estado', render: r => (
            <span style={{ color: r.is_active ? theme.success : theme.danger, fontWeight: 600, fontSize: 11 }}>
                {r.is_active ? '● Activa' : '○ Inactiva'}
            </span>
        )},
        { key: 'is_open', label: 'Abierta', render: r => (
            <span style={{ color: r.is_open ? theme.success : theme.textMuted, fontSize: 11 }}>
                {r.is_open ? '🟢 Abierta' : '⚫ Cerrada'}
            </span>
        )},
        { key: 'today_tickets', label: 'Hoy', align: 'center', render: r => (
            <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>{r.today_tickets} <span style={{ color: theme.textMuted, fontWeight: 400 }}>/ {r.max_daily_tickets}</span></span>
        )},
        { key: 'actions', label: '', render: r => (
            <div style={{ display: 'flex', gap: 6 }}>
                <Link href={route('admin.sucursales.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link>
            </div>
        )},
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">Sucursales</h2>}>
            <Head title="Sucursales" />
            <div style={{ padding: '24px 32px', background: theme.bg, minHeight: '100vh' }}>
                <FlashMessages flash={flash} />
                <PageHeader title="Sucursales" subtitle="Gestión de sucursales del sistema"
                    actions={<Link href={route('admin.sucursales.create')}><Btn variant="primary">+ Nueva Sucursal</Btn></Link>} />
                <DataTable columns={columns} rows={branches} />
            </div>
        </AuthenticatedLayout>
    );
}
