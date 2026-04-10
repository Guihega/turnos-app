// resources/js/Pages/Profile/Partials/UpdatePasswordForm.jsx
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { T, Btn } from '@/Components/TurnosUI';

const labelStyle = { fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6, fontFamily: T.font };
const inputStyle = {
    width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
    borderRadius: 8, padding: '11px 14px', fontSize: 13, outline: 'none', fontFamily: T.font,
};

export default function UpdatePasswordForm() {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();
    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '', password: '', password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) { reset('password', 'password_confirmation'); passwordInput.current?.focus(); }
                if (errors.current_password) { reset('current_password'); currentPasswordInput.current?.focus(); }
            },
        });
    };

    const focusHandler = (e) => { e.target.style.borderColor = `var(--t-blue)`; e.target.style.boxShadow = `0 0 0 3px var(--t-blue-glow)`; };
    const blurHandler = (e) => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; };

    return (
        <div>
            <div style={{ marginBottom: 18 }}>
                <h3 style={{ fontSize: 15, fontWeight: 700, color: T.text, margin: 0, fontFamily: T.font }}>Actualizar contraseña</h3>
                <p style={{ fontSize: 12, color: T.textMuted, marginTop: 4, fontFamily: T.font }}>Usa una contraseña segura y única</p>
            </div>

            <form onSubmit={updatePassword} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div>
                    <label style={labelStyle}>Contraseña actual</label>
                    <input ref={currentPasswordInput} type="password" style={inputStyle} value={data.current_password}
                        onChange={e => setData('current_password', e.target.value)} autoComplete="current-password"
                        onFocus={focusHandler} onBlur={blurHandler} />
                    {errors.current_password && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.current_password}</div>}
                </div>
                <div>
                    <label style={labelStyle}>Nueva contraseña</label>
                    <input ref={passwordInput} type="password" style={inputStyle} value={data.password}
                        onChange={e => setData('password', e.target.value)} autoComplete="new-password"
                        onFocus={focusHandler} onBlur={blurHandler} />
                    {errors.password && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.password}</div>}
                </div>
                <div>
                    <label style={labelStyle}>Confirmar contraseña</label>
                    <input type="password" style={inputStyle} value={data.password_confirmation}
                        onChange={e => setData('password_confirmation', e.target.value)} autoComplete="new-password"
                        onFocus={focusHandler} onBlur={blurHandler} />
                    {errors.password_confirmation && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.password_confirmation}</div>}
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <Btn type="submit" variant="primary" disabled={processing}>{processing ? 'Guardando...' : 'Actualizar contraseña'}</Btn>
                    {recentlySuccessful && <span style={{ fontSize: 12, color: T.green, fontWeight: 600 }}>✓ Guardado</span>}
                </div>
            </form>
        </div>
    );
}
