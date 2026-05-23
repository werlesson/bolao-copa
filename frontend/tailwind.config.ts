import type { Config } from 'tailwindcss'

export default {
  content: [
    './app/**/*.{vue,js,ts,mjs}',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Backgrounds & Surfaces
        background:                  '#121414',
        surface:                     '#121414',
        'surface-dim':               '#121414',
        'surface-bright':            '#383939',
        'surface-container-lowest':  '#0d0f0f',
        'surface-container-low':     '#1a1c1c',
        'surface-container':         '#1e2020',
        'surface-container-high':    '#282a2a',
        'surface-container-highest': '#333535',
        'surface-variant':           '#333535',
        'surface-tint':              '#65df76',
        // Legacy tokens usados no código existente
        'surface-card':     '#1a1c1c',
        'surface-elevated': '#1e2020',
        'surface-outline':  '#3e4a3d',

        // Text
        'on-surface':         '#e2e2e2',
        'on-surface-variant': '#bdcab9',
        'on-background':      '#e2e2e2',
        'inverse-surface':    '#e2e2e2',
        'inverse-on-surface': '#2f3131',

        // Borders
        outline:           '#879484',
        'outline-variant': '#3e4a3d',

        // Primary — Verde Neon (CTAs, sucesso, ativo)
        primary:                    '#65df76',
        'on-primary':               '#003911',
        'primary-container':        '#23a646',
        'on-primary-container':     '#00320d',
        'inverse-primary':          '#006e27',
        'primary-fixed':            '#81fc90',
        'primary-fixed-dim':        '#65df76',
        'on-primary-fixed':         '#002107',
        'on-primary-fixed-variant': '#00531c',

        // Secondary — Amarelo Ouro (destaques, pontos, tier Gold)
        secondary:                    '#fff9ed',
        'on-secondary':               '#393000',
        'secondary-container':        '#fddc00',
        'on-secondary-container':     '#706000',
        'secondary-fixed':            '#ffe24b',
        'secondary-fixed-dim':        '#e3c600',
        'on-secondary-fixed':         '#211b00',
        'on-secondary-fixed-variant': '#524600',

        // Tertiary — Azul Estrutural (gradientes, ações secundárias)
        tertiary:                    '#b5c4ff',
        'on-tertiary':               '#042978',
        'tertiary-container':        '#748de0',
        'on-tertiary-container':     '#00236c',
        'tertiary-fixed':            '#dbe1ff',
        'tertiary-fixed-dim':        '#b5c4ff',
        'on-tertiary-fixed':         '#00174d',
        'on-tertiary-fixed-variant': '#25428f',

        // Error
        error:                '#ffb4ab',
        'on-error':           '#690005',
        'error-container':    '#93000a',
        'on-error-container': '#ffdad6',

        // App-specific
        success:      '#65df76',
        'success-bg': '#00210c',
        live:         '#65df76',
      },

      borderRadius: {
        sm:      '0.25rem',  // 4px — chips, badges
        DEFAULT: '0.5rem',   // 8px — botões, inputs
        md:      '0.75rem',  // 12px
        lg:      '1rem',     // 16px — cards
        xl:      '1.5rem',   // 24px — cards grandes, modais
        full:    '9999px',   // avatares, tags Live
      },

      spacing: {
        'base-unit':      '4px',
        gutter:           '16px',
        margin:           '20px',
        'margin-mobile':  '20px',
        'margin-desktop': '40px',
        'stack-sm':       '8px',
        'stack-md':       '16px',
        'stack-lg':       '32px',
        'card-padding':   '16px',
      },

      fontFamily: {
        sans:    ['Archivo Narrow', 'sans-serif'],
        mono:    ['JetBrains Mono', 'monospace'],
        display: ['Anton', 'sans-serif'],
        // Tokens semânticos — geram classes font-{name} usadas pelo stitch
        'display-lg':         ['Anton', 'sans-serif'],
        'headline-lg':        ['Anton', 'sans-serif'],
        'headline-lg-mobile': ['Anton', 'sans-serif'],
        'title-md':           ['Archivo Narrow', 'sans-serif'],
        'body-lg':            ['Archivo Narrow', 'sans-serif'],
        'body-sm':            ['Archivo Narrow', 'sans-serif'],
        'label-caps':         ['Archivo Narrow', 'sans-serif'],
      },

      fontSize: {
        // Pitch Side Premium
        'display-lg':         ['48px', { lineHeight: '1.1', letterSpacing: '0.02em', fontWeight: '400' }],
        'headline-lg':        ['32px', { lineHeight: '1.2', fontWeight: '400' }],
        'headline-lg-mobile': ['24px', { lineHeight: '1.2', fontWeight: '400' }],
        'title-md':           ['20px', { lineHeight: '1.4', fontWeight: '600' }],
        'body-lg':            ['16px', { lineHeight: '1.6', fontWeight: '400' }],
        'body-sm':            ['14px', { lineHeight: '1.5', fontWeight: '400' }],
        'label-caps':         ['12px', { lineHeight: '1', letterSpacing: '0.1em', fontWeight: '700' }],
        // Tokens legados (compatibilidade com componentes existentes)
        'display-score': ['32px', { lineHeight: '40px', letterSpacing: '-0.04em', fontWeight: '700' }],
        'headline-md':   ['20px', { lineHeight: '28px', letterSpacing: '-0.02em', fontWeight: '700' }],
        'body-md':       ['14px', { lineHeight: '20px', fontWeight: '400' }],
        'label-sm':      ['12px', { lineHeight: '16px', fontWeight: '600' }],
      },
    },
  },
} satisfies Config
