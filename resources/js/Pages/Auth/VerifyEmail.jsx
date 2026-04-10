// resources/js/Pages/Auth/VerifyEmail.jsx
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { T, Btn } from '@/Components/TurnosUI';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});
    const submit = (e) => { e.preventDefault(); post(route('verification.send')); };

    return (
        <GuestLayout>
            <Head title="Verificar Email" />
            <div style={{ textAlign: 'center', marginBottom: 20 }}>
                <div style={{ fontSize: 40, marginBottom: 12 }}>📧</div>
                <h2 style={{ fontSize: 18, fontWeight: 800, marginBottom: 8, letterSpacing: '-0.02em', fontFamily: T.font }}>Verifica tu email</h2>
                <p style={{ fontSize: 13, color: T.textSoft, lineHeight: 1.5 }}>
                    Te enviamos un enlace de verificación. Si no lo recibiste, podemos enviarte otro.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div style={{
                    background: `color-mix(in srgb, ${T.green} 8%, transparent)`,
                    border: `1px solid color-mix(in srgb, ${T.green} 20%, transparent)`,
                    borderRadius: 8, padding: '10px 14px', marginBottom: 16, fontSize: 12, color: T.green, textAlign: 'center',
                }}>Se envió un nuevo enlace de verificación</div>
            )}

            <form onSubmit={submit}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Btn type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Enviando...' : 'Reenviar enlace'}
                    </Btn>
                    <Link href={route('logout')} method="post" as="button"
                        style={{
                            fontSize: 12, color: T.textMuted, background: 'none', border: 'none',
                            cursor: 'pointer', textDecoration: 'underline', fontFamily: T.font,
                        }}>
                        Cerrar sesión
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
