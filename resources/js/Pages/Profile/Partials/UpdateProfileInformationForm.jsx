// resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.jsx
import { Link, useForm, usePage } from '@inertiajs/react';
import { T, Btn } from '@/Components/TurnosUI';

const labelStyle = { fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6, fontFamily: T.font };
const inputStyle = {
    width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
    borderRadius: 8, padding: '11px 14px', fontSize: 13, outline: 'none', fontFamily: T.font,
};

export default function UpdateProfileInformation({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({ name: user.name, email: user.email });

    const submit = (e) => { e.preventDefault(); patch(route('profile.update')); };

    return (
        <div>
            <div style={{ marginBottom: 18 }}>
                <h3 style={{ fontSize: 15, fontWeight: 700, color: T.text, margin: 0, fontFamily: T.font }}>Información del perfil</h3>
                <p style={{ fontSize: 12, color: T.textMuted, marginTop: 4, fontFamily: T.font }}>Actualiza tu nombre y correo electrónico</p>
            </div>

            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div>
                    <label style={labelStyle}>Nombre</label>
                    <input style={inputStyle} value={data.name} onChange={e => setData('name', e.target.value)} required autoComplete="name"
                        onFocus={e => { e.target.style.borderColor = `var(--t-blue)`; e.target.style.boxShadow = `0 0 0 3px var(--t-blue-glow)`; }}
                        onBlur={e => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; }} />
                    {errors.name && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.name}</div>}
                </div>
                <div>
                    <label style={labelStyle}>Email</label>
                    <input type="email" style={inputStyle} value={data.email} onChange={e => setData('email', e.target.value)} required autoComplete="username"
                        onFocus={e => { e.target.style.borderColor = `var(--t-blue)`; e.target.style.boxShadow = `0 0 0 3px var(--t-blue-glow)`; }}
                        onBlur={e => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; }} />
                    {errors.email && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.email}</div>}
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div style={{ fontSize: 12, color: T.amber }}>
                        Tu email no está verificado.{' '}
                        <Link href={route('verification.send')} method="post" as="button"
                            style={{ color: T.blue, background: 'none', border: 'none', cursor: 'pointer', textDecoration: 'underline', fontSize: 12, fontFamily: T.font }}>
                            Reenviar verificación
                        </Link>
                        {status === 'verification-link-sent' && (
                            <span style={{ color: T.green, marginLeft: 8 }}>Se envió un nuevo enlace</span>
                        )}
                    </div>
                )}

                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <Btn type="submit" variant="primary" disabled={processing}>{processing ? 'Guardando...' : 'Guardar'}</Btn>
                    {recentlySuccessful && <span style={{ fontSize: 12, color: T.green, fontWeight: 600 }}>✓ Guardado</span>}
                </div>
            </form>
        </div>
    );
}
