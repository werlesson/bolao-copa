# BolãoCopa - Nuxt 4 Frontend

## Stack
Nuxt 4, TypeScript, TailwindCSS, Pinia, shadcn-vue, Zod, VueUse, PWA.
Pure SPA (ssr: false). No server-side rendering.

## Full context
Read ../prompt-final.md before writing any code.
It contains: all UX rules, behavior of each screen,
complete design system with tokens, and implementation order.

## Screen mockups
Visual references live in stitch/ (one folder per screen, screen.png inside each).
Ignore stitch_reference/ — use only stitch/ for implementation guidance.
Screens: login, onboarding (4 slides), jogos list, palpite, classificação, meus grupos, criar grupo, perfil.

## Nuxt 4 structure
All application code lives in app/ (pages, components, composables, etc.)
nuxt.config.ts stays at the root, outside app/.

## Required conventions
- Vue 3 Composition API with <script setup lang="ts"> in all components
- Auto-imports enabled � do not manually import ref, computed, or composables
- Sanctum token in httpOnly cookie via useCookie � NEVER in localStorage
- useFetch in Nuxt 4 has shallow reactivity by default � use { deep: true } when needed
- Dates always in dd/mm/yyyy format
- navigateTo() instead of useRouter().push()
- Icons: Material Symbols Outlined exclusively
- Fonts: Geist (body) + JetBrains Mono (scores/numbers)
- iOS safe area: pb-[env(safe-area-inset-bottom)] on BottomNav

## Ranking tie-break rule
Sort order: total_points DESC → exact_scores DESC → name ASC (alphabetical, case-insensitive).
Tied players (same points AND same exact_scores) share the same position number — 1224 pattern.
Within a tied group players appear alphabetically.
UI must use array index (not position value) for podium slots and preview slice:
- Podium: entries[0..2] by index (first entry always fills the gold slot)
- Preview: entries[3..9] by index
- userInTopN: check presence in slice(0, TOP_N), not position <= TOP_N

# Design System Prompt

Use este prompt completo ao iniciar qualquer sessão no Claude Code para aplicar o design system correto.

---

## IDENTIDADE VISUAL

Implemente o design system **"Pitch Side Premium"** — um tema dark premium inspirado em ambientes de estádio à noite e interfaces de transmissão esportiva profissional. A estética é **"Elite Athleticism"**: fundo deep-space com pontos focais neon, tipografia editorial esportiva e visualizações de dados nítidas e legíveis.

---

## PALETA DE CORES — TOKENS COMPLETOS

Configure o Tailwind com os seguintes color tokens via `tailwind.config`:

```js
colors: {
  // Backgrounds & Surfaces (camadas tonais dark)
  'background':                '#121414', // fundo base da aplicação
  'surface':                   '#121414',
  'surface-dim':               '#121414',
  'surface-bright':            '#383939',
  'surface-container-lowest':  '#0d0f0f',
  'surface-container-low':     '#1a1c1c',
  'surface-container':         '#1e2020', // cards padrão
  'surface-container-high':    '#282a2a',
  'surface-container-highest': '#333535',
  'surface-variant':           '#333535',
  'surface-tint':              '#65df76',

  // Text (on surfaces)
  'on-surface':         '#e2e2e2', // texto primário
  'on-surface-variant': '#bdcab9', // texto secundário/metadata
  'on-background':      '#e2e2e2',
  'inverse-surface':    '#e2e2e2',
  'inverse-on-surface': '#2f3131',

  // Borders
  'outline':         '#879484',
  'outline-variant': '#3e4a3d',

  // Primary — Brazil Green (ações principais, sucesso, elementos do campo)
  'primary':                   '#65df76',
  'on-primary':                '#003911',
  'primary-container':         '#23a646',
  'on-primary-container':      '#00320d',
  'inverse-primary':           '#006e27',
  'primary-fixed':             '#81fc90',
  'primary-fixed-dim':         '#65df76',
  'on-primary-fixed':          '#002107',
  'on-primary-fixed-variant':  '#00531c',

  // Secondary — Brazil Yellow (destaques, estatísticas críticas, tier "Gold")
  'secondary':                  '#fff9ed',
  'on-secondary':               '#393000',
  'secondary-container':        '#fddc00', // ⭐ cor de destaque máximo
  'on-secondary-container':     '#706000',
  'secondary-fixed':            '#ffe24b',
  'secondary-fixed-dim':        '#e3c600',
  'on-secondary-fixed':         '#211b00',
  'on-secondary-fixed-variant': '#524600',

  // Tertiary — Brazil Blue (elementos estruturais secundários, gradientes decorativos)
  'tertiary':                   '#b5c4ff',
  'on-tertiary':                '#042978',
  'tertiary-container':         '#748de0',
  'on-tertiary-container':      '#00236c',
  'tertiary-fixed':             '#dbe1ff',
  'tertiary-fixed-dim':         '#b5c4ff',
  'on-tertiary-fixed':          '#00174d',
  'on-tertiary-fixed-variant':  '#25428f',

  // Error
  'error':              '#ffb4ab',
  'on-error':           '#690005',
  'error-container':    '#93000a',
  'on-error-container': '#ffdad6',
}
```

### Hierarquia de uso das cores

| Cor | Token | Quando usar |
|---|---|---|
| Verde Neon | `primary` `#65df76` | CTAs, estados de sucesso, borda de item em destaque, ícones ativos |
| Amarelo Ouro | `secondary-container` `#fddc00` | Estatísticas críticas, badge premium, pontos, elementos "Gold" |
| Azul Estrutural | `tertiary` `#b5c4ff` | Gradientes decorativos, ações secundárias |
| Branco Quente | `on-surface` `#e2e2e2` | Texto principal |
| Cinza Muted | `on-surface-variant` `#bdcab9` | Metadata, labels secundários |

---

## TIPOGRAFIA

Instale as fontes via Google Fonts:
```html
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Archivo+Narrow:wght@400;600;700&display=swap" rel="stylesheet"/>
```

Configure os fontSize tokens no Tailwind:

```js
fontFamily: {
  'display-lg':          ['Anton', 'sans-serif'],
  'headline-lg':         ['Anton', 'sans-serif'],
  'headline-lg-mobile':  ['Anton', 'sans-serif'],
  'title-md':            ['Archivo Narrow', 'sans-serif'],
  'body-lg':             ['Archivo Narrow', 'sans-serif'],
  'body-sm':             ['Archivo Narrow', 'sans-serif'],
  'label-caps':          ['Archivo Narrow', 'sans-serif'],
},
fontSize: {
  'display-lg':         ['48px', { lineHeight: '1.1', letterSpacing: '0.02em', fontWeight: '400' }],
  'headline-lg':        ['32px', { lineHeight: '1.2', fontWeight: '400' }],
  'headline-lg-mobile': ['24px', { lineHeight: '1.2', fontWeight: '400' }],
  'title-md':           ['20px', { lineHeight: '1.4', fontWeight: '600' }],
  'body-lg':            ['16px', { lineHeight: '1.6', fontWeight: '400' }],
  'body-sm':            ['14px', { lineHeight: '1.5', fontWeight: '400' }],
  'label-caps':         ['12px', { lineHeight: '1',   letterSpacing: '0.1em', fontWeight: '700' }],
},
```

### Regras de uso tipográfico

- **Anton** → headers de seção, displays de score, títulos de tela. Sempre em UPPERCASE, tracking tight.
- **Archivo Narrow** → corpo de texto, tabelas de dados, labels, chips. Alta densidade sem perder legibilidade.
- **label-caps** → sempre `uppercase tracking-widest` para criar ritmo visual "scoreboard".
- Em `display-lg`, aplicar gradiente sutil de branco → prata para efeito de troféu.

---

## ESPAÇAMENTO & GRID

```js
spacing: {
  'base-unit':      '4px',   // unidade base (use múltiplos: 8, 16, 32...)
  'gutter':         '16px',  // espaço entre colunas
  'margin-mobile':  '20px',  // margem lateral no mobile
  'margin-desktop': '40px',  // margem lateral no desktop
  'stack-sm':       '8px',   // espaço entre elementos relacionados
  'stack-md':       '16px',  // espaço padrão entre componentes
  'stack-lg':       '32px',  // espaço entre seções (deixa os glows respirarem)
},
```

### Grid

- **Mobile:** 4 colunas, `margin-mobile` (20px) nas laterais, gutter 16px
- **Tablet/Desktop:** 12 colunas, `margin-desktop` (40px), gutter 16px (compacto, estilo "estádio lotado")
- Preferir scroll **vertical** para feeds ao vivo
- Carrosséis **horizontais** com overflow para cards de jogadores/partidas

---

## BORDER RADIUS

```js
borderRadius: {
  'sm':      '0.25rem',  // 4px — chips pequenos, badges
  'DEFAULT': '0.5rem',   // 8px — botões, inputs
  'md':      '0.75rem',  // 12px
  'lg':      '1rem',     // 16px
  'xl':      '1.5rem',   // 24px — cards grandes, modais
  'full':    '9999px',   // avatares, tags "Live"
},
```

---

## ELEVAÇÃO & EFEITOS VISUAIS

**Não usar box-shadow preto** no fundo charcoal. Elevação é feita por:

### 1. Tonal Lift (camadas de cor)
Elementos de maior elevação usam `surface-container-high` ou `surface-container-highest`.

### 2. Glass Card (glassmorphism)
```css
.glass-card {
  background: rgba(30, 32, 32, 0.7);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.05);
}
```
Usar em: cards de partida, bottom nav, overlays, modais.

### 3. Neon Glow Green (elemento ativo/destaque)
```css
.neon-glow-green {
  box-shadow: 0 0 15px rgba(101, 223, 118, 0.2);
}
```
Usar em: card do líder do ranking, match "Live", item selecionado.

### 4. Neon Glow Blue (destaque estrutural)
```css
.neon-glow-blue {
  box-shadow: 0 0 15px rgba(180, 196, 255, 0.2);
}
```

### 5. Diagonal Gradient (header de grupo/destaque)
```css
.diagonal-grad {
  background: linear-gradient(135deg, #00531c 0%, #121414 100%);
}
```
Usar em: banners de grupo, seções hero de partida.

### 6. Header fixo com Glass Effect
```css
header {
  background: rgba(18, 20, 20, 0.8);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 4px 6px rgba(101, 223, 118, 0.05);
}
```

---

## COMPONENTES — PADRÕES DE IMPLEMENTAÇÃO

### Botão Primário
```html
<button class="bg-primary text-on-primary px-4 py-2 rounded-lg font-label-caps text-label-caps flex items-center gap-2 active:scale-95 duration-150 hover:shadow-[0_0_12px_rgba(101,223,118,0.4)]">
  AÇÃO
</button>
```
- Hover/active: `brazil-yellow` outer glow
- Fundo: `primary` (#65df76), texto: `on-primary` (#003911)

### Botão Secundário
```html
<button class="border border-tertiary text-on-surface px-4 py-2 rounded-lg font-label-caps text-label-caps">
  AÇÃO
</button>
```

### Botão Terciário/Ghost
```html
<button class="text-primary font-label-caps text-label-caps uppercase tracking-widest">
  VER MAIS
</button>
```

### Card de Ranking (líder)
```html
<div class="glass-card p-4 rounded-xl flex items-center gap-4 border-l-4 border-l-primary neon-glow-green">
  <!-- rank number em text-primary -->
  <!-- avatar com border border-primary/40 -->
  <!-- nome em font-title-md, metadata em text-on-surface-variant -->
  <!-- pontos em font-display-lg text-primary -->
</div>
```

### Card de Ranking (demais posições)
```html
<div class="glass-card p-4 rounded-xl flex items-center gap-4">
  <!-- rank number em text-on-surface-variant -->
  <!-- avatar com border border-white/10 -->
  <!-- pontos em text-on-surface -->
</div>
```

### Tag "Live"
```html
<span class="bg-primary text-on-primary text-[10px] font-label-caps px-2 py-0.5 rounded-full uppercase tracking-widest animate-pulse">
  LIVE
</span>
```

### Chip de Estatística
```html
<span class="bg-surface-container-high text-on-surface text-body-sm font-body-sm px-2 py-1 rounded-full flex items-center gap-1">
  <span class="text-secondary-container">⚽</span> 3 gols
</span>
```

### Input Field
```html
<input class="bg-surface-container-low border-b-2 border-outline focus:border-primary focus:shadow-[0_1px_8px_rgba(101,223,118,0.15)] text-on-surface px-3 py-2 w-full outline-none transition-all font-body-lg text-body-lg rounded-t-sm" />
```

### Seção Header com ícone
```html
<h3 class="font-headline-lg-mobile text-headline-lg-mobile flex items-center gap-2">
  <span class="material-symbols-outlined text-secondary-container">leaderboard</span>
  LEADERBOARD
</h3>
```

### Badge de Convite/Código
```html
<span class="font-title-md text-title-md text-secondary-container tracking-widest">
  HQ-B0L40-X9
</span>
```

---

## ESTRUTURA DE PÁGINA PADRÃO

```html
<!-- BG base -->
<body class="bg-background text-on-background font-body-lg min-h-screen pb-32">

  <!-- Header fixo com glass -->
  <header class="fixed top-0 w-full z-50 bg-surface/80 backdrop-blur-xl border-b border-white/10 shadow-md shadow-primary/5 flex justify-between items-center h-16 px-margin-mobile">
    <h1 class="font-headline-lg-mobile text-headline-lg-mobile italic text-primary tracking-tighter">APP TITLE</h1>
  </header>

  <!-- Conteúdo principal -->
  <main class="pt-24 px-margin-mobile max-w-xl mx-auto space-y-stack-lg">
    
    <!-- Seção banner hero com diagonal gradient -->
    <section class="diagonal-grad p-stack-md rounded-xl border border-white/10 space-y-stack-sm">
      ...
    </section>

    <!-- Seções de conteúdo -->
    <section class="space-y-stack-md">
      ...
    </section>

  </main>

</body>
```

---

## ÍCONES

Usar **Material Symbols Outlined** do Google Fonts:
```html
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
```

Uso: `<span class="material-symbols-outlined text-primary">notifications</span>`

Tamanhos comuns: `text-[16px]`, `text-[18px]`, padrão `text-[24px]`.

---

## CSS CUSTOMIZADO OBRIGATÓRIO (adicionar ao global)

```css
.glass-card {
  background: rgba(30, 32, 32, 0.7);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.neon-glow-green {
  box-shadow: 0 0 15px rgba(101, 223, 118, 0.2);
}

.neon-glow-blue {
  box-shadow: 0 0 15px rgba(180, 196, 255, 0.2);
}

.diagonal-grad {
  background: linear-gradient(135deg, #00531c 0%, #121414 100%);
}
```

---

## RESUMO DO TOM VISUAL

> Interface dark premium de futebol. Fundo charcoal profundo (#121414). Tipografia Anton em maiúsculas para headers, Archivo Narrow denso para dados. Acentos em verde neon (primário), amarelo ouro (destaques) e azul estrutural. Elevação por glassmorphism + outer glow verde/azul — sem sombras pretas. Bordas sutis em `rgba(255,255,255,0.05~0.10)`. Atmosfera de "estádio à noite com telão de LED".
