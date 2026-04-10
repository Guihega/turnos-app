// resources/js/Pages/Profile/Partials/DeleteUserForm.jsx
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { T, Btn } from '@/Components/TurnosUI';

const labelStyle = { fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', display: 'block', marginBottom: 6, fontFamily: T.font };
const inputStyle = {
    width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
    borderRadius: 8, padding: '11px 14px', fontSize: 13, outline: 'none', fontFamily: T.font,
};

export default function DeleteUserForm() {
    const [confirming, setConfirming] = useState(false);
    const passwordInput = useRef();
    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({ password: '' });

    const deleteUser = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => { setConfirming(false); clearErrors(); reset(); };

    return (
        <div>
            <div style={{ marginBottom: 18 }}>
                <h3 style={{ fontSize: 15, fontWeight: 700, color: T.text, margin: 0, fontFamily: T.font }}>Eliminar cuenta</h3>
                <p style={{ fontSize: 12, color: T.textMuted, marginTop: 4, fontFamily: T.font, lineHeight: 1.5 }}>
                    Una vez eliminada, todos los recursos y datos se perderán permanentemente. Descarga cualquier información que desees conservar antes de continuar.
                </p>
            </div>

            <Btn variant="danger" onClick={() => setConfirming(true)}>Eliminar cuenta</Btn>

            {/* Confirm Modal */}
            {confirming && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
                    <div onClick={closeModal} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)' }} />
                    <div style={{
                        position: 'relative', background: T.card, borderRadius: 20, border: `1px solid ${T.border}`,
                        padding: 28, maxWidth: 440, width: '100%', boxShadow: T.shadowLg, animation: 'tScaleIn 0.2s ease both',
                    }}>
                        <h3 style={{ fontSize: 16, fontWeight: 700, color: T.text, marginBottom: 8, fontFamily: T.font }}>
                            ¿Estás seguro de eliminar tu cuenta?
                        </h3>
                        <p style={{ fontSize: 13, color: T.textSoft, marginBottom: 20, lineHeight: 1.5, fontFamily: T.font }}>
                            Esta acción es irreversible. Ingresa tu contraseña para confirmar.
                        </p>

                        <form onSubmit={deleteUser}>
                            <div style={{ marginBottom: 20 }}>
                                <label style={labelStyle}>Contraseña</label>
                                <input ref={passwordInput} type="password" style={inputStyle}
                                    value={data.password} onChange={e => setData('password', e.target.value)}
                                    placeholder="Tu contraseña actual" autoFocus
                                    onFocus={e => { e.target.style.borderColor = `var(--t-red)`; e.target.style.boxShadow = `0 0 0 3px var(--t-red-glow)`; }}
                                    onBlur={e => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; }} />
                                {errors.password && <div style={{ fontSize: 11, color: T.red, marginTop: 4 }}>{errors.password}</div>}
                            </div>

                            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                                <Btn variant="ghost" onClick={closeModal}>Cancelar</Btn>
                                <Btn variant="danger" type="submit" disabled={processing}>
                                    {processing ? 'Eliminando...' : 'Eliminar cuenta'}
                                </Btn>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
