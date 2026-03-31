// resources/js/Components/TurnosUI.jsx
// ── Shared Design System for TurnosPro ──

import { Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

// ── Design Tokens ──
export const theme = {
    bg: '#0B0E14',
    cardBg: '#12161F',
    border: '#1E2432',
    textPrimary: '#E8ECF4',
    textSecondary: '#9BA3B5',
    textMuted: '#5C6478',
    accent: '#3B82F6',
    accentSoft: 'rgba(59,130,246,0.12)',
    success: '#10B981',
    warning: '#F59E0B',
    danger: '#EF4444',
    purple: '#8B5CF6',
};

export const statusMap = {
    waiting: { bg: 'rgba(234,179,8,0.12)', text: '#CA8A04', label: 'En espera' },
    called: { bg: 'rgba(59,130,246,0.12)', text: '#2563EB', label: 'Llamado' },
    in_progress: { bg: 'rgba(99,102,241,0.12)', text: '#4F46E5', label: 'En atención' },
    completed: { bg: 'rgba(16,185,129,0.12)', text: '#059669', label: 'Completado' },
    cancelled: { bg: 'rgba(239,68,68,0.12)', text: '#DC2626', label: 'Cancelado' },
    no_show: { bg: 'rgba(249,115,22,0.12)', text: '#EA580C', label: 'No presentado' },
    transferred: { bg: 'rgba(139,92,246,0.12)', text: '#7C3AED', label: 'Transferido' },
};

// ── Format helpers ──
export const fmtSeconds = (s) => {
    if (!s) return '0:00';
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${m}:${String(sec).padStart(2, '0')}`;
};

export const fmtMinutes = (s) => `${Math.round((s || 0) / 60)} min`;

// ── Card ──
export function Card({ children, className = '', style = {}, ...props }) {
    return (
        <div style={{
            background: theme.cardBg, borderRadius: 12, border: `1px solid ${theme.border}`,
            padding: 20, ...style,
        }} className={className} {...props}>
            {children}
        </div>
    );
}

// ── Badge ──
export function StatusBadge({ status }) {
    const s = statusMap[status] || statusMap.waiting;
    return (
        <span style={{
            background: s.bg, color: s.text, padding: '3px 10px', borderRadius: 5,
            fontSize: 11, fontWeight: 600, whiteSpace: 'nowrap',
        }}>{s.label}</span>
    );
}

// ── Button ──
export function Btn({ children, variant = 'primary', size = 'md', onClick, disabled, type = 'button', style: extraStyle = {}, ...props }) {
    const base = {
        border: 'none', borderRadius: 8, fontWeight: 600, cursor: disabled ? 'not-allowed' : 'pointer',
        transition: 'all 0.2s', display: 'inline-flex', alignItems: 'center', gap: 6,
        opacity: disabled ? 0.5 : 1, fontFamily: 'inherit',
    };
    const sizes = {
        sm: { padding: '6px 12px', fontSize: 11 },
        md: { padding: '8px 16px', fontSize: 12 },
        lg: { padding: '12px 24px', fontSize: 14 },
    };
    const variants = {
        primary: { background: theme.accent, color: '#fff' },
        success: { background: theme.success, color: '#fff' },
        danger: { background: theme.danger, color: '#fff' },
        warning: { background: theme.warning, color: '#000' },
        ghost: { background: 'transparent', color: theme.textSecondary, border: `1px solid ${theme.border}` },
        outline: { background: 'transparent', color: theme.accent, border: `1px solid ${theme.accent}` },
    };

    return (
        <button type={type} onClick={onClick} disabled={disabled}
            style={{ ...base, ...sizes[size], ...variants[variant], ...extraStyle }} {...props}>
            {children}
        </button>
    );
}

// ── Input ──
export function Input({ label, error, style: extraStyle = {}, ...props }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            {label && <label style={{ fontSize: 11, fontWeight: 500, color: theme.textMuted, textTransform: 'uppercase', letterSpacing: '0.05em' }}>{label}</label>}
            <input style={{
                background: theme.bg, color: theme.textPrimary, border: `1px solid ${error ? theme.danger : theme.border}`,
                borderRadius: 8, padding: '10px 14px', fontSize: 13, outline: 'none', fontFamily: 'inherit',
                transition: 'border-color 0.2s', ...extraStyle,
            }} {...props} />
            {error && <span style={{ fontSize: 11, color: theme.danger }}>{error}</span>}
        </div>
    );
}

// ── Select ──
export function Select({ label, options = [], error, style: extraStyle = {}, ...props }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            {label && <label style={{ fontSize: 11, fontWeight: 500, color: theme.textMuted, textTransform: 'uppercase', letterSpacing: '0.05em' }}>{label}</label>}
            <select style={{
                background: theme.bg, color: theme.textPrimary, border: `1px solid ${error ? theme.danger : theme.border}`,
                borderRadius: 8, padding: '10px 14px', fontSize: 13, outline: 'none', fontFamily: 'inherit',
                cursor: 'pointer', ...extraStyle,
            }} {...props}>
                {options.map(o => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                ))}
            </select>
            {error && <span style={{ fontSize: 11, color: theme.danger }}>{error}</span>}
        </div>
    );
}

// ── PageHeader ──
export function PageHeader({ title, subtitle, actions }) {
    return (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
            <div>
                <h1 style={{ fontSize: 22, fontWeight: 700, color: theme.textPrimary, margin: 0 }}>{title}</h1>
                {subtitle && <p style={{ fontSize: 13, color: theme.textMuted, margin: '4px 0 0' }}>{subtitle}</p>}
            </div>
            {actions && <div style={{ display: 'flex', gap: 8 }}>{actions}</div>}
        </div>
    );
}

// ── DataTable ──
export function DataTable({ columns, rows, onRowClick }) {
    return (
        <div style={{ overflowX: 'auto', borderRadius: 12, border: `1px solid ${theme.border}`, background: theme.cardBg }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                <thead>
                    <tr style={{ borderBottom: `1px solid ${theme.border}` }}>
                        {columns.map(col => (
                            <th key={col.key} style={{
                                padding: '12px 16px', textAlign: col.align || 'left', fontWeight: 500,
                                color: theme.textMuted, fontSize: 10, textTransform: 'uppercase', letterSpacing: '0.05em',
                            }}>{col.label}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr><td colSpan={columns.length} style={{ padding: 40, textAlign: 'center', color: theme.textMuted }}>Sin datos</td></tr>
                    )}
                    {rows.map((row, i) => (
                        <tr key={row.id || i}
                            onClick={() => onRowClick?.(row)}
                            style={{
                                borderBottom: i < rows.length - 1 ? `1px solid ${theme.border}` : 'none',
                                cursor: onRowClick ? 'pointer' : 'default',
                                transition: 'background 0.15s',
                            }}
                            onMouseEnter={e => e.currentTarget.style.background = 'rgba(59,130,246,0.04)'}
                            onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                            {columns.map(col => (
                                <td key={col.key} style={{
                                    padding: '12px 16px', color: theme.textSecondary, textAlign: col.align || 'left',
                                }}>{col.render ? col.render(row) : row[col.key]}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ── KPI Stat ──
export function Stat({ label, value, suffix, color, icon }) {
    return (
        <Card style={{ textAlign: 'center', padding: 16 }}>
            {icon && <div style={{ fontSize: 18, marginBottom: 4 }}>{icon}</div>}
            <div style={{ fontSize: 28, fontWeight: 700, color: color || theme.textPrimary, fontVariantNumeric: 'tabular-nums' }}>
                {value}{suffix && <span style={{ fontSize: 13, color: theme.textMuted, marginLeft: 2 }}>{suffix}</span>}
            </div>
            <div style={{ fontSize: 10, color: theme.textMuted, textTransform: 'uppercase', letterSpacing: '0.05em', marginTop: 4 }}>{label}</div>
        </Card>
    );
}

// ── Empty State ──
export function EmptyState({ icon = '📋', title, description, action }) {
    return (
        <div style={{ textAlign: 'center', padding: '60px 20px' }}>
            <div style={{ fontSize: 48, marginBottom: 12 }}>{icon}</div>
            <h3 style={{ fontSize: 16, fontWeight: 600, color: theme.textPrimary, margin: '0 0 8px' }}>{title}</h3>
            {description && <p style={{ fontSize: 13, color: theme.textMuted, margin: '0 0 16px' }}>{description}</p>}
            {action}
        </div>
    );
}

// ── Flash Messages ──
export function FlashMessages({ flash }) {
    if (!flash?.success && !flash?.error && !flash?.info) return null;

    const msg = flash.success || flash.error || flash.info;
    const color = flash.success ? theme.success : flash.error ? theme.danger : theme.accent;

    return (
        <div style={{
            background: `${color}15`, border: `1px solid ${color}30`, borderRadius: 8,
            padding: '10px 16px', marginBottom: 16, fontSize: 13, color,
        }}>
            {msg}
        </div>
    );
}

// ── Auto-refresh hook ──
export function useAutoRefresh(intervalMs = 10000) {
    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['activeTickets', 'todayStats', 'queues', 'waitingTickets', 'currentTicket', 'myStats'] });
        }, intervalMs);
        return () => clearInterval(id);
    }, [intervalMs]);
}

// ── Page Layout wrapper ──
export function PageLayout({ children }) {
    return (
        <div style={{
            fontFamily: "'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif",
            background: theme.bg, color: theme.textPrimary, minHeight: '100vh',
            padding: '24px 32px',
        }}>
            {children}
        </div>
    );
}

export default { theme, statusMap, Card, StatusBadge, Btn, Input, Select, PageHeader, DataTable, Stat, EmptyState, FlashMessages, PageLayout, fmtSeconds, fmtMinutes, useAutoRefresh };
