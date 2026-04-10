// resources/js/Pages/Admin/Counters/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, Select, FlashMessages, Badge, T } from '@/Components/TurnosUI';

export default function CountersIndex({ counters, branches, currentBranchId }) {
    const { flash } = usePage().props;
    const statusConfig = {
        open:    { color: T.green,    label: 'Disponible' },
        serving: { color: T.blue,     label: 'Atendiendo' },
        paused:  { color: T.amber,    label: 'Pausada' },
        closed:  { color: T.textMuted, label: 'Cerrada' },
    };

    const columns = [
        { key: 'number', label: '#', render: r => (
            <span style={{ fontSize: 17, fontWeight: 800, fontVariantNumeric: 'tabular-nums', color: T.blue, fontFamily: T.mono }}>{r.number}</span>
        )},
        { key: 'name', label: 'Nombre', render: r => <span style={{ fontWeight: 600 }}>{r.name}</span> },
        { key: 'status', label: 'Estado', render: r => {
            const s = statusConfig[r.status] || statusConfig.closed;
            return <Badge color={s.color}>● {s.label}</Badge>;
        }},
        { key: 'operator_name', label: 'Operador', render: r => r.operator_name || <span style={{ color: T.textMuted, fontSize: 12 }}>Sin asignar</span> },
        { key: 'current_ticket', label: 'Turno actual', render: r => r.current_ticket
            ? <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums', fontFamily: T.mono }}>{r.current_ticket}</span>
            : <span style={{ color: T.textMuted }}>—</span>
        },
        { key: 'actions', label: '', align: 'right', render: r => (
            <Link href={route('admin.ventanillas.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link>
        )},
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Ventanillas" />
            <div className="t-page-shell" style={{ padding: T.pagePadding, background: T.bg, minHeight: '100vh', fontFamily: T.font }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>
                    <FlashMessages flash={flash} />
                    <PageHeader
                        title="Ventanillas / Puestos"
                        subtitle="Control de puntos de atención por sucursal"
                        actions={
                            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                <Select
                                    value={currentBranchId || ''}
                                    onChange={e => router.get(route('admin.ventanillas.index'), { branch_id: e.target.value }, { preserveState: true })}
                                    options={branches.map(b => ({ value: b.id, label: b.name }))}
                                />
                                <Link href={route('admin.ventanillas.create')}><Btn variant="primary">+ Nueva Ventanilla</Btn></Link>
                            </div>
                        }
                    />
                    <DataTable
                        columns={columns}
                        rows={counters}
                        emptyIcon="▣"
                        emptyTitle="Sin ventanillas"
                        emptySubtitle="Crea tu primera ventanilla para comenzar a atender"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
