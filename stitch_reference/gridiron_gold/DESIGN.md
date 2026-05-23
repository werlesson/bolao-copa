---
name: Gridiron Gold
colors:
  surface: '#111319'
  surface-dim: '#111319'
  surface-bright: '#373940'
  surface-container-lowest: '#0c0e14'
  surface-container-low: '#191b22'
  surface-container: '#1e1f26'
  surface-container-high: '#282a30'
  surface-container-highest: '#33343b'
  on-surface: '#e2e2eb'
  on-surface-variant: '#d1c5ae'
  inverse-surface: '#e2e2eb'
  inverse-on-surface: '#2e3037'
  outline: '#9a907b'
  outline-variant: '#4e4634'
  surface-tint: '#eec13c'
  primary: '#ffe7af'
  on-primary: '#3d2e00'
  primary-container: '#f5c842'
  on-primary-container: '#6c5400'
  inverse-primary: '#755b00'
  secondary: '#4de082'
  on-secondary: '#003919'
  secondary-container: '#00b55d'
  on-secondary-container: '#003e1c'
  tertiary: '#c8efff'
  on-tertiary: '#003543'
  tertiary-container: '#6adbff'
  on-tertiary-container: '#005f75'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffe08f'
  primary-fixed-dim: '#eec13c'
  on-primary-fixed: '#241a00'
  on-primary-fixed-variant: '#584400'
  secondary-fixed: '#6dfe9c'
  secondary-fixed-dim: '#4de082'
  on-secondary-fixed: '#00210c'
  on-secondary-fixed-variant: '#005227'
  tertiary-fixed: '#b5ebff'
  tertiary-fixed-dim: '#62d4f8'
  on-tertiary-fixed: '#001f28'
  on-tertiary-fixed-variant: '#004e60'
  background: '#111319'
  on-background: '#e2e2eb'
  surface-variant: '#33343b'
typography:
  display-score:
    fontFamily: jetbrainsMono
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg:
    fontFamily: geist
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: geist
    fontSize: 20px
    fontWeight: '700'
    lineHeight: 28px
    letterSpacing: -0.02em
  body-lg:
    fontFamily: geist
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: geist
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-bold:
    fontFamily: geist
    fontSize: 12px
    fontWeight: '700'
    lineHeight: 16px
  score-sm:
    fontFamily: jetbrainsMono
    fontSize: 14px
    fontWeight: '700'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  gutter: 16px
  margin-mobile: 16px
  margin-desktop: 48px
---

## Brand & Style
The design system is engineered for the high-stakes environment of World Cup predictions, blending the intensity of sports betting with a premium, data-dense aesthetic. The brand personality is authoritative, precise, and sophisticated, evoking the atmosphere of a modern stadium at night.

The design style is **Corporate Modern with a "Flat-Tactile" edge**. It avoids decorative flourishes like gradients, blurs, or glows in favor of raw structural clarity. Depth is communicated through a strict hierarchy of monochromatic surfaces and "Scoreboard" inspired typography, ensuring that the user's focus remains entirely on the data and the competition.

## Colors
The palette is rooted in a "Deep Night" foundation to maximize the legibility of scores and action items. 

- **Primary (Golden Yellow):** Reserved strictly for high-priority CTAs, current rankings, and exact-score celebrations. It represents the trophy and the ultimate prize.
- **Success (Pitch Green):** Used for live match indicators and positive point accumulation.
- **Neutral Surfaces:** A three-tier grey scale (`#0f1117`, `#161820`, `#1e2028`) creates structural depth without the use of shadows.
- **Borders:** Every interactive element or container is defined by a `#252836` hairline border to maintain a "scoreboard grid" appearance.

## Typography
This design system utilizes **Geist Sans** for its technical, neutral precision in all UI and reading contexts. For numerical data—specifically match scores and point tallies—**JetBrains Mono** is utilized to provide a fixed-width, scoreboard-style aesthetic that ensures numbers remain perfectly aligned in lists and tables.

Headings utilize a tight letter-spacing to appear more impactful and "headline-ready," while body text maintains standard tracking for readability during long-form analysis.

## Layout & Spacing
The layout follows a strict 4px baseline grid to reinforce the systematic, "engineered" feel of the app. 

- **Grid:** A 12-column fluid grid is used for desktop, collapsing to a single column with 16px side margins on mobile.
- **Density:** Information density is high. Match rows and ranking tables should minimize vertical padding (8px - 12px) to allow as much data as possible to be visible "above the fold."
- **Containers:** Content is grouped into cards that span the full width of the mobile viewport minus the margins.

## Elevation & Depth
This design system rejects the use of ambient shadows. Depth is achieved solely through **Tonal Layering** and **Structural Outlines**.

- **Level 0 (Base):** `#0f1117` - The main canvas of the application.
- **Level 1 (Cards):** `#161820` - Used for content grouping. These must have a 1px solid border of `#252836`.
- **Level 2 (Interaction):** `#1e2028` - Reserved for inputs, modals, and hover states. 

The lack of shadows creates a crisp, "high-definition" look that feels professional and eliminates visual clutter.

## Shapes
The shape language is "Soft-Geometric." While the overall aesthetic is sharp and technical, the use of a 12px radius on cards and 8px on inputs prevents the UI from feeling overly aggressive or dated.

- **Cards/Buttons:** 12px (Standard for primary containers).
- **Inputs:** 8px (To create a slight visual distinction from cards).
- **Badges:** 6px (Tight rounding for small data labels).
- **Avatars:** Always 100% circular to contrast against the dominant rectangular grid.

## Components

### Buttons
- **Primary:** High-contrast Golden Yellow (`#f5c842`) background with `#0f1117` bold text. No shadow.
- **Secondary:** Surface Elevated (`#1e2028`) background with a `#252836` border and Secondary Text (`#9ca3af`).

### Match Cards
Containers use the Card Surface (`#161820`) with a 1px border. Inside, team names use Primary Text, and the score uses the `display-score` typography style.

### Badges & Status
- **Live Badge:** Features a green dot (`#4ade80`) with a 1.5s ease-in-out pulse animation, accompanied by "AO VIVO" text.
- **Points Badge:** Small containers with Success Background (`#0a2a14`) and Success Text (`#4ade80`).
- **Golden Badge:** For Rank #1 or Exact Predictions, use Accent Background (`#3d2f00`) with Golden Yellow text.

### Inputs
Backgrounded in `#1e2028` with a `#252836` border. On focus, the border should change to the Golden Yellow (`#f5c842`).

### Navigation
The bottom bar is locked to the bottom of the viewport with a `#0f1117` background and a top-only border of `#252836`. Active states use the primary color for both the icon and the label.