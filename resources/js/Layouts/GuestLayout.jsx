// resources/js/Layouts/GuestLayout.jsx
import { Link } from '@inertiajs/react';
import { T } from '@/Components/TurnosUI';

export default function GuestLayout({ children }) {
    return (
        <div style={{
            minHeight: '100vh', display: 'flex', flexDirection: 'column', alignItems: 'center',
            justifyContent: 'center', background: T.bg, fontFamily: T.font, color: T.text, padding: 20,
        }}>
            <Link href="/" style={{ textDecoration: 'none', marginBottom: 28 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <div style={{
                        width: 40, height: 40, borderRadius: 12,
                        background: `linear-gradient(135deg, ${T.blue}, ${T.purple})`,
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        fontWeight: 900, fontSize: 18, color: '#fff',
                    }}>O</div>
                    <span style={{ fontSize: 20, fontWeight: 900, color: T.text, letterSpacing: '-0.03em' }}>Olinora</span>
                </div>
            </Link>

            <div style={{
                width: '100%', maxWidth: 420, background: T.card, borderRadius: 20,
                border: `1px solid ${T.border}`, padding: '32px 28px', boxShadow: T.shadow,
            }}>
                {children}
            </div>
        </div>
    );
}
