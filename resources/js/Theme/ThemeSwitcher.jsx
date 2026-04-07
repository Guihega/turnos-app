// resources/js/Theme/ThemeSwitcher.jsx
// ══ Theme Picker UI — dropdown + customizer ══

import { useState, useRef, useEffect } from 'react';
import { useTheme } from './ThemeContext';

const V = (name) => `var(${name})`;

export default function ThemeSwitcher({ compact = false }) {
    const { themeId, setTheme, themes, isDark, customOverrides, setOverride, resetOverrides } = useTheme();
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState('presets'); // 'presets' | 'customize'
    const ref = useRef(null);

    // Close on outside click
    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const current = themes[themeId];
    const darkThemes = Object.entries(themes).filter(([, t]) => t.group === 'dark');
    const lightThemes = Object.entries(themes).filter(([, t]) => t.group === 'light');

    const customizableVars = [
        { key: '--t-blue', label: 'Primario' },
        { key: '--t-green', label: 'Éxito' },
        { key: '--t-amber', label: 'Advertencia' },
        { key: '--t-red', label: 'Peligro' },
        { key: '--t-purple', label: 'Acento' },
        { key: '--t-bg', label: 'Fondo' },
        { key: '--t-card', label: 'Tarjeta' },
        { key: '--t-text', label: 'Texto' },
    ];

    return (
        <div ref={ref} style={{ position: 'relative', display: 'inline-block' }}>
            {/* Trigger */}
            <button
                onClick={() => setOpen(!open)}
                style={{
                    background: 'transparent',
                    border: `1px solid ${V('--t-border')}`,
                    borderRadius: 8,
                    padding: compact ? '6px 10px' : '8px 14px',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 6,
                    color: V('--t-text-soft'),
                    fontSize: 12,
                    fontFamily: "'Outfit', sans-serif",
                    fontWeight: 500,
                    transition: 'all 0.2s',
                }}
                onMouseEnter={e => e.currentTarget.style.borderColor = V('--t-blue')}
                onMouseLeave={e => e.currentTarget.style.borderColor = V('--t-border')}
            >
                <span style={{ fontSize: 14 }}>{isDark ? '◑' : '◐'}</span>
                {!compact && <span>{current?.label || 'Tema'}</span>}
                <span style={{ fontSize: 8, opacity: 0.5 }}>▼</span>
            </button>

            {/* Dropdown */}
            {open && (
                <div style={{
                    position: 'absolute',
                    top: '100%',
                    right: 0,
                    marginTop: 6,
                    width: 300,
                    background: V('--t-card'),
                    border: `1px solid ${V('--t-border')}`,
                    borderRadius: 14,
                    boxShadow: V('--t-shadow-lg'),
                    zIndex: 9999,
                    overflow: 'hidden',
                    animation: 'tScaleIn 0.2s ease both',
                }}>
                    {/* Tabs */}
                    <div style={{
                        display: 'flex',
                        borderBottom: `1px solid ${V('--t-border')}`,
                    }}>
                        {['presets', 'customize'].map(t => (
                            <button
                                key={t}
                                onClick={() => setTab(t)}
                                style={{
                                    flex: 1,
                                    padding: '12px 0',
                                    background: 'transparent',
                                    border: 'none',
                                    borderBottom: tab === t ? `2px solid ${V('--t-blue')}` : '2px solid transparent',
                                    color: tab === t ? V('--t-text') : V('--t-text-muted'),
                                    fontSize: 11,
                                    fontWeight: 600,
                                    fontFamily: "'Outfit', sans-serif",
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.08em',
                                    cursor: 'pointer',
                                    transition: 'all 0.2s',
                                }}
                            >
                                {t === 'presets' ? '◆ Temas' : '⚙ Personalizar'}
                            </button>
                        ))}
                    </div>

                    <div style={{ padding: 14, maxHeight: 360, overflowY: 'auto' }}>
                        {tab === 'presets' ? (
                            <>
                                {/* Dark themes */}
                                <div style={{ fontSize: 9, color: V('--t-text-muted'), textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8, fontFamily: "'Outfit', sans-serif" }}>
                                    Temas Oscuros
                                </div>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 4, marginBottom: 16 }}>
                                    {darkThemes.map(([id, t]) => (
                                        <ThemeOption key={id} id={id} theme={t} active={themeId === id} onSelect={setTheme} />
                                    ))}
                                </div>

                                {/* Light themes */}
                                <div style={{ fontSize: 9, color: V('--t-text-muted'), textTransform: 'uppercase', letterSpacing: '0.1em', marginBottom: 8, fontFamily: "'Outfit', sans-serif" }}>
                                    Temas Claros
                                </div>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                                    {lightThemes.map(([id, t]) => (
                                        <ThemeOption key={id} id={id} theme={t} active={themeId === id} onSelect={setTheme} />
                                    ))}
                                </div>
                            </>
                        ) : (
                            <>
                                <div style={{ fontSize: 10, color: V('--t-text-muted'), marginBottom: 12, fontFamily: "'Outfit', sans-serif" }}>
                                    Personaliza los colores del tema actual ({current?.label})
                                </div>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                                    {customizableVars.map(({ key, label }) => (
                                        <ColorPicker
                                            key={key}
                                            label={label}
                                            varName={key}
                                            defaultValue={current?.vars[key] || '#000000'}
                                            override={customOverrides[key]}
                                            onChange={(val) => setOverride(key, val)}
                                        />
                                    ))}
                                </div>
                                {Object.keys(customOverrides).length > 0 && (
                                    <button
                                        onClick={resetOverrides}
                                        style={{
                                            marginTop: 14,
                                            width: '100%',
                                            padding: '8px 0',
                                            background: 'transparent',
                                            border: `1px solid ${V('--t-border')}`,
                                            borderRadius: 8,
                                            color: V('--t-text-soft'),
                                            fontSize: 11,
                                            fontFamily: "'Outfit', sans-serif",
                                            cursor: 'pointer',
                                        }}
                                    >
                                        ↺ Restaurar colores del tema
                                    </button>
                                )}
                            </>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

function ThemeOption({ id, theme, active, onSelect }) {
    const previewColors = [
        theme.vars['--t-blue'],
        theme.vars['--t-green'],
        theme.vars['--t-amber'],
        theme.vars['--t-red'],
        theme.vars['--t-purple'],
    ];

    return (
        <button
            onClick={() => onSelect(id)}
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                padding: '8px 10px',
                background: active ? V('--t-blue-glow') : 'transparent',
                border: active ? `1px solid ${V('--t-blue')}40` : '1px solid transparent',
                borderRadius: 8,
                cursor: 'pointer',
                transition: 'all 0.15s',
                width: '100%',
            }}
            onMouseEnter={e => { if (!active) e.currentTarget.style.background = V('--t-card-hover'); }}
            onMouseLeave={e => { if (!active) e.currentTarget.style.background = 'transparent'; }}
        >
            {/* Color preview dots */}
            <div style={{ display: 'flex', gap: 3 }}>
                {previewColors.map((c, i) => (
                    <span key={i} style={{
                        width: 10, height: 10, borderRadius: '50%',
                        background: c, border: '1px solid rgba(255,255,255,0.1)',
                    }} />
                ))}
            </div>
            <span style={{
                fontSize: 12,
                fontFamily: "'Outfit', sans-serif",
                fontWeight: active ? 600 : 400,
                color: active ? V('--t-blue') : V('--t-text-soft'),
                flex: 1,
                textAlign: 'left',
            }}>
                {theme.label}
            </span>
            {active && <span style={{ fontSize: 10, color: V('--t-blue') }}>✓</span>}
        </button>
    );
}

function ColorPicker({ label, varName, defaultValue, override, onChange }) {
    const value = override || defaultValue;

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <input
                type="color"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                style={{
                    width: 28, height: 28, padding: 0,
                    border: `1px solid ${V('--t-border')}`,
                    borderRadius: 6, cursor: 'pointer',
                    background: 'transparent',
                }}
            />
            <div style={{ flex: 1 }}>
                <div style={{ fontSize: 11, color: V('--t-text'), fontFamily: "'Outfit', sans-serif", fontWeight: 500 }}>
                    {label}
                </div>
                <div style={{ fontSize: 9, color: V('--t-text-muted'), fontFamily: "'JetBrains Mono', monospace" }}>
                    {value}
                </div>
            </div>
            {override && (
                <button
                    onClick={() => onChange(undefined)}
                    style={{
                        background: 'transparent', border: 'none',
                        color: V('--t-text-muted'), cursor: 'pointer', fontSize: 12,
                        padding: 4,
                    }}
                    title="Restaurar"
                >↺</button>
            )}
        </div>
    );
}
