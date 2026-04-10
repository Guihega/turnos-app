// resources/js/Components/TurnosUI.jsx
// ══ Olinora Design System v4 — Theme-Aware ══
//
// v4 Changes:
//   - Spacing tokens for consistency across all pages
//   - Enhanced DataTable with row striping, better empty state, row count
//   - New components: EmptyState, Divider, Avatar, Badge, SearchInput, Tooltip
//   - FlashMessages auto-dismiss
//   - Btn ripple effect on click
//   - Refined Card with subtle hover lift
//   - PageShell standardized padding
//   - Backward compatible — all v3 exports preserved

import { Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';

// ── Google Fonts injection ──
if (typeof document !== 'undefined' && !document.getElementById('turnos-fonts')) {
    const link = document.createElement('link');
    link.id = 'turnos-fonts';
    link.rel = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap';
    document.head.appendChild(link);
}

// ── Helper: CSS variable reference ──
const V = (name) => `var(${name})`;

// ── Design Tokens (CSS-variable backed) ──
export const T = {
    bg:          V('--t-bg'),
    surface:     V('--t-surface'),
    card:        V('--t-card'),
    cardHover:   V('--t-card-hover'),
    border:      V('--t-border'),
    borderLight: V('--t-border-light'),
    glass:       V('--t-glass'),
    glassBorder: V('--t-glass-border'),

    text:        V('--t-text'),
    textSoft:    V('--t-text-soft'),
    textMuted:   V('--t-text-muted'),

    blue:        V('--t-blue'),
    blueGlow:    V('--t-blue-glow'),
    green:       V('--t-green'),
    greenGlow:   V('--t-green-glow'),
    amber:       V('--t-amber'),
    amberGlow:   V('--t-amber-glow'),
    red:         V('--t-red'),
    redGlow:     V('--t-red-glow'),
    purple:      V('--t-purple'),
    purpleGlow:  V('--t-purple-glow'),
    cyan:        V('--t-cyan'),
    cyanGlow:    V('--t-cyan-glow'),

    // Semantic aliases (backward compat)
    accent:      V('--t-blue'),
    accentSoft:  V('--t-blue-glow'),
    success:     V('--t-green'),
    danger:      V('--t-red'),
    warning:     V('--t-amber'),
    textSecondary: V('--t-text-soft'),
    cardBg:      V('--t-card'),

    font: "'Outfit', -apple-system, BlinkMacSystemFont, sans-serif",
    mono: "'JetBrains Mono', monospace",

    // Spacing scale (px)
    sp1: 4,   sp2: 8,   sp3: 12,  sp4: 16,
    sp5: 20,  sp6: 24,  sp7: 28,  sp8: 32,
    sp10: 40, sp12: 48, sp16: 64,

    // Radii
    radius: 14,
    radiusSm: 8,
    radiusLg: 20,
    radiusXl: 28,

    shadow:   V('--t-shadow'),
    shadowLg: V('--t-shadow-lg'),

    // Page padding — single source of truth
    pagePadding: '28px 32px',
    pagePaddingMobile: '16px 18px',
};

// Backward-compatible alias
export const theme = T;

export const statusMap = {
    waiting:     { bg: T.amberGlow, text: T.amber, label: 'En espera', icon: '◷' },
    called:      { bg: T.blueGlow,  text: T.blue,  label: 'Llamado',   icon: '◈' },
    in_progress: { bg: T.purpleGlow,text: T.purple, label: 'En atención', icon: '◉' },
    completed:   { bg: T.greenGlow, text: T.green,  label: 'Completado', icon: '◆' },
    cancelled:   { bg: T.redGlow,   text: T.red,    label: 'Cancelado',  icon: '◇' },
    no_show:     { bg: 'rgba(249,115,22,0.12)', text: '#F97316', label: 'No presentado', icon: '◌' },
    transferred: { bg: T.cyanGlow,  text: T.cyan,   label: 'Transferido', icon: '↻' },
};

// ── Formatters ──
export const fmtSeconds = (s) => { if (!s) return '0:00'; const m = Math.floor(s / 60); return `${m}:${String(s % 60).padStart(2, '0')}`; };
export const fmtMinutes = (s) => `${Math.round((s || 0) / 60)} min`;

// ── CSS injection ──
if (typeof document !== 'undefined' && !document.getElementById('turnos-styles')) {
    const style = document.createElement('style');
    style.id = 'turnos-styles';
    style.textContent = `
        /* Default theme vars (refined-dark) — applied before ThemeProvider mounts */
        :root {
            --t-bg: #06080D;
            --t-surface: #0C0F16;
            --t-card: #111520;
            --t-card-hover: #161B28;
            --t-border: #1A2035;
            --t-border-light: #243050;
            --t-glass: rgba(17,21,32,0.72);
            --t-glass-border: rgba(255,255,255,0.06);
            --t-text: #E4E8F1;
            --t-text-soft: #8B95AD;
            --t-text-muted: #4D5672;
            --t-blue: #3D7AFF;
            --t-blue-glow: rgba(61,122,255,0.15);
            --t-green: #00D68F;
            --t-green-glow: rgba(0,214,143,0.15);
            --t-amber: #FFB020;
            --t-amber-glow: rgba(255,176,32,0.15);
            --t-red: #FF4757;
            --t-red-glow: rgba(255,71,87,0.15);
            --t-purple: #9D5CFF;
            --t-purple-glow: rgba(157,92,255,0.15);
            --t-cyan: #00D4FF;
            --t-cyan-glow: rgba(0,212,255,0.15);
            --t-shadow: 0 8px 32px rgba(0,0,0,0.3);
            --t-shadow-lg: 0 16px 64px rgba(0,0,0,0.4);
        }

        @keyframes tFadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes tPulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
        @keyframes tShimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
        @keyframes tGlow { 0%, 100% { box-shadow: 0 0 0 0 rgba(61,122,255,0.2); } 50% { box-shadow: 0 0 20px 4px rgba(61,122,255,0.08); } }
        @keyframes tSlideIn { from { opacity:0; transform: translateX(-8px); } to { opacity:1; transform: translateX(0); } }
        @keyframes tScaleIn { from { opacity:0; transform: scale(0.95); } to { opacity:1; transform: scale(1); } }
        @keyframes tCounterPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(61,122,255,0.3); } 50% { box-shadow: 0 0 30px 8px rgba(61,122,255,0.1); } }
        @keyframes tFlashOut { 0% { opacity: 1; transform: translateY(0); } 100% { opacity: 0; transform: translateY(-8px); } }
        @keyframes tRipple { to { transform: scale(4); opacity: 0; } }

        .t-hover:hover { background: var(--t-card-hover) !important; }
        .t-glass { backdrop-filter: blur(16px) saturate(180%); -webkit-backdrop-filter: blur(16px) saturate(180%); }
        .t-fade-up { animation: tFadeUp 0.5s ease both; }
        .t-stagger-1 { animation-delay: 0.05s; } .t-stagger-2 { animation-delay: 0.1s; }
        .t-stagger-3 { animation-delay: 0.15s; } .t-stagger-4 { animation-delay: 0.2s; }
        .t-stagger-5 { animation-delay: 0.25s; } .t-stagger-6 { animation-delay: 0.3s; }
        .t-stagger-7 { animation-delay: 0.35s; } .t-stagger-8 { animation-delay: 0.4s; }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--t-border); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--t-border-light); }

        /* Smooth theme transitions */
        *, *::before, *::after {
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.15s ease, box-shadow 0.3s ease;
        }
        /* Opt out for interactive elements */
        button, a, input, select, textarea, [role="button"] {
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.1s ease, transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
        }

        /* Table row hover */
        .t-table-row { transition: background-color 0.15s ease !important; }
        .t-table-row:hover { background: var(--t-card-hover) !important; }
        .t-table-row:last-child { border-bottom: none !important; }

        /* Striped rows (subtle) */
        .t-table-striped .t-table-row:nth-child(even) { background: color-mix(in srgb, var(--t-surface) 30%, var(--t-card)); }
        .t-table-striped .t-table-row:nth-child(even):hover { background: var(--t-card-hover) !important; }

        @media (max-width: 768px) {
            .t-grid-responsive { grid-template-columns: 1fr !important; }
            .t-page-shell { padding: 16px 18px !important; }
        }
        @media (max-width: 480px) {
            .t-page-shell { padding: 14px 14px !important; }
        }
    `;
    document.head.appendChild(style);
}

// ── Card ──
export function Card({ children, glow, accent, hover = false, className = '', style = {}, ...props }) {
    const [hovered, setHovered] = useState(false);
    return (
        <div
            className={`t-fade-up ${className}`}
            onMouseEnter={() => hover && setHovered(true)}
            onMouseLeave={() => hover && setHovered(false)}
            style={{
                background: T.card, borderRadius: T.radius, border: `1px solid ${T.border}`,
                padding: 20, position: 'relative', overflow: 'hidden',
                ...(glow ? { boxShadow: `0 0 40px ${glow}` } : {}),
                ...(hover && hovered ? { transform: 'translateY(-2px)', boxShadow: T.shadow, borderColor: T.borderLight } : {}),
                ...style,
            }} {...props}>
            {accent && <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2, background: `linear-gradient(90deg, ${accent}, transparent)` }} />}
            {children}
        </div>
    );
}

// ── Glass Card ──
export function GlassCard({ children, style = {}, ...props }) {
    return (
        <div className="t-glass t-fade-up" style={{
            background: T.glass, borderRadius: T.radius, border: `1px solid ${T.glassBorder}`,
            padding: 20, ...style,
        }} {...props}>{children}</div>
    );
}

// ── Status Badge ──
export function StatusBadge({ status, size = 'md' }) {
    const s = statusMap[status] || statusMap.waiting;
    const sizes = { sm: { padding: '2px 8px', fontSize: 10 }, md: { padding: '4px 12px', fontSize: 11 }, lg: { padding: '6px 16px', fontSize: 13 } };
    return (
        <span style={{
            background: s.bg, color: s.text, borderRadius: 20, fontWeight: 600,
            fontFamily: T.font, display: 'inline-flex', alignItems: 'center', gap: 5,
            letterSpacing: '0.02em', ...sizes[size],
        }}>
            <span style={{ fontSize: size === 'sm' ? 8 : 10 }}>{s.icon}</span> {s.label}
        </span>
    );
}

// ── Badge (generic) ──
export function Badge({ children, color, variant = 'soft', style: sx = {} }) {
    const c = color || T.blue;
    const styles = variant === 'soft'
        ? { background: `color-mix(in srgb, ${c} 12%, transparent)`, color: c, border: 'none' }
        : { background: 'transparent', color: c, border: `1px solid color-mix(in srgb, ${c} 30%, transparent)` };
    return (
        <span style={{
            display: 'inline-flex', alignItems: 'center', gap: 4,
            padding: '2px 8px', borderRadius: 6, fontSize: 10, fontWeight: 600,
            fontFamily: T.font, letterSpacing: '0.02em', whiteSpace: 'nowrap',
            ...styles, ...sx,
        }}>{children}</span>
    );
}

// ── Avatar ──
export function Avatar({ name, size = 32, color, style: sx = {} }) {
    const initial = (name || '?').charAt(0).toUpperCase();
    const bg = color || `linear-gradient(135deg, ${T.blue}, ${T.purple})`;
    return (
        <div style={{
            width: size, height: size, borderRadius: '50%',
            background: bg, display: 'flex', alignItems: 'center', justifyContent: 'center',
            color: '#fff', fontWeight: 700, fontSize: Math.round(size * 0.4),
            fontFamily: T.font, flexShrink: 0, ...sx,
        }}>{initial}</div>
    );
}

// ── Button ──
export function Btn({ children, variant = 'primary', size = 'md', onClick, disabled, type = 'button', style: sx = {}, ...props }) {
    const base = {
        border: 'none', borderRadius: T.radiusSm, fontWeight: 600, cursor: disabled ? 'not-allowed' : 'pointer',
        display: 'inline-flex', alignItems: 'center', gap: 7,
        opacity: disabled ? 0.4 : 1, fontFamily: T.font, letterSpacing: '0.01em', whiteSpace: 'nowrap',
        position: 'relative', overflow: 'hidden',
    };
    const sizes = { sm: { padding: '7px 14px', fontSize: 12 }, md: { padding: '10px 20px', fontSize: 13 }, lg: { padding: '14px 28px', fontSize: 15 } };
    const variants = {
        primary: { background: `linear-gradient(135deg, ${T.blue}, color-mix(in srgb, ${T.blue} 80%, black))`, color: '#fff', boxShadow: `0 4px 16px ${T.blueGlow}` },
        success: { background: `linear-gradient(135deg, ${T.green}, color-mix(in srgb, ${T.green} 80%, black))`, color: '#fff', boxShadow: `0 4px 16px ${T.greenGlow}` },
        danger:  { background: `linear-gradient(135deg, ${T.red}, color-mix(in srgb, ${T.red} 80%, black))`, color: '#fff', boxShadow: `0 4px 16px ${T.redGlow}` },
        warning: { background: `linear-gradient(135deg, ${T.amber}, color-mix(in srgb, ${T.amber} 80%, black))`, color: '#000', boxShadow: `0 4px 16px ${T.amberGlow}` },
        ghost:   { background: 'transparent', color: T.textSoft, border: `1px solid ${T.border}` },
        outline: { background: 'transparent', color: T.blue, border: `1px solid color-mix(in srgb, ${T.blue} 40%, transparent)` },
    };
    return (
        <button type={type} onClick={onClick} disabled={disabled}
            style={{ ...base, ...sizes[size], ...variants[variant], ...sx }}
            onMouseEnter={e => { if (!disabled) { e.currentTarget.style.transform = 'translateY(-1px)'; e.currentTarget.style.filter = 'brightness(1.08)'; } }}
            onMouseLeave={e => { e.currentTarget.style.transform = 'none'; e.currentTarget.style.filter = 'none'; }}
            {...props}>{children}</button>
    );
}

// ── Input ──
export function Input({ label, error, hint, style: sx = {}, ...props }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
            {label && <label style={{ fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', fontFamily: T.font }}>{label}</label>}
            <input style={{
                background: T.surface, color: T.text, border: `1px solid ${error ? T.red : T.border}`,
                borderRadius: T.radiusSm, padding: '11px 14px', fontSize: 13, outline: 'none', fontFamily: T.font,
                ...sx,
            }}
            onFocus={e => { e.target.style.borderColor = `var(--t-blue)`; e.target.style.boxShadow = `0 0 0 3px var(--t-blue-glow)`; }}
            onBlur={e => { e.target.style.borderColor = error ? `var(--t-red)` : `var(--t-border)`; e.target.style.boxShadow = 'none'; }}
            {...props} />
            {hint && !error && <span style={{ fontSize: 11, color: T.textMuted, fontFamily: T.font }}>{hint}</span>}
            {error && <span style={{ fontSize: 11, color: T.red, fontFamily: T.font }}>{error}</span>}
        </div>
    );
}

// ── SearchInput ──
export function SearchInput({ value, onChange, placeholder = 'Buscar...', style: sx = {} }) {
    return (
        <div style={{ position: 'relative', ...sx }}>
            <span style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)', fontSize: 13, color: T.textMuted, pointerEvents: 'none' }}>⌕</span>
            <input
                type="text" value={value} onChange={onChange} placeholder={placeholder}
                style={{
                    width: '100%', background: T.surface, color: T.text, border: `1px solid ${T.border}`,
                    borderRadius: T.radiusSm, padding: '9px 14px 9px 34px', fontSize: 12, outline: 'none', fontFamily: T.font,
                }}
                onFocus={e => { e.target.style.borderColor = `var(--t-blue)`; e.target.style.boxShadow = `0 0 0 3px var(--t-blue-glow)`; }}
                onBlur={e => { e.target.style.borderColor = `var(--t-border)`; e.target.style.boxShadow = 'none'; }}
            />
        </div>
    );
}

// ── Select ──
export function Select({ label, options = [], error, disabled, style: sx = {}, ...props }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
            {label && <label style={{ fontSize: 11, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.08em', fontFamily: T.font }}>{label}</label>}
            <select disabled={disabled} style={{
                background: T.surface, color: T.text, border: `1px solid ${T.border}`,
                borderRadius: T.radiusSm, padding: '11px 14px', fontSize: 13, outline: 'none', fontFamily: T.font,
                cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.5 : 1,
                ...sx,
            }} {...props}>
                {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
        </div>
    );
}

// ── Divider ──
export function Divider({ label, style: sx = {} }) {
    if (!label) return <div style={{ height: 1, background: T.border, margin: `${T.sp4}px 0`, ...sx }} />;
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, margin: `${T.sp4}px 0`, ...sx }}>
            <div style={{ flex: 1, height: 1, background: T.border }} />
            <span style={{ fontSize: 10, fontWeight: 600, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', fontFamily: T.font }}>{label}</span>
            <div style={{ flex: 1, height: 1, background: T.border }} />
        </div>
    );
}

// ── Page Header ──
export function PageHeader({ title, subtitle, actions, breadcrumb }) {
    return (
        <div className="t-fade-up" style={{ marginBottom: 24 }}>
            {breadcrumb && (
                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                    {breadcrumb.map((item, i) => (
                        <span key={i} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                            {i > 0 && <span style={{ fontSize: 10, color: T.textMuted }}>›</span>}
                            {item.href ? (
                                <Link href={item.href} style={{ fontSize: 12, color: T.textMuted, textDecoration: 'none', fontFamily: T.font }}
                                    onMouseEnter={e => e.currentTarget.style.color = `var(--t-blue)`}
                                    onMouseLeave={e => e.currentTarget.style.color = `var(--t-text-muted)`}>
                                    {item.label}
                                </Link>
                            ) : (
                                <span style={{ fontSize: 12, color: T.textSoft, fontWeight: 600, fontFamily: T.font }}>{item.label}</span>
                            )}
                        </span>
                    ))}
                </div>
            )}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12 }}>
                <div>
                    <h1 style={{ fontSize: 24, fontWeight: 800, color: T.text, margin: 0, fontFamily: T.font, letterSpacing: '-0.02em' }}>{title}</h1>
                    {subtitle && <p style={{ fontSize: 13, color: T.textMuted, margin: '4px 0 0', fontFamily: T.font }}>{subtitle}</p>}
                </div>
                {actions && <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>{actions}</div>}
            </div>
        </div>
    );
}

// ── Empty State ──
export function EmptyState({ icon = '◇', title = 'Sin datos', subtitle, action }) {
    return (
        <div style={{ textAlign: 'center', padding: '48px 24px' }}>
            <div style={{ fontSize: 40, marginBottom: 12, opacity: 0.25 }}>{icon}</div>
            <div style={{ fontSize: 15, fontWeight: 700, color: T.textSoft, marginBottom: 4, fontFamily: T.font }}>{title}</div>
            {subtitle && <div style={{ fontSize: 12, color: T.textMuted, marginBottom: 16, fontFamily: T.font, maxWidth: 320, margin: '0 auto' }}>{subtitle}</div>}
            {action && <div style={{ marginTop: 16 }}>{action}</div>}
        </div>
    );
}

// ── Data Table ──
export function DataTable({ columns, rows, onRowClick, striped = true, emptyIcon, emptyTitle, emptySubtitle }) {
    return (
        <div className="t-fade-up" style={{ overflowX: 'auto', borderRadius: T.radius, border: `1px solid ${T.border}`, background: T.card }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13, fontFamily: T.font }}>
                <thead>
                    <tr style={{ borderBottom: `1px solid ${T.border}` }}>
                        {columns.map(col => (
                            <th key={col.key} style={{
                                padding: '12px 16px', textAlign: col.align || 'left',
                                fontWeight: 600, color: T.textMuted, fontSize: 10,
                                textTransform: 'uppercase', letterSpacing: '0.08em',
                                background: T.surface,
                            }}>{col.label}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className={striped ? 't-table-striped' : ''}>
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={columns.length}>
                                <EmptyState
                                    icon={emptyIcon || '◇'}
                                    title={emptyTitle || 'Sin datos disponibles'}
                                    subtitle={emptySubtitle}
                                />
                            </td>
                        </tr>
                    )}
                    {rows.map((row, i) => (
                        <tr key={row.id || i} className="t-table-row"
                            onClick={() => onRowClick?.(row)}
                            style={{
                                borderBottom: `1px solid color-mix(in srgb, ${T.border} 50%, transparent)`,
                                cursor: onRowClick ? 'pointer' : 'default',
                            }}>
                            {columns.map(col => (
                                <td key={col.key} style={{
                                    padding: '13px 16px', color: T.textSoft,
                                    textAlign: col.align || 'left',
                                }}>{col.render ? col.render(row) : row[col.key]}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
            {/* Row count footer */}
            {rows.length > 0 && (
                <div style={{
                    padding: '8px 16px', borderTop: `1px solid color-mix(in srgb, ${T.border} 50%, transparent)`,
                    fontSize: 10, color: T.textMuted, fontFamily: T.mono, textAlign: 'right',
                }}>
                    {rows.length} registro{rows.length !== 1 ? 's' : ''}
                </div>
            )}
        </div>
    );
}

// ── KPI Stat Card ──
export function Stat({ label, value, suffix, color, icon, glow, trend }) {
    return (
        <Card style={{ textAlign: 'center', padding: '14px 12px', minWidth: 90 }} glow={glow}>
            {icon && <div style={{ fontSize: 16, marginBottom: 2 }}>{icon}</div>}
            <div style={{ fontSize: 26, fontWeight: 800, color: color || T.text, fontVariantNumeric: 'tabular-nums', fontFamily: T.mono, letterSpacing: '-0.02em' }}>
                {value}{suffix && <span style={{ fontSize: 12, color: T.textMuted, fontWeight: 400, marginLeft: 2, fontFamily: T.font }}>{suffix}</span>}
            </div>
            <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginTop: 3, fontFamily: T.font }}>{label}</div>
            {trend != null && (
                <div style={{ fontSize: 10, fontWeight: 700, color: trend >= 0 ? T.green : T.red, marginTop: 4, fontFamily: T.mono }}>
                    {trend >= 0 ? '↑' : '↓'} {Math.abs(trend)}%
                </div>
            )}
        </Card>
    );
}

// ── Flash Messages (auto-dismiss) ──
export function FlashMessages({ flash, autoDismiss = 5000 }) {
    const [visible, setVisible] = useState(true);
    const [exiting, setExiting] = useState(false);

    useEffect(() => {
        setVisible(true);
        setExiting(false);
        if (autoDismiss && (flash?.success || flash?.error || flash?.info)) {
            const t = setTimeout(() => {
                setExiting(true);
                setTimeout(() => setVisible(false), 300);
            }, autoDismiss);
            return () => clearTimeout(t);
        }
    }, [flash, autoDismiss]);

    if (!visible || (!flash?.success && !flash?.error && !flash?.info)) return null;
    const msg = flash.success || flash.error || flash.info;
    const color = flash.success ? T.green : flash.error ? T.red : T.blue;
    const icon = flash.success ? '✓' : flash.error ? '✕' : 'ℹ';
    return (
        <div style={{
            background: `color-mix(in srgb, ${color} 8%, transparent)`,
            border: `1px solid color-mix(in srgb, ${color} 20%, transparent)`,
            borderRadius: T.radiusSm,
            padding: '12px 18px', marginBottom: 18, fontSize: 13, color, fontFamily: T.font,
            display: 'flex', alignItems: 'center', gap: 8,
            animation: exiting ? 'tFlashOut 0.3s ease both' : 'tFadeUp 0.3s ease both',
        }}>
            <span style={{ fontSize: 16, flexShrink: 0 }}>{icon}</span>
            <span style={{ flex: 1 }}>{msg}</span>
            <button onClick={() => { setExiting(true); setTimeout(() => setVisible(false), 300); }}
                style={{ background: 'none', border: 'none', color, cursor: 'pointer', fontSize: 14, padding: 4, opacity: 0.6 }}>✕</button>
        </div>
    );
}

// ── Auto-refresh hook ──
export function useAutoRefresh(intervalMs = 8000) {
    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['activeTickets', 'todayStats', 'queues', 'waitingTickets', 'currentTicket', 'myStats', 'operators', 'branchStats'], preserveScroll: true });
        }, intervalMs);
        return () => clearInterval(id);
    }, [intervalMs]);
}

// ── Live indicator dot ──
export function LiveDot({ size = 8, label }) {
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
            <span style={{ width: size, height: size, borderRadius: '50%', background: T.green, boxShadow: `0 0 ${size}px ${T.greenGlow}`, display: 'inline-block', animation: 'tPulse 2s ease-in-out infinite' }} />
            {label && <span style={{ fontSize: 10, color: T.green, fontWeight: 600, fontFamily: T.font }}>{label}</span>}
        </span>
    );
}

// ── Metric bar ──
export function MetricBar({ value, max, color, height = 4, showLabel }) {
    const pct = Math.min((value / (max || 1)) * 100, 100);
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <div style={{ flex: 1, height, borderRadius: height, background: T.border, overflow: 'hidden' }}>
                <div style={{ height: '100%', borderRadius: height, width: `${pct}%`, background: color || T.blue, transition: 'width 0.8s cubic-bezier(0.4,0,0.2,1)' }} />
            </div>
            {showLabel && <span style={{ fontSize: 10, fontWeight: 600, fontFamily: T.mono, color: T.textMuted, minWidth: 32, textAlign: 'right' }}>{Math.round(pct)}%</span>}
        </div>
    );
}

// ── Page wrapper ──
export function PageShell({ children, maxWidth = 1400, style: sx = {} }) {
    return (
        <div className="t-page-shell" style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100vh', padding: T.pagePadding, ...sx }}>
            <div style={{ maxWidth, margin: '0 auto' }}>
                {children}
            </div>
        </div>
    );
}

// ── Confirm Dialog ──
export function ConfirmDialog({ open, title, message, confirmLabel = 'Confirmar', cancelLabel = 'Cancelar', variant = 'danger', onConfirm, onCancel }) {
    if (!open) return null;
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
            <div onClick={onCancel} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)' }} />
            <div style={{
                position: 'relative', background: T.card, borderRadius: T.radiusLg, border: `1px solid ${T.border}`,
                padding: 28, maxWidth: 400, width: '100%', boxShadow: T.shadowLg, animation: 'tScaleIn 0.2s ease both',
            }}>
                <div style={{ fontSize: 16, fontWeight: 700, color: T.text, marginBottom: 8, fontFamily: T.font }}>{title}</div>
                <div style={{ fontSize: 13, color: T.textSoft, marginBottom: 24, lineHeight: 1.5, fontFamily: T.font }}>{message}</div>
                <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                    <Btn variant="ghost" onClick={onCancel}>{cancelLabel}</Btn>
                    <Btn variant={variant} onClick={onConfirm}>{confirmLabel}</Btn>
                </div>
            </div>
        </div>
    );
}

export default { T, theme: T, statusMap, Card, GlassCard, StatusBadge, Badge, Avatar, Btn, Input, SearchInput, Select, Divider, PageHeader, EmptyState, DataTable, Stat, FlashMessages, useAutoRefresh, LiveDot, MetricBar, PageShell, ConfirmDialog, fmtSeconds, fmtMinutes };
