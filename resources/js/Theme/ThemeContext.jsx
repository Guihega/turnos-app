// resources/js/Theme/ThemeContext.jsx
// ══ Olinora Theme System ══
// Provides theme switching (dark/light/presets) + tenant customization

import { createContext, useContext, useState, useEffect, useCallback } from 'react';

// ── Theme Presets ──

const themes = {
    // ── Dark Themes ──
    'refined-dark': {
        label: 'Industrial Oscuro',
        group: 'dark',
        icon: '◆',
        vars: {
            '--t-bg': '#06080D',
            '--t-surface': '#0C0F16',
            '--t-card': '#111520',
            '--t-card-hover': '#161B28',
            '--t-border': '#1A2035',
            '--t-border-light': '#243050',
            '--t-glass': 'rgba(17,21,32,0.72)',
            '--t-glass-border': 'rgba(255,255,255,0.06)',
            '--t-text': '#E4E8F1',
            '--t-text-soft': '#8B95AD',
            '--t-text-muted': '#6B7693',
            '--t-blue': '#3D7AFF',
            '--t-blue-glow': 'rgba(61,122,255,0.15)',
            '--t-green': '#00D68F',
            '--t-green-glow': 'rgba(0,214,143,0.15)',
            '--t-amber': '#FFB020',
            '--t-amber-glow': 'rgba(255,176,32,0.15)',
            '--t-red': '#FF4757',
            '--t-red-glow': 'rgba(255,71,87,0.15)',
            '--t-purple': '#9D5CFF',
            '--t-purple-glow': 'rgba(157,92,255,0.15)',
            '--t-cyan': '#00D4FF',
            '--t-cyan-glow': 'rgba(0,212,255,0.15)',
            '--t-shadow': '0 8px 32px rgba(0,0,0,0.3)',
            '--t-shadow-lg': '0 16px 64px rgba(0,0,0,0.4)',
        },
    },

    'midnight': {
        label: 'Medianoche',
        group: 'dark',
        icon: '●',
        vars: {
            '--t-bg': '#0A0A12',
            '--t-surface': '#10101C',
            '--t-card': '#161626',
            '--t-card-hover': '#1C1C30',
            '--t-border': '#22223A',
            '--t-border-light': '#2E2E50',
            '--t-glass': 'rgba(16,16,28,0.75)',
            '--t-glass-border': 'rgba(255,255,255,0.05)',
            '--t-text': '#E8E8F0',
            '--t-text-soft': '#9090B0',
            '--t-text-muted': '#7676A0',
            '--t-blue': '#6C8FFF',
            '--t-blue-glow': 'rgba(108,143,255,0.15)',
            '--t-green': '#4ADE80',
            '--t-green-glow': 'rgba(74,222,128,0.15)',
            '--t-amber': '#FBBF24',
            '--t-amber-glow': 'rgba(251,191,36,0.15)',
            '--t-red': '#F87171',
            '--t-red-glow': 'rgba(248,113,113,0.15)',
            '--t-purple': '#A78BFA',
            '--t-purple-glow': 'rgba(167,139,250,0.15)',
            '--t-cyan': '#22D3EE',
            '--t-cyan-glow': 'rgba(34,211,238,0.15)',
            '--t-shadow': '0 8px 32px rgba(0,0,0,0.4)',
            '--t-shadow-lg': '0 16px 64px rgba(0,0,0,0.5)',
        },
    },

    'forest': {
        label: 'Bosque',
        group: 'dark',
        icon: '◈',
        vars: {
            '--t-bg': '#060D08',
            '--t-surface': '#0B150E',
            '--t-card': '#101E14',
            '--t-card-hover': '#15281A',
            '--t-border': '#1A3520',
            '--t-border-light': '#245030',
            '--t-glass': 'rgba(16,30,20,0.72)',
            '--t-glass-border': 'rgba(255,255,255,0.06)',
            '--t-text': '#E0EDE4',
            '--t-text-soft': '#8BAD95',
            '--t-text-muted': '#6B9A7A',
            '--t-blue': '#3DD68F',
            '--t-blue-glow': 'rgba(61,214,143,0.15)',
            '--t-green': '#00D68F',
            '--t-green-glow': 'rgba(0,214,143,0.15)',
            '--t-amber': '#D4A030',
            '--t-amber-glow': 'rgba(212,160,48,0.15)',
            '--t-red': '#E85D5D',
            '--t-red-glow': 'rgba(232,93,93,0.15)',
            '--t-purple': '#7CB8A0',
            '--t-purple-glow': 'rgba(124,184,160,0.15)',
            '--t-cyan': '#40C9A2',
            '--t-cyan-glow': 'rgba(64,201,162,0.15)',
            '--t-shadow': '0 8px 32px rgba(0,0,0,0.3)',
            '--t-shadow-lg': '0 16px 64px rgba(0,0,0,0.4)',
        },
    },

    // ── Light Themes ──
    'clean-light': {
        label: 'Limpio',
        group: 'light',
        icon: '○',
        vars: {
            '--t-bg': '#F5F6FA',
            '--t-surface': '#FFFFFF',
            '--t-card': '#FFFFFF',
            '--t-card-hover': '#F0F2F8',
            '--t-border': '#E2E6EF',
            '--t-border-light': '#D0D5E2',
            '--t-glass': 'rgba(255,255,255,0.80)',
            '--t-glass-border': 'rgba(0,0,0,0.06)',
            '--t-text': '#1A1D2B',
            '--t-text-soft': '#5A6178',
            '--t-text-muted': '#6B7285',
            '--t-blue': '#2563EB',
            '--t-blue-glow': 'rgba(37,99,235,0.10)',
            '--t-green': '#059669',
            '--t-green-glow': 'rgba(5,150,105,0.10)',
            '--t-amber': '#D97706',
            '--t-amber-glow': 'rgba(217,119,6,0.10)',
            '--t-red': '#DC2626',
            '--t-red-glow': 'rgba(220,38,38,0.10)',
            '--t-purple': '#7C3AED',
            '--t-purple-glow': 'rgba(124,58,237,0.10)',
            '--t-cyan': '#0891B2',
            '--t-cyan-glow': 'rgba(8,145,178,0.10)',
            '--t-shadow': '0 4px 16px rgba(0,0,0,0.06)',
            '--t-shadow-lg': '0 8px 32px rgba(0,0,0,0.08)',
        },
    },

    'warm-light': {
        label: 'Cálido',
        group: 'light',
        icon: '◐',
        vars: {
            '--t-bg': '#FAF8F5',
            '--t-surface': '#FFFFFF',
            '--t-card': '#FFFEFA',
            '--t-card-hover': '#F5F0E8',
            '--t-border': '#E8E0D4',
            '--t-border-light': '#D8CFC0',
            '--t-glass': 'rgba(255,254,250,0.80)',
            '--t-glass-border': 'rgba(0,0,0,0.05)',
            '--t-text': '#2D2418',
            '--t-text-soft': '#6B5D4F',
            '--t-text-muted': '#7D6B5B',
            '--t-blue': '#B45309',
            '--t-blue-glow': 'rgba(180,83,9,0.10)',
            '--t-green': '#15803D',
            '--t-green-glow': 'rgba(21,128,61,0.10)',
            '--t-amber': '#CA8A04',
            '--t-amber-glow': 'rgba(202,138,4,0.10)',
            '--t-red': '#B91C1C',
            '--t-red-glow': 'rgba(185,28,28,0.10)',
            '--t-purple': '#6D28D9',
            '--t-purple-glow': 'rgba(109,40,217,0.10)',
            '--t-cyan': '#0E7490',
            '--t-cyan-glow': 'rgba(14,116,144,0.10)',
            '--t-shadow': '0 4px 16px rgba(45,36,24,0.06)',
            '--t-shadow-lg': '0 8px 32px rgba(45,36,24,0.08)',
        },
    },

    'clinical': {
        label: 'Clínico',
        group: 'light',
        icon: '◇',
        vars: {
            '--t-bg': '#F0F4F8',
            '--t-surface': '#FFFFFF',
            '--t-card': '#FFFFFF',
            '--t-card-hover': '#EBF0F7',
            '--t-border': '#D4DEE8',
            '--t-border-light': '#BCC9D8',
            '--t-glass': 'rgba(255,255,255,0.85)',
            '--t-glass-border': 'rgba(0,40,100,0.06)',
            '--t-text': '#0F172A',
            '--t-text-soft': '#475569',
            '--t-text-muted': '#64748B',
            '--t-blue': '#0369A1',
            '--t-blue-glow': 'rgba(3,105,161,0.10)',
            '--t-green': '#047857',
            '--t-green-glow': 'rgba(4,120,87,0.10)',
            '--t-amber': '#B45309',
            '--t-amber-glow': 'rgba(180,83,9,0.10)',
            '--t-red': '#BE123C',
            '--t-red-glow': 'rgba(190,18,60,0.10)',
            '--t-purple': '#6D28D9',
            '--t-purple-glow': 'rgba(109,40,217,0.10)',
            '--t-cyan': '#0891B2',
            '--t-cyan-glow': 'rgba(8,145,178,0.10)',
            '--t-shadow': '0 4px 16px rgba(15,23,42,0.06)',
            '--t-shadow-lg': '0 8px 32px rgba(15,23,42,0.10)',
        },
    },
};

// ── Context ──

const ThemeContext = createContext(null);

export function ThemeProvider({ children, defaultTheme = 'refined-dark' }) {
    const [themeId, setThemeId] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('olinora-theme') || defaultTheme;
        }
        return defaultTheme;
    });

    const [customOverrides, setCustomOverrides] = useState(() => {
        if (typeof window !== 'undefined') {
            try {
                return JSON.parse(localStorage.getItem('olinora-theme-overrides') || '{}');
            } catch { return {}; }
        }
        return {};
    });

    // Apply theme vars to :root
    const applyTheme = useCallback((id, overrides = {}) => {
        const preset = themes[id];
        if (!preset) return;

        const root = document.documentElement;
        const merged = { ...preset.vars, ...overrides };

        Object.entries(merged).forEach(([prop, val]) => {
            root.style.setProperty(prop, val);
        });

        // Set data-theme for CSS selectors
        root.setAttribute('data-theme', id);
        root.setAttribute('data-theme-group', preset.group);
    }, []);

    useEffect(() => {
        applyTheme(themeId, customOverrides);
    }, [themeId, customOverrides, applyTheme]);

    const setTheme = useCallback((id) => {
        setThemeId(id);
        localStorage.setItem('olinora-theme', id);
        // Clear overrides when switching presets
        setCustomOverrides({});
        localStorage.removeItem('olinora-theme-overrides');
    }, []);

    const setOverride = useCallback((varName, value) => {
        setCustomOverrides(prev => {
            const next = { ...prev, [varName]: value };
            localStorage.setItem('olinora-theme-overrides', JSON.stringify(next));
            return next;
        });
    }, []);

    const resetOverrides = useCallback(() => {
        setCustomOverrides({});
        localStorage.removeItem('olinora-theme-overrides');
    }, []);

    const currentTheme = themes[themeId] || themes['refined-dark'];
    const isDark = currentTheme.group === 'dark';

    return (
        <ThemeContext.Provider value={{
            themeId,
            setTheme,
            currentTheme,
            isDark,
            themes,
            customOverrides,
            setOverride,
            resetOverrides,
        }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const ctx = useContext(ThemeContext);
    if (!ctx) throw new Error('useTheme must be used within ThemeProvider');
    return ctx;
}

export { themes };
export default ThemeProvider;
