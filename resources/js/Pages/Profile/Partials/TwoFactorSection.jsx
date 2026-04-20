import { useForm, usePage } from '@inertiajs/react';
import { T } from '@/Components/TurnosUI';
import { useState, useEffect } from 'react';

export default function TwoFactorSection() {
    const { auth, flash } = usePage().props;
    const user = auth.user;

    const twoFactor = flash?.twoFactor || {};
    const isEnabled = !!user.two_factor_confirmed_at;
    const [showSetup, setShowSetup] = useState(!!twoFactor.showSetup);
    const [showRecoveryCodes, setShowRecoveryCodes] = useState(!!twoFactor.showRecoveryCodes);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (twoFactor.showSetup) setShowSetup(true);
        if (twoFactor.showRecoveryCodes) setShowRecoveryCodes(true);
    }, [twoFactor]);

    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({ password: '' });

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

    const handleDisable = (e) => {
        e.preventDefault();
        disableForm.post(route('two-factor.disable'), {
            preserveScroll: true,
            onSuccess: () => {
                disableForm.reset();
                setShowSetup(false);
                setShowRecoveryCodes(false);
            },
        });
    };

    const copyRecoveryCodes = () => {
        if (twoFactor.recoveryCodes) {
            navigator.clipboard.writeText(twoFactor.recoveryCodes.join('\n'));
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    // ── Already enabled ──
    if (isEnabled && !showRecoveryCodes) {
        return (
            <div>
                <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 4 }}>
                    Autenticación en dos pasos
                </h3>
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
                    marginBottom: 12,
                }}>
                    ✓ Activada
                </div>
                <p style={{ fontSize: 14, color: T.textMuted, marginBottom: 16 }}>
                    Tu cuenta está protegida con autenticación en dos pasos usando Google Authenticator.
                </p>

                <form onSubmit={handleDisable}>
                    <div style={{ marginBottom: 12 }}>
                        <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 6, color: T.textMuted }}>
                            Contraseña actual para desactivar
                        </label>
                        <input
                            type="password"
                            value={disableForm.data.password}
                            onChange={(e) => disableForm.setData('password', e.target.value)}
                            style={{
                                width: '100%',
                                padding: '8px 12px',
                                background: T.bg,
                                border: `1px solid ${disableForm.errors.password ? T.red : T.border}`,
                                borderRadius: 8,
                                color: T.text,
                                fontSize: 14,
                                outline: 'none',
                                boxSizing: 'border-box',
                            }}
                        />
                        {disableForm.errors.password && (
                            <p style={{ color: T.red, fontSize: 13, marginTop: 4 }}>{disableForm.errors.password}</p>
                        )}
                    </div>
                    <button
                        type="submit"
                        disabled={disableForm.processing}
                        style={{
                            padding: '8px 16px',
                            background: T.red,
                            color: '#fff',
                            border: 'none',
                            borderRadius: 8,
                            fontSize: 14,
                            fontWeight: 500,
                            cursor: 'pointer',
                            opacity: disableForm.processing ? 0.7 : 1,
                        }}
                    >
                        Desactivar 2FA
                    </button>
                </form>
            </div>
        );
    }

    // ── Show recovery codes (just confirmed) ──
    if (showRecoveryCodes && twoFactor.recoveryCodes) {
        return (
            <div>
                <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 4 }}>
                    Autenticación en dos pasos
                </h3>
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
                    marginBottom: 12,
                }}>
                    ✓ Activada
                </div>

                <div style={{
                    background: 'rgba(234,179,8,0.08)',
                    border: `1px solid rgba(234,179,8,0.3)`,
                    borderRadius: 8,
                    padding: 16,
                    marginBottom: 16,
                }}>
                    <p style={{ fontSize: 14, fontWeight: 600, color: '#EAB308', marginBottom: 8 }}>
                        ⚠️ Guarda estos códigos de recuperación
                    </p>
                    <p style={{ fontSize: 13, color: T.textMuted, marginBottom: 12 }}>
                        Cada código solo puede usarse una vez. Guárdalos en un lugar seguro.
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
            </div>
        );
    }

    // ── Setup in progress (QR shown) ──
    if (showSetup && twoFactor.qrUrl) {
        return (
            <div>
                <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 8 }}>
                    Configurar autenticación en dos pasos
                </h3>
                <p style={{ fontSize: 14, color: T.textMuted, marginBottom: 16 }}>
                    Escanea el código QR con Google Authenticator o Authy, luego ingresa el código de 6 dígitos.
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
        );
    }

    // ── Not enabled — show enable button ──
    return (
        <div>
            <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 4 }}>
                Autenticación en dos pasos
            </h3>
            <div style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                padding: '4px 10px',
                borderRadius: 6,
                fontSize: 13,
                fontWeight: 500,
                background: 'rgba(239,68,68,0.1)',
                color: T.red,
                marginBottom: 12,
            }}>
                ✗ No activada
            </div>
            <p style={{ fontSize: 14, color: T.textMuted, marginBottom: 16 }}>
                Protege tu cuenta con un segundo factor de autenticación usando Google Authenticator o Authy.
            </p>
            <form onSubmit={handleEnable}>
                <button
                    type="submit"
                    disabled={enableForm.processing}
                    style={{
                        padding: '8px 20px',
                        background: T.blue,
                        color: '#fff',
                        border: 'none',
                        borderRadius: 8,
                        fontSize: 14,
                        fontWeight: 500,
                        cursor: 'pointer',
                        opacity: enableForm.processing ? 0.7 : 1,
                    }}
                >
                    {enableForm.processing ? 'Preparando...' : 'Activar 2FA'}
                </button>
            </form>
        </div>
    );
}
