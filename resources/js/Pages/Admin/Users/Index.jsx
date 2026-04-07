// resources/js/Pages/Admin/Users/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, FlashMessages, T } from '@/Components/TurnosUI';

export default function UsersIndex({ users, roles }) {
    const { flash } = usePage().props;
    const roleColors = { super_admin: '#EF4444', tenant_admin: '#8B5CF6', branch_manager: '#3B82F6', operator: '#10B981', receptionist: '#F59E0B', viewer: '#6B7280' };

    const columns = [
        { key: 'name', label: 'Nombre', render: r => (
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <div style={{ width: 32, height: 32, borderRadius: '50%', background: roleColors[r.role] || T.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontWeight: 700, fontSize: 13, flexShrink: 0 }}>{r.name.charAt(0)}</div>
                <div><div style={{ fontWeight: 600 }}>{r.name}</div><div style={{ fontSize: 11, color: T.textMuted }}>{r.email}</div></div>
            </div>
        )},
        { key: 'role_label', label: 'Rol', render: r => (
            <span style={{ background: (roleColors[r.role] || T.accent) + '18', color: roleColors[r.role] || T.accent, padding: '3px 8px', borderRadius: 4, fontSize: 11, fontWeight: 600 }}>{r.role_label}</span>
        )},
        { key: 'branches', label: 'Sucursales', render: r => (
            <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                {r.branches.map(b => <span key={b.id} style={{ background: T.accentSoft, color: T.accent, padding: '2px 6px', borderRadius: 3, fontSize: 10 }}>{b.name}</span>)}
                {r.branches.length === 0 && <span style={{ color: T.textMuted, fontSize: 11 }}>—</span>}
            </div>
        )},
        { key: 'is_active', label: 'Estado', render: r => <span style={{ color: r.is_active ? T.success : T.danger, fontSize: 11, fontWeight: 600 }}>{r.is_active ? '● Activo' : '○ Inactivo'}</span> },
        { key: 'last_login_at', label: 'Último acceso', render: r => <span style={{ fontSize: 11, color: T.textMuted }}>{r.last_login_at || 'Nunca'}</span> },
        { key: 'actions', label: '', render: r => <Link href={route('admin.usuarios.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link> },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-200">Usuarios</h2>}>
            <Head title="Usuarios" />
            <div style={{ padding: '24px 32px', background: T.bg, minHeight: '100vh' }}>
                <FlashMessages flash={flash} />
                <PageHeader title="Usuarios" subtitle="Gestión de operadores, recepcionistas y administradores"
                    actions={<Link href={route('admin.usuarios.create')}><Btn variant="primary">+ Nuevo Usuario</Btn></Link>} />
                <DataTable columns={columns} rows={users} />
            </div>
        </AuthenticatedLayout>
    );
}
