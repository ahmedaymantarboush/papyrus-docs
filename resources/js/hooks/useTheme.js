import { useState, useEffect } from 'react';

/**
 * useTheme — Manages dark/light theme with localStorage persistence.
 *
 * - Reads initial preference from localStorage ('papyrus_theme')
 * - Falls back to OS preference via prefers-color-scheme
 * - Adds/removes 'dark' class on <html> element
 * - Persists choice to localStorage on change
 */
export default function useTheme() {
    const [theme, setTheme] = useState(() => {
        if (typeof window === 'undefined') return 'dark';

        const stored = localStorage.getItem('papyrus_theme');
        if (stored === 'light' || stored === 'dark') return stored;

        // Fall back to OS preference
        if (window.matchMedia?.('(prefers-color-scheme: light)').matches) return 'light';
        return 'dark';
    });

    useEffect(() => {
        const root = document.documentElement;

        if (theme === 'dark') {
            root.classList.add('dark');
            root.classList.remove('light');
        } else {
            root.classList.add('light');
            root.classList.remove('dark');
        }

        localStorage.setItem('papyrus_theme', theme);
    }, [theme]);

    const toggle = () => setTheme(t => t === 'dark' ? 'light' : 'dark');

    return { theme, setTheme, toggle, isDark: theme === 'dark' };
}
