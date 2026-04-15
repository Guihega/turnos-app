// resources/js/Components/LegalPageLayout.jsx
import { Head, Link } from '@inertiajs/react';

const V = (name) => `var(${name})`;

export default function LegalPageLayout({ title, lastUpdated, children }) {
    return (
        <>
            <Head title={title} />
            <div style={{
                minHeight: '100vh',
                background: V('--t-bg'),
                fontFamily: "'Outfit', -apple-system, BlinkMacSystemFont, sans-serif",
                color: V('--t-text'),
            }}>
                {/* Header */}
                <nav style={{
                    background: V('--t-card'),
                    borderBottom: `1px solid ${V('--t-border')}`,
                    padding: '0 20px',
                    position: 'sticky',
                    top: 0,
                    zIndex: 50,
                }}>
                    <div style={{ maxWidth: 900, margin: '0 auto', display: 'flex', alignItems: 'center', justifyContent: 'space-between', height: 56 }}>
                        <Link href="/" style={{ display: 'flex', alignItems: 'center', gap: 10, textDecoration: 'none' }}>
                            <div style={{
                                width: 32, height: 32, borderRadius: 9,
                                background: `linear-gradient(135deg, ${V('--t-blue')}, ${V('--t-purple')})`,
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontWeight: 900, fontSize: 14, color: '#fff',
                            }}>O</div>
                            <span style={{ fontWeight: 700, fontSize: 16, color: V('--t-text') }}>Olinora</span>
                        </Link>
                        <div style={{ display: 'flex', gap: 16 }}>
                            <Link href="/privacidad" style={{ fontSize: 13, color: V('--t-text-muted'), textDecoration: 'none' }}>Privacidad</Link>
                            <Link href="/terminos" style={{ fontSize: 13, color: V('--t-text-muted'), textDecoration: 'none' }}>Términos</Link>
                        </div>
                    </div>
                </nav>

                {/* Content */}
                <div style={{ maxWidth: 900, margin: '0 auto', padding: '40px 20px 80px' }}>
                    <h1 style={{ fontSize: 28, fontWeight: 800, color: V('--t-text'), marginBottom: 8 }}>{title}</h1>
                    <p style={{ fontSize: 13, color: V('--t-text-muted'), marginBottom: 40 }}>
                        Última actualización: {lastUpdated}
                    </p>

                    <div className="legal-content">
                        {children}
                    </div>
                </div>

                {/* Footer */}
                <div style={{
                    borderTop: `1px solid ${V('--t-border')}`,
                    padding: '24px 20px',
                    textAlign: 'center',
                }}>
                    <p style={{ fontSize: 12, color: V('--t-text-muted') }}>
                        © {new Date().getFullYear()} Olinora — Sistema de Gestión de Turnos.
                        {' '}<Link href="/privacidad" style={{ color: V('--t-blue'), textDecoration: 'none' }}>Privacidad</Link>
                        {' · '}<Link href="/terminos" style={{ color: V('--t-blue'), textDecoration: 'none' }}>Términos</Link>
                        {' · '}<Link href="/" style={{ color: V('--t-blue'), textDecoration: 'none' }}>Inicio</Link>
                    </p>
                </div>

                <style>{`
                    .legal-content h2 {
                        font-size: 20px;
                        font-weight: 700;
                        color: ${V('--t-text')};
                        margin: 32px 0 12px;
                    }
                    .legal-content h3 {
                        font-size: 16px;
                        font-weight: 600;
                        color: ${V('--t-text-soft')};
                        margin: 24px 0 8px;
                    }
                    .legal-content p {
                        font-size: 14px;
                        line-height: 1.7;
                        color: ${V('--t-text-soft')};
                        margin: 0 0 12px;
                    }
                    .legal-content ul {
                        margin: 8px 0 16px 20px;
                        padding: 0;
                    }
                    .legal-content li {
                        font-size: 14px;
                        line-height: 1.7;
                        color: ${V('--t-text-soft')};
                        margin-bottom: 4px;
                    }
                    .legal-content a {
                        color: ${V('--t-blue')};
                        text-decoration: none;
                    }
                    .legal-content a:hover {
                        text-decoration: underline;
                    }
                `}</style>
            </div>
        </>
    );
}
