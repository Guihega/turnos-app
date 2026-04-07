// resources/js/Pages/Admin/Queues/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, Select, FlashMessages, T } from '@/Components/TurnosUI';

export default function QueuesIndex({ queues, branches, currentBranchId }) {
    const { flash } = usePage().props;
    const columns = [
        { key: 'prefix', label: 'Prefijo', render: r => <span style={{ fontFamily: 'monospace', fontSize: 14, fontWeight: 700, color: T.accent }}>{r.prefix}</span> },
        { key: 'name', label: 'Nombre', render: r => <span style={{ fontWeight: 600 }}>{r.name}</span> },
        { key: 'priority_algorithm', label: 'Algoritmo', render: r => ({ fifo: 'FIFO', priority: 'Prioridad', weighted_fair: 'Ponderado' }[r.priority_algorithm] || r.priority_algorithm) },
        { key: 'max_capacity', label: 'Capacidad' },
        { key: 'waiting_count', label: 'En espera', align: 'center', render: r => <span style={{ fontWeight: 700, color: r.waiting_count > 0 ? T.warning : T.textMuted }}>{r.waiting_count}</span> },
        { key: 'services', label: 'Servicios', render: r => (
            <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                {r.services.map(s => <span key={s.id} style={{ background: s.color + '20', color: s.color, padding: '2px 6px', borderRadius: 4, fontSize: 10, fontWeight: 600 }}>{s.name}</span>)}
            </div>
        )},
        { key: 'is_active', label: 'Estado', render: r => <span style={{ color: r.is_active ? T.success : T.danger, fontSize: 11, fontWeight: 600 }}>{r.is_active ? '● Activa' : '○ Inactiva'}</span> },
        { key: 'actions', label: '', render: r => <Link href={route('admin.colas.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link> },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">Colas</h2>}>
            <Head title="Colas" />
            <div style={{ padding: '24px 32px', background: T.bg, minHeight: '100vh' }}>
                <FlashMessages flash={flash} />
                <PageHeader title="Colas de Atención" subtitle="Configuración de las colas por sucursal"
                    actions={<div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                        <Select value={currentBranchId || ''} onChange={e => router.get(route('admin.colas.index'), { branch_id: e.target.value }, { preserveState: true })}
                            options={branches.map(b => ({ value: b.id, label: b.name }))} />
                        <Link href={route('admin.colas.create')}><Btn variant="primary">+ Nueva Cola</Btn></Link>
                    </div>} />
                <DataTable columns={columns} rows={queues} />
            </div>
        </AuthenticatedLayout>
    );
}
