import { router } from '@inertiajs/react';

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

export default function SocialAuthButtons({ action = 'login', className = '' }) {
    const handleSocialAuth = (provider) => {
        // Redirigir al endpoint OAuth con la acción
        window.location.href = route('social.redirect', {
            provider,
            action,
        });
    };

    return (
        <div className={className}>
            <div style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                margin: '20px 0',
            }}>
                <div style={{ flex: 1, height: '1px', background: 'var(--t-border, #e2e8f0)' }} />
                <span style={{
                    fontSize: '13px',
                    color: 'var(--t-text-muted, #94a3b8)',
                    fontWeight: 500,
                    whiteSpace: 'nowrap',
                }}>
                    o continúa con
                </span>
                <div style={{ flex: 1, height: '1px', background: 'var(--t-border, #e2e8f0)' }} />
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                <button
                    type="button"
                    onClick={() => handleSocialAuth('google')}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '10px',
                        width: '100%',
                        padding: '10px 16px',
                        border: '1px solid var(--t-border, #e2e8f0)',
                        borderRadius: '8px',
                        background: 'var(--t-card, #fff)',
                        color: 'var(--t-text, #1e293b)',
                        fontSize: '14px',
                        fontWeight: 500,
                        cursor: 'pointer',
                        transition: 'all 0.15s ease',
                    }}
                    onMouseOver={(e) => {
                        e.currentTarget.style.background = 'var(--t-bg-hover, #f8fafc)';
                        e.currentTarget.style.borderColor = 'var(--t-border-hover, #cbd5e1)';
                    }}
                    onMouseOut={(e) => {
                        e.currentTarget.style.background = 'var(--t-card, #fff)';
                        e.currentTarget.style.borderColor = 'var(--t-border, #e2e8f0)';
                    }}
                >
                    <GoogleIcon />
                    Continuar con Google
                </button>

                <button
                    type="button"
                    onClick={() => handleSocialAuth('facebook')}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '10px',
                        width: '100%',
                        padding: '10px 16px',
                        border: 'none',
                        borderRadius: '8px',
                        background: '#1877F2',
                        color: '#fff',
                        fontSize: '14px',
                        fontWeight: 500,
                        cursor: 'pointer',
                        transition: 'all 0.15s ease',
                    }}
                    onMouseOver={(e) => e.currentTarget.style.background = '#166FE5'}
                    onMouseOut={(e) => e.currentTarget.style.background = '#1877F2'}
                >
                    <FacebookIcon />
                    Continuar con Facebook
                </button>
            </div>
        </div>
    );
}
