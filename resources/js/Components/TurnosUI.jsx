// resources/js/Components/TurnosUI.jsx
// ══ Olinora Design System v3 — Theme-Aware ══
//
// Migration: T tokens now resolve to CSS custom properties via var().
// All existing code using T.blue, T.bg etc. continues to work unchanged.
// Theme switching is handled by ThemeContext which sets --t-* vars on :root.

import { Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

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
// These resolve at render time to whatever the current theme sets.
// Fallbacks match the 'refined-dark' theme for SSR/initial paint.
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

    font: "'Outfit', -apple-system, BlinkMacSystemFont, sans-serif",
    mono: "'JetBrains Mono', monospace",

    radius: 14,
    radiusSm: 8,
    radiusLg: 20,
    radiusXl: 28,
    shadow:   V('--t-shadow'),
    shadowLg: V('--t-shadow-lg'),
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

// ── CSS injection (now uses CSS vars) ──
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

        /* ── A11y: focus visible para navegación por teclado ── */
        *:focus { outline: none; }
        *:focus-visible {
            outline: 2px solid var(--t-blue);
            outline-offset: 2px;
            border-radius: 4px;
        }
        button:focus-visible,
        a:focus-visible,
        [role="button"]:focus-visible {
            outline: 2px solid var(--t-blue);
            outline-offset: 2px;
            box-shadow: 0 0 0 4px var(--t-blue-glow);
        }
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            outline: 2px solid var(--t-blue);
            outline-offset: 0;
            border-color: var(--t-blue);
        }

        /* ── A11y: respetar prefers-reduced-motion ── */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        /* Smooth theme transitions */
        *, *::before, *::after {
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.15s ease, box-shadow 0.3s ease;
        }
        /* Opt out for interactive elements */
        button, a, input, select, [role="button"] {
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.1s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }

        @media (max-width: 768px) { .t-grid-responsive { grid-template-columns: 1fr !important; } }
    `;
    document.head.appendChild(style);
}

// ── Card ──
export function Card({ children, glow, accent, className = '', style = {}, ...props }) {
    return (
        <div className={`t-fade-up ${className}`} style={{
            background: T.card, borderRadius: T.radius, border: `1px solid ${T.border}`,
            padding: 20, position: 'relative', overflow: 'hidden',
            ...(glow ? { boxShadow: `0 0 40px ${glow}` } : {}),
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
            onMouseEnter={e => { if (!disabled) e.currentTarget.style.transform = 'translateY(-1px)'; }}
            onMouseLeave={e => { e.currentTarget.style.transform = 'none'; }}
            {...props}>{children}</button>
    );
}

// ── Input ──
export function Input({ label, error, style: sx = {}, ...props }) {
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
            {error && <span style={{ fontSize: 11, color: T.red, fontFamily: T.font }}>{error}</span>}
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

// ── Page Header ──
export function PageHeader({ title, subtitle, actions }) {
    return (
        <div className="t-fade-up" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 28, flexWrap: 'wrap', gap: 12 }}>
            <div>
                <h1 style={{ fontSize: 24, fontWeight: 800, color: T.text, margin: 0, fontFamily: T.font, letterSpacing: '-0.02em' }}>{title}</h1>
                {subtitle && <p style={{ fontSize: 13, color: T.textMuted, margin: '4px 0 0', fontFamily: T.font }}>{subtitle}</p>}
            </div>
            {actions && <div style={{ display: 'flex', gap: 8 }}>{actions}</div>}
        </div>
    );
}

// ── Data Table ──
export function DataTable({ columns, rows, onRowClick }) {
    return (
        <div className="t-fade-up" style={{ overflowX: 'auto', borderRadius: T.radius, border: `1px solid ${T.border}`, background: T.card }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13, fontFamily: T.font }}>
                <thead>
                    <tr style={{ borderBottom: `1px solid ${T.border}` }}>
                        {columns.map(col => (
                            <th key={col.key} style={{ padding: '14px 16px', textAlign: col.align || 'left', fontWeight: 600, color: T.textMuted, fontSize: 10, textTransform: 'uppercase', letterSpacing: '0.08em' }}>{col.label}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && <tr><td colSpan={columns.length} style={{ padding: 48, textAlign: 'center', color: T.textMuted }}>Sin datos disponibles</td></tr>}
                    {rows.map((row, i) => (
                        <tr key={row.id || i} className="t-hover"
                            onClick={() => onRowClick?.(row)}
                            style={{ borderBottom: i < rows.length - 1 ? `1px solid ${T.border}` : 'none', cursor: onRowClick ? 'pointer' : 'default' }}>
                            {columns.map(col => (
                                <td key={col.key} style={{ padding: '14px 16px', color: T.textSoft, textAlign: col.align || 'left' }}>{col.render ? col.render(row) : row[col.key]}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ── KPI Stat Card ──
export function Stat({ label, value, suffix, color, icon, glow }) {
    return (
        <Card style={{ textAlign: 'center', padding: '14px 12px', minWidth: 90 }} glow={glow}>
            {icon && <div style={{ fontSize: 16, marginBottom: 2 }}>{icon}</div>}
            <div style={{ fontSize: 26, fontWeight: 800, color: color || T.text, fontVariantNumeric: 'tabular-nums', fontFamily: T.mono, letterSpacing: '-0.02em' }}>
                {value}{suffix && <span style={{ fontSize: 12, color: T.textMuted, fontWeight: 400, marginLeft: 2, fontFamily: T.font }}>{suffix}</span>}
            </div>
            <div style={{ fontSize: 9, color: T.textMuted, textTransform: 'uppercase', letterSpacing: '0.1em', marginTop: 3, fontFamily: T.font }}>{label}</div>
        </Card>
    );
}

// ── Flash Messages ──
export function FlashMessages({ flash }) {
    if (!flash?.success && !flash?.error && !flash?.info) return null;
    const msg = flash.success || flash.error || flash.info;
    const color = flash.success ? T.green : flash.error ? T.red : T.blue;
    return (
        <div className="t-fade-up" style={{
            background: `color-mix(in srgb, ${color} 8%, transparent)`,
            border: `1px solid color-mix(in srgb, ${color} 20%, transparent)`,
            borderRadius: T.radiusSm,
            padding: '12px 18px', marginBottom: 18, fontSize: 13, color, fontFamily: T.font,
            display: 'flex', alignItems: 'center', gap: 8,
        }}>
            <span style={{ fontSize: 16 }}>{flash.success ? '✓' : flash.error ? '✕' : 'ℹ'}</span> {msg}
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
export function LiveDot({ size = 8 }) {
    return <span style={{ width: size, height: size, borderRadius: '50%', background: T.green, boxShadow: `0 0 ${size}px ${T.greenGlow}`, display: 'inline-block', animation: 'tPulse 2s ease-in-out infinite' }} />;
}

// ── Metric bar ──
export function MetricBar({ value, max, color, height = 4 }) {
    const pct = Math.min((value / (max || 1)) * 100, 100);
    return (
        <div style={{ height, borderRadius: height, background: T.border, overflow: 'hidden' }}>
            <div style={{ height: '100%', borderRadius: height, width: `${pct}%`, background: color || T.blue, transition: 'width 0.8s cubic-bezier(0.4,0,0.2,1)' }} />
        </div>
    );
}

// ── Page wrapper ──
export function PageShell({ children }) {
    return (
        <div style={{ fontFamily: T.font, background: T.bg, color: T.text, minHeight: '100vh', padding: '24px 28px' }}>
            {children}
        </div>
    );
}

// ══════════════════════════════════════════════════════════════════════
// Componentes v4 — agregados en Fase 1 para compatibilidad con páginas Admin
// ══════════════════════════════════════════════════════════════════════

// ── Badge ── chip de estado / etiqueta. Acepta color semántico o custom.
export function Badge({ children, color = 'neutral', size = 'md', style: sx = {} }) {
    const colors = {
        neutral: { bg: 'color-mix(in srgb, var(--t-text-muted) 15%, transparent)', fg: T.textSoft, border: T.border },
        blue: { bg: T.blueGlow, fg: T.blue, border: `color-mix(in srgb, ${T.blue} 30%, transparent)` },
        green: { bg: T.greenGlow, fg: T.green, border: `color-mix(in srgb, ${T.green} 30%, transparent)` },
        amber: { bg: T.amberGlow, fg: T.amber, border: `color-mix(in srgb, ${T.amber} 30%, transparent)` },
        red: { bg: T.redGlow, fg: T.red, border: `color-mix(in srgb, ${T.red} 30%, transparent)` },
        purple: { bg: T.purpleGlow, fg: T.purple, border: `color-mix(in srgb, ${T.purple} 30%, transparent)` },
        cyan: { bg: T.cyanGlow, fg: T.cyan, border: `color-mix(in srgb, ${T.cyan} 30%, transparent)` },
    };
    const c = colors[color] || colors.neutral;
    const sizes = {
        sm: { fontSize: 10, padding: '3px 8px' },
        md: { fontSize: 11, padding: '4px 10px' },
        lg: { fontSize: 12, padding: '5px 12px' },
    };
    const s = sizes[size] || sizes.md;
    return (
        <span style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: 4,
            ...s,
            fontWeight: 700,
            letterSpacing: '0.04em',
            textTransform: 'uppercase',
            color: c.fg,
            background: c.bg,
            border: `1px solid ${c.border}`,
            borderRadius: 999,
            whiteSpace: 'nowrap',
            ...sx,
        }}>
            {children}
        </span>
    );
}

// ── Avatar ── círculo con iniciales o imagen
export function Avatar({ name = '', src, size = 36, color, style: sx = {} }) {
    const initials = name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(w => w[0])
        .join('')
        .toUpperCase();

    // Color derivado del nombre (hash simple)
    const colors = [T.blue, T.green, T.amber, T.red, T.purple, T.cyan];
    const hash = name.split('').reduce((acc, ch) => acc + ch.charCodeAt(0), 0);
    const bg = color || colors[hash % colors.length];

    const baseStyle = {
        width: size,
        height: size,
        borderRadius: '50%',
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontWeight: 700,
        fontSize: Math.round(size * 0.4),
        color: '#fff',
        background: bg,
        flexShrink: 0,
        ...sx,
    };

    if (src) {
        return (
            <img
                src={src}
                alt={name}
                aria-label={name}
                style={{ ...baseStyle, objectFit: 'cover' }}
            />
        );
    }

    return (
        <span
            aria-label={name}
            role="img"
            style={baseStyle}
        >
            {initials || '?'}
        </span>
    );
}

// ── EmptyState ── para listas y tablas vacías
export function EmptyState({ icon, title, subtitle, action, style: sx = {} }) {
    return (
        <div style={{
            textAlign: 'center',
            padding: '48px 24px',
            color: T.textSoft,
            ...sx,
        }}>
            {icon && (
                <div style={{
                    fontSize: 48,
                    marginBottom: 16,
                    opacity: 0.5,
                    color: T.textMuted,
                }}>
                    {icon}
                </div>
            )}
            {title && (
                <div style={{
                    fontSize: 16,
                    fontWeight: 700,
                    color: T.text,
                    marginBottom: 8,
                }}>
                    {title}
                </div>
            )}
            {subtitle && (
                <div style={{
                    fontSize: 13,
                    color: T.textMuted,
                    lineHeight: 1.5,
                    maxWidth: 420,
                    margin: '0 auto 20px',
                }}>
                    {subtitle}
                </div>
            )}
            {action && (
                <div style={{ marginTop: 16 }}>
                    {action}
                </div>
            )}
        </div>
    );
}

// ── SearchInput ── input de búsqueda con icono
export function SearchInput({ value, onChange, placeholder = 'Buscar...', style: sx = {} }) {
    return (
        <div style={{ position: 'relative', ...sx }}>
            <span style={{
                position: 'absolute',
                left: 12,
                top: '50%',
                transform: 'translateY(-50%)',
                fontSize: 14,
                color: T.textMuted,
                pointerEvents: 'none',
            }}>
                🔍
            </span>
            <input
                type="search"
                value={value}
                onChange={e => onChange && onChange(e.target.value)}
                placeholder={placeholder}
                style={{
                    width: '100%',
                    padding: '10px 14px 10px 36px',
                    fontSize: 13,
                    color: T.text,
                    background: T.card,
                    border: `1px solid ${T.border}`,
                    borderRadius: 8,
                    outline: 'none',
                    fontFamily: T.font,
                }}
            />
        </div>
    );
}

// ── Divider ── separador horizontal
export function Divider({ label, style: sx = {} }) {
    if (!label) {
        return (
            <div style={{
                height: 1,
                background: T.border,
                margin: '20px 0',
                ...sx,
            }} />
        );
    }
    return (
        <div style={{
            display: 'flex',
            alignItems: 'center',
            gap: 12,
            margin: '20px 0',
            ...sx,
        }}>
            <div style={{ flex: 1, height: 1, background: T.border }} />
            <span style={{
                fontSize: 11,
                fontWeight: 700,
                color: T.textMuted,
                letterSpacing: '0.08em',
                textTransform: 'uppercase',
            }}>
                {label}
            </span>
            <div style={{ flex: 1, height: 1, background: T.border }} />
        </div>
    );
}

// ── Tooltip ── básico con hover
export function Tooltip({ children, content, position = 'top' }) {
    const [visible, setVisible] = useState(false);

    const positionStyles = {
        top: { bottom: '100%', left: '50%', transform: 'translateX(-50%)', marginBottom: 6 },
        bottom: { top: '100%', left: '50%', transform: 'translateX(-50%)', marginTop: 6 },
        left: { right: '100%', top: '50%', transform: 'translateY(-50%)', marginRight: 6 },
        right: { left: '100%', top: '50%', transform: 'translateY(-50%)', marginLeft: 6 },
    };

    return (
        <span
            style={{ position: 'relative', display: 'inline-block' }}
            onMouseEnter={() => setVisible(true)}
            onMouseLeave={() => setVisible(false)}
        >
            {children}
            {visible && content && (
                <span
                    role="tooltip"
                    style={{
                        position: 'absolute',
                        ...positionStyles[position],
                        background: T.card,
                        color: T.text,
                        fontSize: 11,
                        fontWeight: 600,
                        padding: '6px 10px',
                        borderRadius: 6,
                        border: `1px solid ${T.border}`,
                        boxShadow: T.shadow,
                        whiteSpace: 'nowrap',
                        zIndex: 1000,
                        pointerEvents: 'none',
                    }}
                >
                    {content}
                </span>
            )}
        </span>
    );
}

// ── ConfirmDialog ── diálogo de confirmación modal
export function ConfirmDialog({ open, onClose, onConfirm, title, message, confirmLabel = 'Confirmar', cancelLabel = 'Cancelar', variant = 'primary' }) {
    if (!open) return null;

    const confirmColor = variant === 'danger' ? T.red : T.blue;

    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed',
                inset: 0,
                background: 'rgba(0,0,0,0.6)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 9999,
                padding: 20,
                backdropFilter: 'blur(4px)',
            }}
        >
            <div
                onClick={e => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                style={{
                    background: T.card,
                    border: `1px solid ${T.border}`,
                    borderRadius: 12,
                    padding: '28px 28px 20px',
                    maxWidth: 440,
                    width: '100%',
                    boxShadow: T.shadowLg,
                    animation: 'tScaleIn 0.2s ease',
                }}
            >
                {title && (
                    <h3 style={{
                        fontSize: 18,
                        fontWeight: 800,
                        color: T.text,
                        marginBottom: 8,
                        letterSpacing: '-0.01em',
                    }}>
                        {title}
                    </h3>
                )}
                {message && (
                    <p style={{
                        fontSize: 14,
                        color: T.textSoft,
                        lineHeight: 1.5,
                        marginBottom: 24,
                    }}>
                        {message}
                    </p>
                )}
                <div style={{
                    display: 'flex',
                    justifyContent: 'flex-end',
                    gap: 10,
                }}>
                    <button
                        onClick={onClose}
                        style={{
                            padding: '9px 18px',
                            fontSize: 13,
                            fontWeight: 600,
                            color: T.textSoft,
                            background: 'transparent',
                            border: `1px solid ${T.border}`,
                            borderRadius: 8,
                            cursor: 'pointer',
                            fontFamily: T.font,
                        }}
                    >
                        {cancelLabel}
                    </button>
                    <button
                        onClick={onConfirm}
                        style={{
                            padding: '9px 18px',
                            fontSize: 13,
                            fontWeight: 700,
                            color: '#fff',
                            background: confirmColor,
                            border: 'none',
                            borderRadius: 8,
                            cursor: 'pointer',
                            fontFamily: T.font,
                        }}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default { T, theme: T, statusMap, Card, GlassCard, StatusBadge, Btn, Input, Select, PageHeader, DataTable, Stat, FlashMessages, useAutoRefresh, LiveDot, MetricBar, PageShell, Badge, Avatar, EmptyState, SearchInput, Divider, Tooltip, ConfirmDialog, fmtSeconds, fmtMinutes };
