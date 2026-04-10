// resources/js/Pages/Admin/Queues/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, Select, Badge, FlashMessages, T } from '@/Components/TurnosUI';

export default function QueuesIndex({ queues, branches, currentBranchId }) {
    const { flash } = usePage().props;

    const algoLabels = { fifo: 'FIFO', priority: 'Prioridad', weighted_fair: 'Ponderado' };

    const columns = [
        { key: 'prefix', label: 'Prefijo', render: r => (
            <span style={{ fontFamily: T.mono, fontSize: 14, fontWeight: 700, color: T.blue }}>{r.prefix}</span>
        )},
        { key: 'name', label: 'Nombre', render: r => <span style={{ fontWeight: 600 }}>{r.name}</span> },
        { key: 'priority_algorithm', label: 'Algoritmo', render: r => (
            <Badge color={T.textSoft} variant="outline">{algoLabels[r.priority_algorithm] || r.priority_algorithm}</Badge>
        )},
        { key: 'max_capacity', label: 'Capacidad', render: r => (
            <span style={{ fontFamily: T.mono, fontSize: 12 }}>{r.max_capacity}</span>
        )},
        { key: 'waiting_count', label: 'En espera', align: 'center', render: r => (
            <span style={{ fontWeight: 700, fontFamily: T.mono, color: r.waiting_count > 0 ? T.amber : T.textMuted }}>
                {r.waiting_count}
            </span>
        )},
        { key: 'services', label: 'Servicios', render: r => (
            <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                {r.services.map(s => <Badge key={s.id} color={s.color}>{s.name}</Badge>)}
                {r.services.length === 0 && <span style={{ color: T.textMuted, fontSize: 11 }}>—</span>}
            </div>
        )},
        { key: 'is_active', label: 'Estado', render: r => (
            <Badge color={r.is_active ? T.green : T.red}>{r.is_active ? '● Activa' : '○ Inactiva'}</Badge>
        )},
        { key: 'actions', label: '', align: 'right', render: r => (
            <Link href={route('admin.colas.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link>
        )},
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Colas" />
            <div className="t-page-shell" style={{ padding: T.pagePadding, background: T.bg, minHeight: '100vh', fontFamily: T.font }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>
                    <FlashMessages flash={flash} />
                    <PageHeader
                        title="Colas de Atención"
                        subtitle="Configuración de las colas por sucursal"
                        actions={
                            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                <Select
                                    value={currentBranchId || ''}
                                    onChange={e => router.get(route('admin.colas.index'), { branch_id: e.target.value }, { preserveState: true })}
                                    options={branches.map(b => ({ value: b.id, label: b.name }))}
                                />
                                <Link href={route('admin.colas.create')}><Btn variant="primary">+ Nueva Cola</Btn></Link>
                            </div>
                        }
                    />
                    <DataTable
                        columns={columns}
                        rows={queues}
                        emptyIcon="▦"
                        emptyTitle="Sin colas configuradas"
                        emptySubtitle="Crea tu primera cola para organizar la atención"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
