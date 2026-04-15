// resources/js/Pages/Profile/Edit.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { Card, PageHeader, T } from '@/Components/TurnosUI';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import SocialAccountsSection from '@/Components/SocialAccountsSection';

export default function Edit({ mustVerifyEmail, status }) {
    const { auth } = usePage().props;

    return (
        <AuthenticatedLayout>
            <Head title="Perfil" />
            <div className="t-page-shell" style={{ padding: T.pagePadding, background: T.bg, minHeight: '100vh', fontFamily: T.font, color: T.text }}>
                <PageHeader title="Mi Perfil" subtitle="Administra tu información personal y seguridad" />

                <div style={{ maxWidth: 640, display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Card accent={T.blue}>
                        <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                    </Card>

                    <Card accent={T.blue}>
                        <SocialAccountsSection socialAccounts={auth?.user?.social_accounts || []} />
                    </Card>

                    <Card accent={T.purple}>
                        <UpdatePasswordForm />
                    </Card>

                    <Card accent={T.red}>
                        <DeleteUserForm />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
