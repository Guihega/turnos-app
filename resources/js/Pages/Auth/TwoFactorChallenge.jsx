import { Head, useForm } from '@inertiajs/react';
import { T } from '@/Components/TurnosUI';
import { useState } from 'react';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);

    const form = useForm({
        code: '',
        recovery_code: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('two-factor.challenge.verify'), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Verificación en dos pasos" />
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
                    maxWidth: 420,
                    width: '100%',
                    border: `1px solid ${T.border}`,
                }}>
                    <div style={{ textAlign: 'center', marginBottom: 24 }}>
                        <div style={{ fontSize: 32, marginBottom: 8 }}>🔐</div>
                        <h1 style={{ fontSize: 20, fontWeight: 600, margin: 0 }}>
                            Verificación en dos pasos
                        </h1>
                        <p style={{ fontSize: 14, color: T.textMuted, marginTop: 8 }}>
                            {useRecovery
                                ? 'Ingresa uno de tus códigos de recuperación.'
                                : 'Ingresa el código de 6 dígitos de tu aplicación de autenticación.'
                            }
                        </p>
                    </div>

                    <form onSubmit={submit}>
                        {!useRecovery ? (
                            <div style={{ marginBottom: 16 }}>
                                <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 6, color: T.textMuted }}>
                                    Código de verificación
                                </label>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    autoFocus
                                    maxLength={6}
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value.replace(/\D/g, ''))}
                                    placeholder="000000"
                                    style={{
                                        width: '100%',
                                        padding: '10px 12px',
                                        fontSize: 24,
                                        fontFamily: T.mono,
                                        textAlign: 'center',
                                        letterSpacing: '0.3em',
                                        background: T.bg,
                                        border: `1px solid ${form.errors.code ? T.red : T.border}`,
                                        borderRadius: 8,
                                        color: T.text,
                                        outline: 'none',
                                        boxSizing: 'border-box',
                                    }}
                                />
                                {form.errors.code && (
                                    <p style={{ color: T.red, fontSize: 13, marginTop: 6 }}>{form.errors.code}</p>
                                )}
                            </div>
                        ) : (
                            <div style={{ marginBottom: 16 }}>
                                <label style={{ display: 'block', fontSize: 13, fontWeight: 500, marginBottom: 6, color: T.textMuted }}>
                                    Código de recuperación
                                </label>
                                <input
                                    type="text"
                                    autoFocus
                                    value={form.data.recovery_code}
                                    onChange={(e) => form.setData('recovery_code', e.target.value)}
                                    placeholder="xxxxxxxxxx-xxxxxxxxxx"
                                    style={{
                                        width: '100%',
                                        padding: '10px 12px',
                                        fontSize: 16,
                                        fontFamily: T.mono,
                                        textAlign: 'center',
                                        background: T.bg,
                                        border: `1px solid ${form.errors.recovery_code ? T.red : T.border}`,
                                        borderRadius: 8,
                                        color: T.text,
                                        outline: 'none',
                                        boxSizing: 'border-box',
                                    }}
                                />
                                {form.errors.recovery_code && (
                                    <p style={{ color: T.red, fontSize: 13, marginTop: 6 }}>{form.errors.recovery_code}</p>
                                )}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={form.processing}
                            style={{
                                width: '100%',
                                padding: '10px 16px',
                                background: T.blue,
                                color: '#fff',
                                border: 'none',
                                borderRadius: 8,
                                fontSize: 15,
                                fontWeight: 600,
                                cursor: form.processing ? 'wait' : 'pointer',
                                opacity: form.processing ? 0.7 : 1,
                            }}
                        >
                            {form.processing ? 'Verificando...' : 'Verificar'}
                        </button>

                        <button
                            type="button"
                            onClick={() => {
                                setUseRecovery(!useRecovery);
                                form.clearErrors();
                                form.reset();
                            }}
                            style={{
                                width: '100%',
                                padding: '8px 16px',
                                marginTop: 12,
                                background: 'transparent',
                                color: T.textMuted,
                                border: 'none',
                                fontSize: 13,
                                cursor: 'pointer',
                                textDecoration: 'underline',
                            }}
                        >
                            {useRecovery ? 'Usar código de autenticación' : 'Usar código de recuperación'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
