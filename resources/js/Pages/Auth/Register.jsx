// resources/js/Pages/Auth/Register.jsx
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { T, Btn } from '@/Components/TurnosUI';

const labelStyle = { fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6, fontFamily: T.font };
const inputStyle = { width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`, borderRadius: 8, padding: '11px 14px', fontSize: 13, outline: 'none', fontFamily: T.font };
const focusH = e => { e.target.style.borderColor = `var(--t-blue)`; e.target.style.boxShadow = `0 0 0 3px var(--t-blue-glow)`; };
const blurH = e => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; };

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', email: '', password: '', password_confirmation: '' });
    const submit = (e) => { e.preventDefault(); post(route('register'), { onFinish: () => reset('password', 'password_confirmation') }); };

    return (
        <GuestLayout>
            <Head title="Registro" />
            <h2 style={{ fontSize: 18, fontWeight: 800, marginBottom: 4, letterSpacing: '-0.02em', fontFamily: T.font, textAlign: 'center' }}>Crear cuenta</h2>
            <p style={{ fontSize: 12, color: T.textMuted, marginBottom: 24, textAlign: 'center' }}>Completa tus datos para registrarte</p>

            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div>
                    <label style={labelStyle}>Nombre</label>
                    <input style={inputStyle} value={data.name} onChange={e => setData('name', e.target.value)} required autoFocus autoComplete="name" onFocus={focusH} onBlur={blurH} />
                    {errors.name && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.name}</div>}
                </div>
                <div>
                    <label style={labelStyle}>Email</label>
                    <input type="email" style={inputStyle} value={data.email} onChange={e => setData('email', e.target.value)} required autoComplete="username" onFocus={focusH} onBlur={blurH} />
                    {errors.email && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.email}</div>}
                </div>
                <div>
                    <label style={labelStyle}>Contraseña</label>
                    <input type="password" style={inputStyle} value={data.password} onChange={e => setData('password', e.target.value)} required autoComplete="new-password" onFocus={focusH} onBlur={blurH} />
                    {errors.password && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.password}</div>}
                </div>
                <div>
                    <label style={labelStyle}>Confirmar contraseña</label>
                    <input type="password" style={inputStyle} value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)} required autoComplete="new-password" onFocus={focusH} onBlur={blurH} />
                    {errors.password_confirmation && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.password_confirmation}</div>}
                </div>

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 4 }}>
                    <Link href={route('login')} style={{ fontSize: 12, color: T.blue, textDecoration: 'none', fontWeight: 600 }}>
                        ¿Ya tienes cuenta?
                    </Link>
                    <Btn type="submit" variant="primary" disabled={processing}>{processing ? 'Registrando...' : 'Crear cuenta'}</Btn>
                </div>
            </form>
        </GuestLayout>
    );
}
