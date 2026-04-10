// resources/js/Pages/Auth/ForgotPassword.jsx
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { T, Btn } from '@/Components/TurnosUI';

const labelStyle = { fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6, fontFamily: T.font };
const inputStyle = { width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`, borderRadius: 8, padding: '11px 14px', fontSize: 13, outline: 'none', fontFamily: T.font };

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });
    const submit = (e) => { e.preventDefault(); post(route('password.email')); };

    return (
        <GuestLayout>
            <Head title="Recuperar Contraseña" />
            <h2 style={{ fontSize: 18, fontWeight: 800, marginBottom: 8, letterSpacing: '-0.02em', fontFamily: T.font }}>Recuperar contraseña</h2>
            <p style={{ fontSize: 13, color: T.textSoft, marginBottom: 20, lineHeight: 1.5 }}>
                Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña.
            </p>

            {status && (
                <div style={{
                    background: `color-mix(in srgb, ${T.green} 8%, transparent)`,
                    border: `1px solid color-mix(in srgb, ${T.green} 20%, transparent)`,
                    borderRadius: 8, padding: '10px 14px', marginBottom: 16, fontSize: 12, color: T.green,
                }}>{status}</div>
            )}

            <form onSubmit={submit}>
                <div style={{ marginBottom: 20 }}>
                    <label style={labelStyle}>Email</label>
                    <input type="email" style={inputStyle} value={data.email} onChange={e => setData('email', e.target.value)} autoFocus
                        onFocus={e => { e.target.style.borderColor = `var(--t-blue)`; e.target.style.boxShadow = `0 0 0 3px var(--t-blue-glow)`; }}
                        onBlur={e => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; }} />
                    {errors.email && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.email}</div>}
                </div>
                <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <Btn type="submit" variant="primary" disabled={processing}>{processing ? 'Enviando...' : 'Enviar enlace'}</Btn>
                </div>
            </form>
        </GuestLayout>
    );
}
