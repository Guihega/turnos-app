import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const GoogleIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
    </svg>
);

const FacebookIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="#1877F2">
        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
    </svg>
);

/**
 * Sección de cuentas sociales vinculadas para Profile/Edit.jsx
 *
 * Uso: <SocialAccountsSection socialAccounts={auth.user.social_accounts} />
 *
 * El backend debe pasar social_accounts en las props del usuario.
 * En el controlador de Profile:
 *   'auth' => [
 *       'user' => $request->user()->load('socialAccounts'),
 *   ]
 */
export default function SocialAccountsSection({ socialAccounts = [] }) {
    const { flash } = usePage().props;
    const [unlinking, setUnlinking] = useState(null);

    const providers = [
        { key: 'google', name: 'Google', icon: <GoogleIcon />, color: '#4285F4' },
        { key: 'facebook', name: 'Facebook', icon: <FacebookIcon />, color: '#1877F2' },
    ];

    const getLinkedAccount = (providerKey) => {
        return socialAccounts.find(a => a.provider === providerKey);
    };

    const handleLink = (provider) => {
        window.location.href = route('social.link', { provider });
    };

    const handleUnlink = (provider) => {
        if (!confirm(`¿Estás seguro de que deseas desvincular tu cuenta de ${provider === 'google' ? 'Google' : 'Facebook'}?`)) {
            return;
        }

        setUnlinking(provider);
        router.delete(route('social.unlink', { provider }), {
            preserveScroll: true,
            onFinish: () => setUnlinking(null),
        });
    };

    return (
        <div>
            <h3 style={{
                fontSize: '16px',
                fontWeight: 600,
                color: 'var(--t-text, #1e293b)',
                marginBottom: '4px',
            }}>
                Cuentas vinculadas
            </h3>
            <p style={{
                fontSize: '13px',
                color: 'var(--t-text-muted, #94a3b8)',
                marginBottom: '16px',
            }}>
                Vincula tus redes sociales para iniciar sesión más rápido.
            </p>

            {/* Flash messages */}
            {flash?.success && (
                <div style={{
                    padding: '10px 14px',
                    borderRadius: '8px',
                    background: '#f0fdf4',
                    border: '1px solid #bbf7d0',
                    color: '#16a34a',
                    fontSize: '13px',
                    marginBottom: '12px',
                }}>
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div style={{
                    padding: '10px 14px',
                    borderRadius: '8px',
                    background: '#fef2f2',
                    border: '1px solid #fecaca',
                    color: '#dc2626',
                    fontSize: '13px',
                    marginBottom: '12px',
                }}>
                    {flash.error}
                </div>
            )}

            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                {providers.map(({ key, name, icon, color }) => {
                    const linked = getLinkedAccount(key);

                    return (
                        <div
                            key={key}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                padding: '12px 16px',
                                borderRadius: '10px',
                                border: '1px solid var(--t-border, #e2e8f0)',
                                background: 'var(--t-card, #fff)',
                            }}
                        >
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                {icon}
                                <div>
                                    <div style={{
                                        fontSize: '14px',
                                        fontWeight: 500,
                                        color: 'var(--t-text, #1e293b)',
                                    }}>
                                        {name}
                                    </div>
                                    {linked && (
                                        <div style={{
                                            fontSize: '12px',
                                            color: 'var(--t-text-muted, #94a3b8)',
                                        }}>
                                            {linked.provider_email}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {linked ? (
                                <button
                                    type="button"
                                    onClick={() => handleUnlink(key)}
                                    disabled={unlinking === key}
                                    style={{
                                        padding: '6px 14px',
                                        borderRadius: '6px',
                                        border: '1px solid #fecaca',
                                        background: '#fef2f2',
                                        color: '#dc2626',
                                        fontSize: '13px',
                                        fontWeight: 500,
                                        cursor: unlinking === key ? 'not-allowed' : 'pointer',
                                        opacity: unlinking === key ? 0.5 : 1,
                                    }}
                                >
                                    {unlinking === key ? 'Desvinculando...' : 'Desvincular'}
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => handleLink(key)}
                                    style={{
                                        padding: '6px 14px',
                                        borderRadius: '6px',
                                        border: `1px solid ${color}20`,
                                        background: `${color}10`,
                                        color: color,
                                        fontSize: '13px',
                                        fontWeight: 500,
                                        cursor: 'pointer',
                                    }}
                                >
                                    Vincular
                                </button>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
