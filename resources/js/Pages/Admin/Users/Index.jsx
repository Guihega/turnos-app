// resources/js/Pages/Admin/Users/Index.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageHeader, DataTable, Btn, Badge, Avatar, FlashMessages, T } from '@/Components/TurnosUI';

const roleColors = {
    super_admin: '#EF4444', tenant_admin: '#8B5CF6', branch_manager: '#3B82F6',
    operator: '#10B981', receptionist: '#F59E0B', viewer: '#6B7280',
};

export default function UsersIndex({ users, roles }) {
    const { flash } = usePage().props;

    const columns = [
        { key: 'name', label: 'Nombre', render: r => (
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <Avatar name={r.name} size={30} color={roleColors[r.role] || T.blue} />
                <div>
                    <div style={{ fontWeight: 600, fontSize: 13 }}>{r.name}</div>
                    <div style={{ fontSize: 11, color: T.textMuted }}>{r.email}</div>
                </div>
            </div>
        )},
        { key: 'role_label', label: 'Rol', render: r => (
            <Badge color={roleColors[r.role] || T.blue}>{r.role_label}</Badge>
        )},
        { key: 'branches', label: 'Sucursales', render: r => (
            <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                {r.branches.map(b => <Badge key={b.id} color={T.blue} variant="outline">{b.name}</Badge>)}
                {r.branches.length === 0 && <span style={{ color: T.textMuted, fontSize: 11 }}>—</span>}
            </div>
        )},
        { key: 'is_active', label: 'Estado', render: r => (
            <Badge color={r.is_active ? T.green : T.red}>{r.is_active ? '● Activo' : '○ Inactivo'}</Badge>
        )},
        { key: 'last_login_at', label: 'Último acceso', render: r => (
            <span style={{ fontSize: 11, color: T.textMuted, fontFamily: T.mono }}>{r.last_login_at || 'Nunca'}</span>
        )},
        { key: 'actions', label: '', align: 'right', render: r => (
            <Link href={route('admin.usuarios.edit', r.id)}><Btn variant="ghost" size="sm">Editar</Btn></Link>
        )},
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Usuarios" />
            <div className="t-page-shell" style={{ padding: T.pagePadding, background: T.bg, minHeight: '100vh', fontFamily: T.font }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>
                    <FlashMessages flash={flash} />
                    <PageHeader
                        title="Usuarios"
                        subtitle="Gestión de operadores, recepcionistas y administradores"
                        actions={<Link href={route('admin.usuarios.create')}><Btn variant="primary">+ Nuevo Usuario</Btn></Link>}
                    />
                    <DataTable
                        columns={columns}
                        rows={users}
                        emptyIcon="◉"
                        emptyTitle="Sin usuarios"
                        emptySubtitle="Agrega operadores y administradores para tu equipo"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
