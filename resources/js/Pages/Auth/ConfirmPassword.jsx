// resources/js/Pages/Auth/ConfirmPassword.jsx
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { T, Btn } from '@/Components/TurnosUI';

const labelStyle = { fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6, fontFamily: T.font };
const inputStyle = { width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`, borderRadius: 8, padding: '11px 14px', fontSize: 13, outline: 'none', fontFamily: T.font };
const focusH = e => { e.target.style.borderColor = `var(--t-blue)`; e.target.style.boxShadow = `0 0 0 3px var(--t-blue-glow)`; };
const blurH = e => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; };

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });
    const submit = (e) => { e.preventDefault(); post(route('password.confirm'), { onFinish: () => reset('password') }); };

    return (
        <GuestLayout>
            <Head title="Confirmar Contraseña" />
            <p style={{ fontSize: 13, color: T.textSoft, marginBottom: 20, lineHeight: 1.5 }}>
                Esta es un área segura. Confirma tu contraseña para continuar.
            </p>
            <form onSubmit={submit}>
                <div style={{ marginBottom: 20 }}>
                    <label style={labelStyle}>Contraseña</label>
                    <input type="password" style={inputStyle} value={data.password} onChange={e => setData('password', e.target.value)} autoFocus onFocus={focusH} onBlur={blurH} />
                    {errors.password && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.password}</div>}
                </div>
                <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <Btn type="submit" variant="primary" disabled={processing}>{processing ? 'Confirmando...' : 'Confirmar'}</Btn>
                </div>
            </form>
        </GuestLayout>
    );
}
