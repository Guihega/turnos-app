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

                <div className="profile-grid">
                    {/* Left column: Identity */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <Card accent={T.blue}>
                            <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                        </Card>

                        <Card accent={T.blue}>
                            <SocialAccountsSection socialAccounts={auth?.user?.social_accounts || []} />
                        </Card>
                    </div>

                    {/* Right column: Security */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <Card accent={T.purple}>
                            <UpdatePasswordForm />
                        </Card>

                        <Card accent={T.red}>
                            <DeleteUserForm />
                        </Card>
                    </div>
                </div>
            </div>

            <style>{`
                .profile-grid {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 16px;
                    max-width: 1100px;
                }
                @media (min-width: 900px) {
                    .profile-grid {
                        grid-template-columns: 1fr 1fr;
                        align-items: start;
                    }
                }
            `}</style>
        </AuthenticatedLayout>
    );
}
