// resources/js/Pages/Admin/Counters/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, Select, FlashMessages, T } from '@/Components/TurnosUI';

export default function CountersIndex({ counters, branches, currentBranchId }) {
    const { flash } = usePage().props;
    const statusColors = { open: T.success, serving: T.accent, paused: T.warning, closed: T.textMuted };
    const statusLabels = { open: 'Disponible', serving: 'Atendiendo', paused: 'Pausada', closed: 'Cerrada' };

    const columns = [
        { key: 'number', label: '#', render: r => <span style={{ fontSize: 18, fontWeight: 800, fontVariantNumeric: 'tabular-nums', color: T.accent }}>{r.number}</span> },
        { key: 'name', label: 'Nombre', render: r => <span style={{ fontWeight: 600 }}>{r.name}</span> },
        { key: 'status', label: 'Estado', render: r => (
            <span style={{ color: statusColors[r.status], fontWeight: 600, fontSize: 12 }}>● {statusLabels[r.status]}</span>
        )},
        { key: 'operator_name', label: 'Operador', render: r => r.operator_name || <span style={{ color: T.textMuted }}>Sin asignar</span> },
        { key: 'current_ticket', label: 'Turno actual', render: r => r.current_ticket ? <span style={{ fontWeight: 600, fontVariantNumeric: 'tabular-nums' }}>{r.current_ticket}</span> : <span style={{ color: T.textMuted }}>—</span> },
        { key: 'actions', label: '', render: r => <Link href={route('admin.ventanillas.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link> },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">Ventanillas</h2>}>
            <Head title="Ventanillas" />
            <div style={{ padding: '24px 32px', background: T.bg, minHeight: '100vh' }}>
                <FlashMessages flash={flash} />
                <PageHeader title="Ventanillas / Puestos" subtitle="Control de puntos de atención por sucursal"
                    actions={<div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                        <Select value={currentBranchId || ''} onChange={e => router.get(route('admin.ventanillas.index'), { branch_id: e.target.value }, { preserveState: true })}
                            options={branches.map(b => ({ value: b.id, label: b.name }))} />
                        <Link href={route('admin.ventanillas.create')}><Btn variant="primary">+ Nueva Ventanilla</Btn></Link>
                    </div>} />
                <DataTable columns={columns} rows={counters} />
            </div>
        </AuthenticatedLayout>
    );
}
