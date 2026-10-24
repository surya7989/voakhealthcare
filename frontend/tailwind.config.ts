import type { Config } from 'tailwindcss';

const config: Config = {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        voak: {
          canvas: 'var(--voak-canvas)',
          'canvas-alt': 'var(--voak-canvas-alt)',
          white: 'var(--voak-white)',
          pink: 'var(--voak-pink)',
          'pink-light': 'var(--voak-pink-light)',
          yellow: 'var(--voak-yellow)',
          'yellow-light': 'var(--voak-yellow-light)',
          mint: 'var(--voak-mint)',
          'mint-light': 'var(--voak-mint-light)',
          sky: 'var(--voak-sky)',
          'sky-light': 'var(--voak-sky-light)',
          purple: 'var(--voak-purple-cta)',
          'purple-hover': 'var(--voak-purple-hover)',
          'purple-active': 'var(--voak-purple-active)',
          'purple-light': 'var(--voak-purple-light)',
          navy: 'var(--voak-navy)',
          'navy-surface': 'var(--voak-navy-surface)',
          muted: 'var(--voak-muted)',
          border: 'var(--voak-border)',
          'border-subtle': 'var(--voak-border-subtle)',
          'focus-ring': 'var(--voak-focus-ring)',
        },
        admin: {
          sidebar: 'var(--admin-sidebar-bg)',
          'sidebar-hover': 'var(--admin-sidebar-hover)',
          'sidebar-active': 'var(--admin-sidebar-active)',
          'sidebar-text': 'var(--admin-sidebar-text)',
          'sidebar-text-active': 'var(--admin-sidebar-text-active)',
          content: 'var(--admin-content-bg)',
          card: 'var(--admin-card-bg)',
          'table-head': 'var(--admin-table-head)',
          border: 'var(--admin-border)',
        },
        state: {
          success: 'var(--state-success)',
          'success-bg': 'var(--state-success-bg)',
          warning: 'var(--state-warning)',
          'warning-bg': 'var(--state-warning-bg)',
          error: 'var(--state-error)',
          'error-bg': 'var(--state-error-bg)',
          info: 'var(--state-info)',
          'info-bg': 'var(--state-info-bg)',
        },
      },
      fontFamily: {
        serif: ['var(--font-serif)', 'Georgia', 'serif'],
        sans: ['var(--font-sans)', 'Inter', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        '2xl': '36px',
        xl: '28px',
        lg: '20px',
        md: '14px',
        sm: '8px',
      },
      boxShadow: {
        sm: 'var(--shadow-sm)',
        md: 'var(--shadow-md)',
        lg: 'var(--shadow-lg)',
        xl: 'var(--shadow-xl)',
        floating: 'var(--shadow-floating)',
      },
    },
  },
  plugins: [],
};

export default config;
