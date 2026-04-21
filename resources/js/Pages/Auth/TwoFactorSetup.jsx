import { Head, useForm, usePage } from '@inertiajs/react';
import { T } from '@/Components/TurnosUI';
import { useState, useEffect } from 'react';

export default function TwoFactorSetup({ twoFactorData }) {
    const { flash } = usePage().props;
    const twoFactor = flash?.twoFactor || twoFactorData || {};

    const [showRecoveryCodes, setShowRecoveryCodes] = useState(!!twoFactor.showRecoveryCodes);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (twoFactor.showRecoveryCodes) setShowRecoveryCodes(true);
    }, [twoFactor]);

    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });

    const handleEnable = (e) => {
        e.preventDefault();
        enableForm.post(route('two-factor.enable'), { preserveScroll: true });
    };

    const handleConfirm = (e) => {
        e.preventDefault();
        confirmForm.post(route('two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirmForm.reset(),
        });
    };

    const copyRecoveryCodes = () => {
        if (twoFactor.recoveryCodes) {
            navigator.clipboard.writeText(twoFactor.recoveryCodes.join('\n'));
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const goToDashboard = () => {
        window.location.href = route('dashboard');
    };

    return (
        <>
            <Head title="Configurar autenticación en dos pasos" />
            <div style={{
                minHeight: '100vh',
                background: T.bg,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontFamily: T.font,
                color: T.text,
                padding: 16,
            }}>
                <div style={{
                    background: T.card,
                    borderRadius: 12,
                    padding: 32,
                    maxWidth: 480,
                    width: '100%',
                    border: `1px solid ${T.border}`,
                }}>
                    <div style={{ textAlign: 'center', marginBottom: 24 }}>
                        <div style={{ fontSize: 32, marginBottom: 8 }}>🛡️</div>
                        <h1 style={{ fontSize: 20, fontWeight: 600, margin: 0 }}>
                            Configuración obligatoria
                        </h1>
                        <p style={{ fontSize: 14, color: T.textMuted, marginTop: 8 }}>
                            Como administrador, debes activar la autenticación en dos pasos para proteger tu cuenta y los datos de tu organización.
                        </p>
                    </div>

                    {/* Recovery codes shown after confirmation */}
                    {showRecoveryCodes && twoFactor.recoveryCodes ? (
                        <div>
                            <div style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 6,
                                padding: '4px 10px',
                                borderRadius: 6,
                                fontSize: 13,
                                fontWeight: 500,
                                background: 'rgba(16,185,129,0.1)',
                                color: T.green,
                                marginBottom: 16,
                            }}>
                                ✓ 2FA activado exitosamente
                            </div>

                            <div style={{
                                background: 'rgba(234,179,8,0.08)',
                                border: '1px solid rgba(234,179,8,0.3)',
                                borderRadius: 8,
                                padding: 16,
                                marginBottom: 20,
                            }}>
                                <p style={{ fontSize: 14, fontWeight: 600, color: '#EAB308', marginBottom: 8 }}>
                                    ⚠️ Guarda estos códigos de recuperación
                                </p>
                                <p style={{ fontSize: 13, color: T.textMuted, marginBottom: 12 }}>
                                    Cada código solo puede usarse una vez. Guárdalos en un lugar seguro antes de continuar.
                                </p>
                                <div style={{
                                    background: T.bg,
                                    borderRadius: 6,
                                    padding: 12,
                                    fontFamily: T.mono,
                                    fontSize: 14,
                                    lineHeight: 1.8,
                                }}>
                                    {twoFactor.recoveryCodes.map((code, i) => (
                                        <div key={i}>{code}</div>
                                    ))}
                                </div>
                                <button
                                    onClick={copyRecoveryCodes}
                                    style={{
                                        marginTop: 12,
                                        padding: '6px 14px',
                                        background: T.blue,
                                        color: '#fff',
                                        border: 'none',
                                        borderRadius: 6,
                                        fontSize: 13,
                                        cursor: 'pointer',
                                    }}
                                >
                                    {copied ? '✓ Copiados' : 'Copiar códigos'}
                                </button>
                            </div>

                            <button
                                onClick={goToDashboard}
                                style={{
                                    width: '100%',
                                    padding: '10px 16px',
                                    background: T.blue,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 8,
                                    fontSize: 15,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                }}
                            >
                                Continuar al Dashboard
                            </button>
                        </div>
                    ) : twoFactor.qrUrl || twoFactor.showSetup ? (
                        /* QR code shown — waiting for confirmation */
                        <div>
                            <p style={{ fontSize: 14, color: T.textMuted, marginBottom: 16 }}>
                                Escanea el código QR con Google Authenticator o Authy, luego ingresa el código de 6 dígitos para confirmar.
                            </p>

                            <div style={{ textAlign: 'center', marginBottom: 16 }}>
                                <img
                                    src={`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(twoFactor.qrUrl)}`}
                                    alt="QR Code"
                                    style={{ borderRadius: 8, background: '#fff', padding: 8 }}
                                    width={200}
                                    height={200}
                                />
                            </div>

                            <div style={{
                                background: T.bg,
                                borderRadius: 6,
                                padding: 10,
                                marginBottom: 16,
                                textAlign: 'center',
                            }}>
                                <p style={{ fontSize: 12, color: T.textMuted, marginBottom: 4 }}>O ingresa esta clave manualmente:</p>
                                <code style={{ fontFamily: T.mono, fontSize: 14, letterSpacing: '0.1em', wordBreak: 'break-all' }}>
                                    {twoFactor.secret}
                                </code>
                            </div>

                            <form onSubmit={handleConfirm}>
                                <div style={{ marginBottom: 12 }}>
                                    <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 6, color: T.textMuted }}>
                                        Código de verificación
                                    </label>
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        autoFocus
                                        maxLength={6}
                                        value={confirmForm.data.code}
                                        onChange={(e) => confirmForm.setData('code', e.target.value.replace(/\D/g, ''))}
                                        placeholder="000000"
                                        style={{
                                            width: '100%',
                                            padding: '10px 12px',
                                            fontSize: 20,
                                            fontFamily: T.mono,
                                            textAlign: 'center',
                                            letterSpacing: '0.3em',
                                            background: T.bg,
                                            border: `1px solid ${confirmForm.errors.code ? T.red : T.border}`,
                                            borderRadius: 8,
                                            color: T.text,
                                            outline: 'none',
                                            boxSizing: 'border-box',
                                        }}
                                    />
                                    {confirmForm.errors.code && (
                                        <p style={{ color: T.red, fontSize: 13, marginTop: 4 }}>{confirmForm.errors.code}</p>
                                    )}
                                </div>
                                <button
                                    type="submit"
                                    disabled={confirmForm.processing || confirmForm.data.code.length !== 6}
                                    style={{
                                        width: '100%',
                                        padding: '10px 16px',
                                        background: T.green,
                                        color: '#fff',
                                        border: 'none',
                                        borderRadius: 8,
                                        fontSize: 15,
                                        fontWeight: 600,
                                        cursor: 'pointer',
                                        opacity: (confirmForm.processing || confirmForm.data.code.length !== 6) ? 0.5 : 1,
                                    }}
                                >
                                    {confirmForm.processing ? 'Verificando...' : 'Confirmar y activar'}
                                </button>
                            </form>
                        </div>
                    ) : (
                        /* Initial state — show enable button */
                        <form onSubmit={handleEnable}>
                            <div style={{
                                background: 'rgba(234,179,8,0.08)',
                                border: '1px solid rgba(234,179,8,0.3)',
                                borderRadius: 8,
                                padding: 16,
                                marginBottom: 20,
                                fontSize: 13,
                                color: T.textMuted,
                            }}>
                                No podrás acceder al sistema hasta que completes esta configuración. Solo toma un minuto.
                            </div>

                            <button
                                type="submit"
                                disabled={enableForm.processing}
                                style={{
                                    width: '100%',
                                    padding: '12px 16px',
                                    background: T.blue,
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: 8,
                                    fontSize: 16,
                                    fontWeight: 600,
                                    cursor: 'pointer',
                                    opacity: enableForm.processing ? 0.7 : 1,
                                }}
                            >
                                {enableForm.processing ? 'Preparando...' : 'Comenzar configuración'}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </>
    );
}
