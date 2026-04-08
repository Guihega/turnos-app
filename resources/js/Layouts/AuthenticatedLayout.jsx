import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ThemeSwitcher from '@/Theme/ThemeSwitcher';

// Use CSS variables — these resolve to whatever the current theme sets
const V = (name) => `var(${name})`;

const isAdminRole = (role) => ['admin', 'tenant_admin', 'super_admin'].includes(role);

export default function AuthenticatedLayout({ children }) {
    const user = usePage().props.auth.user;
    const [open, setOpen] = useState(false);
    const [userMenu, setUserMenu] = useState(false);
    const currentRoute = route().current();

    const navLinks = [
        { href: 'dashboard', label: 'Dashboard', icon: '◉' },
        { href: 'operator.index', label: 'Atención', icon: '◈' },
        { href: 'admin.dashboard', label: 'Admin', icon: '⬡' },
        { href: 'admin.analytics', label: 'Analytics', icon: '◈' },
        { href: 'display.index', label: 'Pantalla', icon: '▣' },
        { href: 'admin.reports.index', label: 'Reportes', icon: '◧' },
    ];

    const isActive = (name) => {
        if (name === 'admin.dashboard') return currentRoute === 'admin.dashboard';
        if (name === 'admin.analytics') return currentRoute === 'admin.analytics';
        if (name === 'admin.settings.edit') return currentRoute?.startsWith('admin.settings');
        return currentRoute === name;
    };

    return (
        <div style={{ minHeight: '100vh', background: V('--t-bg'), fontFamily: "'Outfit', -apple-system, BlinkMacSystemFont, sans-serif" }}>
            {/* ── Navbar ── */}
            <nav style={{ background: V('--t-card'), borderBottom: `1px solid ${V('--t-border')}`, position: 'sticky', top: 0, zIndex: 50 }}>
                <div style={{ maxWidth: 1440, margin: '0 auto', padding: '0 20px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', height: 56 }}>
                        {/* Left: Logo + Nav links */}
                        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                            <Link href={route('dashboard')} style={{ display: 'flex', alignItems: 'center', gap: 10, textDecoration: 'none', marginRight: 16 }}>
                                <div style={{ width: 32, height: 32, borderRadius: 9, background: `linear-gradient(135deg, ${V('--t-blue')}, ${V('--t-purple')})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 900, fontSize: 14, color: '#fff' }}>O</div>
                            </Link>

                            {/* Desktop nav links */}
                            <div className="turnos-nav-desktop" style={{ display: 'flex', gap: 2 }}>
                                {navLinks.map(link => {
                                    const active = isActive(link.href);
                                    let href;
                                    try { href = route(link.href); } catch { href = '#'; }
                                    return (
                                        <Link key={link.href} href={href} style={{
                                            padding: '8px 14px', borderRadius: 8, fontSize: 12, fontWeight: 600,
                                            textDecoration: 'none', display: 'flex', alignItems: 'center', gap: 5,
                                            transition: 'all 0.2s',
                                            background: active ? `color-mix(in srgb, ${V('--t-blue')} 8%, transparent)` : 'transparent',
                                            color: active ? V('--t-blue') : V('--t-text-muted'),
                                        }}
                                        onMouseEnter={e => { if (!active) e.currentTarget.style.color = V('--t-text-soft'); }}
                                        onMouseLeave={e => { if (!active) e.currentTarget.style.color = V('--t-text-muted'); }}>
                                            <span style={{ fontSize: 11 }}>{link.icon}</span> {link.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Right: Theme switcher + User dropdown */}
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            {/* Theme Switcher */}
                            <ThemeSwitcher compact />

                            {/* Desktop user menu */}
                            <div className="turnos-nav-desktop" style={{ position: 'relative' }}>
                                <button onClick={() => setUserMenu(!userMenu)} style={{
                                    background: 'transparent', border: `1px solid ${V('--t-border')}`, borderRadius: 8,
                                    padding: '6px 14px', color: V('--t-text-soft'), fontSize: 13, fontWeight: 500,
                                    cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 8,
                                    fontFamily: "'Outfit', sans-serif",
                                }}>
                                    <div style={{ width: 26, height: 26, borderRadius: '50%', background: `linear-gradient(135deg, ${V('--t-blue')}, ${V('--t-purple')})`, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontWeight: 700, fontSize: 11 }}>
                                        {user.name?.charAt(0)?.toUpperCase()}
                                    </div>
                                    {user.name}
                                    <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" style={{ opacity: 0.5 }}>
                                        <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                    </svg>
                                </button>

                                {userMenu && (<>
                                    <div onClick={() => setUserMenu(false)} style={{ position: 'fixed', inset: 0, zIndex: 40 }} />
                                    <div style={{
                                        position: 'absolute', right: 0, top: '100%', marginTop: 6, width: 200,
                                        background: V('--t-card'), border: `1px solid ${V('--t-border')}`, borderRadius: 10,
                                        boxShadow: V('--t-shadow-lg'), zIndex: 50, overflow: 'hidden',
                                        animation: 'tFadeUp 0.15s ease',
                                    }}>
                                        <div style={{ padding: '12px 16px', borderBottom: `1px solid ${V('--t-border')}` }}>
                                            <div style={{ fontSize: 13, fontWeight: 600, color: V('--t-text') }}>{user.name}</div>
                                            <div style={{ fontSize: 11, color: V('--t-text-muted') }}>{user.email}</div>
                                        </div>
                                        <Link href={route('profile.edit')} style={{ display: 'block', padding: '10px 16px', fontSize: 13, color: V('--t-text-soft'), textDecoration: 'none', transition: 'background 0.15s' }}
                                            onMouseEnter={e => e.currentTarget.style.background = V('--t-surface')}
                                            onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                            Perfil
                                        </Link>
                                        {isAdminRole(user.role) && (
                                            <Link href={route('admin.settings.edit')} style={{ display: 'block', padding: '10px 16px', fontSize: 13, color: V('--t-text-soft'), textDecoration: 'none', transition: 'background 0.15s' }}
                                                onMouseEnter={e => e.currentTarget.style.background = V('--t-surface')}
                                                onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                                ⚙ Personalización
                                            </Link>
                                        )}
                                        {isAdminRole(user.role) && (
                                            <Link href={route('admin.qr.index')} style={{ display: 'block', padding: '10px 16px', fontSize: 13, color: V('--t-text-soft'), textDecoration: 'none', transition: 'background 0.15s' }}
                                                onMouseEnter={e => e.currentTarget.style.background = V('--t-surface')}
                                                onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                                ⬡ Códigos QR
                                            </Link>
                                        )}
                                        <Link href={route('logout')} method="post" as="button" style={{ display: 'block', width: '100%', textAlign: 'left', padding: '10px 16px', fontSize: 13, color: V('--t-red'), background: 'none', border: 'none', cursor: 'pointer', fontFamily: "'Outfit', sans-serif", transition: 'background 0.15s' }}
                                            onMouseEnter={e => e.currentTarget.style.background = `color-mix(in srgb, ${V('--t-red')} 6%, transparent)`}
                                            onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                            Cerrar sesión
                                        </Link>
                                    </div>
                                </>)}
                            </div>

                            {/* Mobile hamburger */}
                            <button className="turnos-nav-mobile" onClick={() => setOpen(!open)} style={{
                                background: 'transparent', border: 'none', color: V('--t-text-soft'), cursor: 'pointer', padding: 6,
                            }}>
                                <svg width="22" height="22" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    {!open ? <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                           : <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />}
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Mobile dropdown */}
                {open && (
                    <div className="turnos-nav-mobile" style={{ borderTop: `1px solid ${V('--t-border')}`, padding: '8px 0', animation: 'tFadeUp 0.2s ease' }}>
                        {navLinks.map(link => {
                            const active = isActive(link.href);
                            let href;
                            try { href = route(link.href); } catch { href = '#'; }
                            return (
                                <Link key={link.href} href={href} onClick={() => setOpen(false)} style={{
                                    display: 'flex', alignItems: 'center', gap: 8, padding: '12px 20px',
                                    fontSize: 14, fontWeight: 600, textDecoration: 'none',
                                    color: active ? V('--t-blue') : V('--t-text-muted'),
                                    background: active ? `color-mix(in srgb, ${V('--t-blue')} 6%, transparent)` : 'transparent',
                                }}>
                                    <span>{link.icon}</span> {link.label}
                                </Link>
                            );
                        })}
                        <div style={{ borderTop: `1px solid ${V('--t-border')}`, margin: '8px 0', padding: '12px 20px' }}>
                            {/* Theme switcher in mobile menu */}
                            <div style={{ marginBottom: 12, display: 'flex', alignItems: 'center', gap: 8 }}>
                                <span style={{ fontSize: 12, color: V('--t-text-muted') }}>Tema:</span>
                                <ThemeSwitcher />
                            </div>
                            <div style={{ fontSize: 14, fontWeight: 600, color: V('--t-text') }}>{user.name}</div>
                            <div style={{ fontSize: 12, color: V('--t-text-muted'), marginBottom: 10 }}>{user.email}</div>
                            <Link href={route('profile.edit')} onClick={() => setOpen(false)} style={{ display: 'block', fontSize: 13, color: V('--t-text-soft'), textDecoration: 'none', padding: '6px 0' }}>Perfil</Link>
                            {isAdminRole(user.role) && (
                                <Link href={route('admin.settings.edit')} onClick={() => setOpen(false)} style={{ display: 'block', fontSize: 13, color: V('--t-text-soft'), textDecoration: 'none', padding: '6px 0' }}>
                                    ⚙ Personalización
                                </Link>
                            )}
                            {isAdminRole(user.role) && (
                                <Link href={route('admin.qr.index')} onClick={() => setOpen(false)} style={{ display: 'block', fontSize: 13, color: V('--t-text-soft'), textDecoration: 'none', padding: '6px 0' }}>
                                    ⬡ Códigos QR
                                </Link>
                            )}
                            <Link href={route('logout')} method="post" as="button" onClick={() => setOpen(false)} style={{ display: 'block', fontSize: 13, color: V('--t-red'), background: 'none', border: 'none', cursor: 'pointer', fontFamily: "'Outfit', sans-serif", padding: '6px 0', marginTop: 4 }}>Cerrar sesión</Link>
                        </div>
                    </div>
                )}
            </nav>

            {/* ── Main content ── */}
            <main>{children}</main>

            {/* ── Responsive styles ── */}
            <style>{`
                @keyframes tFadeUp { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
                .turnos-nav-mobile { display: none; }
                @media (max-width: 768px) {
                    .turnos-nav-desktop { display: none !important; }
                    .turnos-nav-mobile { display: block !important; }
                }
            `}</style>
        </div>
    );
}
