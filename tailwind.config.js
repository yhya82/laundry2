import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                mono: ['Geist Mono', 'SFMono-Regular', 'Consolas', 'Liberation Mono', 'Menlo', 'monospace'],
            },
            colors: {
                bg: 'var(--color-bg)',
                surface: 'var(--color-surface)',
                'surface-2': 'var(--color-surface-2)',
                ink: 'var(--color-ink)',
                'ink-muted': 'var(--color-ink-muted)',
                'ink-faint': 'var(--color-ink-faint)',
                line: 'var(--color-line)',
                'line-strong': 'var(--color-line-strong)',
                accent: 'var(--color-accent)',
                'accent-ink': 'var(--color-accent-ink)',
                'accent-soft': 'var(--color-accent-soft)',
                success: 'var(--color-success)',
                'success-soft': 'var(--color-success-soft)',
                critical: 'var(--color-critical)',
                'critical-soft': 'var(--color-critical-soft)',
                'pill-bg': 'var(--color-neutral-pill-bg)',
                'pill-ink': 'var(--color-neutral-pill-ink)',
            },
        },
    },

    plugins: [forms],
};
