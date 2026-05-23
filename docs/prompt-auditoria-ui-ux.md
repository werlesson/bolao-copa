# Prompt para auditoria UI/UX — bolão-copa

Use este arquivo em uma nova conversa com o Claude (ou outro agente), com o repositório **bolão-copa** aberto para leitura dos arquivos.

## Como usar

1. Abra o projeto no Cursor/Claude Code com o workspace em `bolao-copa`.
2. Copie todo o bloco entre `--- INÍCIO DO PROMPT ---` e `--- FIM DO PROMPT ---`.
3. Cole como primeira mensagem na nova sessão.
4. Opcional: acrescente ao final uma das linhas em [Variações opcionais](#variações-opcionais).

---

## INÍCIO DO PROMPT

```
Você é um designer de produto sênior e engenheiro front-end especializado em apps mobile-first. Sua tarefa é fazer uma AUDITORIA COMPLETA de UI e UX do projeto **bolão-copa** — um app de palpites da Copa do Mundo com grupos privados, ranking e pontuação.

## Objetivo

Analise o código-fonte do frontend (e layouts/composables relacionados à experiência) e produza um relatório de melhorias de **interface visual** e **experiência do usuário** em **todas as telas**, sem implementar nada ainda — apenas diagnóstico, priorização e recomendações acionáveis.

## Stack e convenções (respeite ao avaliar)

- **Framework:** Nuxt (Vue 3), TypeScript, file-based routing em `frontend/app/pages/`
- **Estilo:** Tailwind CSS com design tokens em `frontend/tailwind.config.ts` (tema "Pitch Side Premium")
  - **Primary (verde neon):** CTAs, estados ativos, sucesso, live
  - **Secondary (amarelo ouro):** destaques, pontos, líderes, badges de palpite
  - **Tertiary (azul):** ações secundárias estruturais
  - Superfícies escuras (`background`, `surface-container-*`), tipografia Anton (display) + Archivo Narrow + JetBrains Mono
- **Ícones:** Google Material Symbols Outlined
- **Padrões visuais:** `glass-card`, `header-glass`, `neon-glow-*` em `frontend/app/assets/css/tailwind.css`
- **Navegação principal:** bottom nav em `frontend/app/components/ui/BottomNav.vue` (Jogos, Ranking, Grupos, Perfil)
- **Layouts:** `default.vue` (app autenticado), `minimal.vue` (fluxos focados), algumas páginas com `layout: false`

## Inventário de telas (revise TODAS)

| Rota | Arquivo | Observação |
|------|---------|------------|
| `/` | `pages/index.vue` | Redireciona para `/jogos` |
| `/login` | `pages/login.vue` | Sem layout padrão |
| `/onboarding` | `pages/onboarding.vue` | Primeiro acesso, slides |
| `/jogos` | `pages/jogos/index.vue` | Lista de partidas |
| `/jogos/:id` | `pages/jogos/[id].vue` | Palpite / detalhe da partida |
| `/ranking` | `pages/ranking.vue` | Classificação global |
| `/grupos` | `pages/grupos/index.vue` | Lista de grupos |
| `/grupos/novo` | `pages/grupos/novo.vue` | Criar grupo (layout minimal) |
| `/grupos/:id` | `pages/grupos/[id].vue` | Detalhe do grupo, membros, convites |
| `/join/:token` | `pages/join/[token].vue` | Entrar via link (layout minimal) |
| `/perfil` | `pages/perfil.vue` | Estatísticas do usuário |
| `/admin` | `pages/admin/index.vue` | Área admin (layout minimal) |

## Componentes compartilhados (avalie consistência entre telas)

Leia e cruze padrões entre:
- `components/MatchCard.vue`, `MatchStatusBadge.vue`, `MatchLocked.vue`, `CountdownTimer.vue`
- `components/ScoreInput.vue`, `components/ui/PointsBadge.vue`, `PhaseBadge.vue`
- `components/ranking/*` (PodiumTop3, RankingRow, RankingRowEmpty)
- `components/group/*` (GroupCard, MemberRow, GroupInviteCard, JoinRequestRow)
- `components/onboarding/*`
- `components/ui/*` (BottomNav, SubPageHeader, ConfirmDialog, Avatar, MatchCardSkeleton)

## O que analisar em cada tela

### UI (visual)
- Uso correto e consistente dos tokens de cor (evitar verde onde deveria ser ouro, etc.)
- Hierarquia tipográfica (`font-display-lg`, `font-headline-lg`, `font-label-caps`, etc.)
- Espaçamento, padding de cards, alinhamento, densidade em mobile
- Estados visuais: hover, active, disabled, loading, empty, error
- Bordas, glass effects, sombras/neon — uso excessivo ou inconsistente
- Contraste e legibilidade (WCAG AA onde possível)
- Coerência de badges, chips, botões primários vs secundários vs ghost

### UX (experiência)
- Clareza do fluxo: login → onboarding → jogos → palpite → ranking/grupos
- Feedback ao usuário: loading, sucesso, erro, confirmações (`ConfirmDialog`)
- Descoberta: o usuário entende pontuação (+3, placar exato, etc.)?
- Navegação: voltar, deep links (`/join/:token`), estado ativo no bottom nav
- Formulários: validação, labels, teclado numérico em placares, prevenção de perda de dados
- Estados vazios e skeletons — são informativos e convidam à ação?
- Acessibilidade: `aria-*`, foco, áreas de toque ≥ 44px, `aria-label` na nav
- Microcopy em português: tom, clareza, consistência de termos (palpite, grupo, ranking)
- Fricção desnecessária: passos extras, informação duplicada, CTAs competindo

### Fluxos críticos (mapear ponta a ponta)
1. Autenticação (Google OAuth em `login.vue`)
2. Onboarding e regras de pontuação
3. Listar jogos → abrir partida → salvar/editar palpite antes do lock
4. Ver pontos no ranking e no perfil
5. Criar grupo → convidar → aprovar entrada → ranking do grupo
6. Entrar por link `/join/:token`

## Metodologia exigida

1. **Leia os arquivos** listados acima (não invente telas que não existem).
2. **Compare padrões** entre telas similares (ex.: `RankingRow` vs `MemberRow`, badges em `GroupCard` vs `MatchCard`).
3. **Identifique inconsistências** com referência ao arquivo e trecho (caminho + descrição do problema).
4. **Priorize** cada item: P0 (quebra UX/confiança), P1 (impacto alto), P2 (polish).
5. **Sugira solução concreta** (classes Tailwind, componente a extrair, copy sugerido) — sem escrever o código completo, a menos que seja um ajuste trivial de 1–2 linhas.
6. **Não proponha redesign total** sem justificar; prefira evolução incremental alinhada ao design system existente.

## Formato da resposta (obrigatório)

### 1. Resumo executivo (5–10 bullets)
Visão geral dos maiores gaps e quick wins.

### 2. Mapa de inconsistências globais
Tabela ou lista: padrão esperado vs onde diverge (com paths).

### 3. Auditoria por tela
Para cada rota do inventário:
- **Pontos fortes** (2–3)
- **Problemas UI** (lista priorizada)
- **Problemas UX** (lista priorizada)
- **Sugestões** (bullets acionáveis)

### 4. Componentes e design system
Oportunidades de unificar (ex.: badge de palpite, empty states, headers).

### 5. Acessibilidade e mobile
Checklist resumido do que falta ou está frágil.

### 6. Backlog priorizado
Tabela final: | Prioridade | Tela/Componente | Problema | Sugestão | Esforço (S/M/L) |

### 7. Top 5 quick wins
Mudanças de baixo esforço e alto impacto que podem ser feitas primeiro.

## Restrições

- Responda em **português (Brasil)**.
- Baseie-se apenas no que existir no repositório; se algo depender de API/backend, marque como "depende de dados — validar em runtime".
- Não altere arquivos; esta é uma fase de análise.
- Se precisir ver comportamento dinâmico (transições, scroll), indique como validar manualmente no browser.

Comece explorando `frontend/tailwind.config.ts`, `frontend/app/layouts/`, `frontend/app/pages/` e `frontend/app/components/`, depois entregue o relatório no formato acima.
```

## FIM DO PROMPT

---

## Variações opcionais

Acrescente ao final do prompt, se quiser refinar o escopo:

**Foco mobile**

```
Assuma viewport 390×844 e priorize touch targets, safe areas (notch/home indicator) e uso com uma mão.
```

**Saída para issues no GitHub**

```
Na seção 6 (Backlog), agrupe cada item pelo arquivo principal afetado, com título sugerido de issue no formato: [UI] ou [UX] + descrição curta.
```

**Incluir comparativo com referências**

```
Quando sugerir melhorias, cite 1 app de referência (ex.: SofaScore, FotMob) apenas como analogia de padrão — sem copiar visual do bolão-copa.
```
