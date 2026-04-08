// resources/js/Pages/Admin/QRCodes.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useRef } from 'react';

const V = (n) => `var(${n})`;

function QRImage({ url, size = 200, dark = true }) {
    const bg = dark ? '0c0e14' : 'ffffff';
    const fg = dark ? 'e2e5eb' : '111827';
    const src = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(url)}&bgcolor=${bg}&color=${fg}&format=svg&margin=1`;
    return <img src={src} alt="QR" width={size} height={size} style={{ borderRadius: 12 }} onError={e => { e.target.style.display = 'none'; }} />;
}

function BranchCard({ branch, tenant, baseUrl }) {
    const shortUrl = `${baseUrl}/t/${branch.slug}`;
    const [copied, setCopied] = useState(false);

    const copy = () => {
        navigator.clipboard?.writeText(shortUrl).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <div style={{
            background: V('--t-card'), border: `1px solid ${V('--t-border')}`, borderRadius: 16,
            padding: 24, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 16,
        }}>
            <QRImage url={shortUrl} size={180} />

            <div style={{ textAlign: 'center' }}>
                <div style={{ fontSize: 18, fontWeight: 800, color: V('--t-text'), marginBottom: 4 }}>{branch.name}</div>
                {branch.address && <div style={{ fontSize: 11, color: V('--t-text-muted'), marginBottom: 8 }}>{branch.address}</div>}
                <div style={{
                    fontSize: 11, fontFamily: "'JetBrains Mono', monospace", color: V('--t-text-muted'),
                    background: V('--t-surface'), padding: '6px 12px', borderRadius: 8, display: 'inline-block',
                    wordBreak: 'break-all',
                }}>{shortUrl}</div>
            </div>

            <div style={{ display: 'flex', gap: 8, width: '100%' }}>
                <button onClick={copy} style={{
                    flex: 1, padding: '10px 16px', borderRadius: 10, border: `1px solid ${V('--t-border')}`,
                    background: 'transparent', color: copied ? V('--t-green') : V('--t-text-muted'),
                    fontSize: 12, fontWeight: 600, cursor: 'pointer', fontFamily: "'Outfit', sans-serif",
                    transition: 'all 0.2s',
                }}>
                    {copied ? '✓ Copiado' : 'Copiar URL'}
                </button>
                <button onClick={() => printPoster(branch, tenant, shortUrl)} style={{
                    flex: 1, padding: '10px 16px', borderRadius: 10, border: 'none',
                    background: V('--t-blue'), color: '#fff',
                    fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit', sans-serif",
                }}>
                    🖨 Imprimir
                </button>
            </div>
        </div>
    );
}

function printPoster(branch, tenant, shortUrl) {
    const qrSrc = `https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=${encodeURIComponent(shortUrl)}&bgcolor=ffffff&color=111827&format=svg&margin=2`;

    const html = `<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>QR - ${branch.name}</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Outfit', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #fff; }
    .poster { width: 210mm; padding: 20mm; text-align: center; }
    .logo-line { font-size: 14px; color: #888; margin-bottom: 8px; letter-spacing: 0.1em; text-transform: uppercase; }
    .branch-name { font-size: 36px; font-weight: 900; color: #111; margin-bottom: 6px; letter-spacing: -0.02em; }
    .branch-addr { font-size: 14px; color: #666; margin-bottom: 32px; }
    .qr-container { display: inline-block; padding: 24px; border: 3px solid #e5e7eb; border-radius: 24px; margin-bottom: 28px; }
    .qr-container img { display: block; }
    .cta { font-size: 28px; font-weight: 900; color: #111; margin-bottom: 8px; }
    .cta-sub { font-size: 16px; color: #666; margin-bottom: 24px; line-height: 1.5; }
    .url { font-size: 18px; color: #3B82F6; font-weight: 700; letter-spacing: 0.02em; }
    .steps { display: flex; gap: 24px; justify-content: center; margin-top: 32px; }
    .step { text-align: center; flex: 1; max-width: 160px; }
    .step-num { width: 36px; height: 36px; border-radius: 50%; background: #3B82F6; color: #fff; font-weight: 900; font-size: 16px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 8px; }
    .step-text { font-size: 13px; color: #666; line-height: 1.4; }
    .footer { margin-top: 40px; font-size: 11px; color: #aaa; letter-spacing: 0.08em; }
    @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style></head><body>
<div class="poster">
    <div class="logo-line">${tenant.name}</div>
    <div class="branch-name">${branch.name}</div>
    ${branch.address ? `<div class="branch-addr">${branch.address}</div>` : '<div style="height:20px"></div>'}

    <div class="qr-container">
        <img src="${qrSrc}" width="280" height="280" alt="QR Code">
    </div>

    <div class="cta">Escanea y toma tu turno</div>
    <div class="cta-sub">Usa la cámara de tu teléfono para<br>escanear el código y registrarte</div>
    <div class="url">${shortUrl.replace('https://', '').replace('http://', '')}</div>

    <div class="steps">
        <div class="step">
            <div class="step-num">1</div>
            <div class="step-text">Escanea el código QR con tu celular</div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-text">Selecciona el servicio que necesitas</div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div class="step-text">Recibe tu turno y sigue tu posición</div>
        </div>
    </div>

    <div class="footer">Powered by Olinora · Sistema de Gestión de Turnos</div>
</div>
</body></html>`;

    const w = window.open('', '_blank');
    w.document.write(html);
    w.document.close();
    setTimeout(() => w.print(), 500);
}

export default function QRCodes({ branches, tenant, baseUrl }) {
    return (
        <AuthenticatedLayout>
            <Head title="Códigos QR" />

            <div style={{ maxWidth: 1200, margin: '0 auto', padding: '32px 24px', fontFamily: "'Outfit', sans-serif" }}>
                <div style={{ marginBottom: 28 }}>
                    <h1 style={{ fontSize: 24, fontWeight: 800, color: V('--t-text'), margin: 0, letterSpacing: '-0.03em' }}>Códigos QR</h1>
                    <p style={{ fontSize: 13, color: V('--t-text-muted'), marginTop: 4 }}>
                        Imprime estos códigos y colócalos en tus sucursales para que los clientes tomen turno desde su celular
                    </p>
                </div>

                {/* Instructions banner */}
                <div style={{
                    background: `color-mix(in srgb, ${V('--t-blue')} 6%, transparent)`,
                    border: `1px solid color-mix(in srgb, ${V('--t-blue')} 15%, transparent)`,
                    borderRadius: 14, padding: '16px 20px', marginBottom: 24,
                    display: 'flex', alignItems: 'flex-start', gap: 12,
                }}>
                    <span style={{ fontSize: 20, flexShrink: 0 }}>💡</span>
                    <div>
                        <div style={{ fontSize: 13, fontWeight: 700, color: V('--t-text'), marginBottom: 4 }}>¿Cómo funciona?</div>
                        <div style={{ fontSize: 12, color: V('--t-text-muted'), lineHeight: 1.5 }}>
                            Cada sucursal tiene una URL corta única (ej: <code style={{ fontFamily: "'JetBrains Mono', monospace", background: V('--t-surface'), padding: '1px 6px', borderRadius: 4, fontSize: 11 }}>/t/sede-centro</code>).
                            El cliente escanea el QR → abre el kiosco en su celular → elige servicio → recibe su turno y puede seguir su posición en tiempo real.
                        </div>
                    </div>
                </div>

                {/* Branch QR cards */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: 16 }}>
                    {branches.map(branch => (
                        <BranchCard key={branch.id} branch={branch} tenant={tenant} baseUrl={baseUrl} />
                    ))}
                </div>

                {branches.length === 0 && (
                    <div style={{ textAlign: 'center', padding: 60, color: V('--t-text-muted') }}>
                        No hay sucursales activas
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
