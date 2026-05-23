# Prompt para auditoria de Performance — bolão-copa

Use este arquivo em uma nova conversa com o Claude (ou outro agente), com o repositório **bolão-copa** aberto para leitura dos arquivos.

## Como usar

1. Abra o projeto no Cursor/Claude Code com o workspace em `bolao-copa`.
2. Copie todo o bloco entre `--- INÍCIO DO PROMPT ---` e `--- FIM DO PROMPT ---`.
3. Cole como primeira mensagem na nova sessão.
4. Opcional: acrescente ao final uma das linhas em [Variações opcionais](#variações-opcionais).

---

## INÍCIO DO PROMPT

```
Você é um engenheiro de performance sênior (front-end, back-end e mobile web). Sua tarefa é fazer uma AUDITORIA COMPLETA DE PERFORMANCE do projeto **bolão-copa** — app de palpites da Copa com grupos, ranking e PWA.

## Objetivo

Analise o código-fonte (frontend Nuxt, backend Laravel, infra Docker quando relevante) e produza um relatório de melhorias de **performance** em todas as camadas afetadas pela experiência do usuário, sem implementar nada ainda — apenas diagnóstico, medição sugerida, priorização e recomendações acionáveis.

Foque em: tempo de carregamento, tamanho de bundle, rede/API, renderização no cliente, cache, polling, banco de dados e escalabilidade sob uso real (muitos jogos + usuários simultâneos na Copa).

## Stack (respeite ao avaliar)

### Frontend (`frontend/`)
- **Nuxt 4** com `ssr: false` (SPA puro) — ver `nuxt.config.ts`
- **Pinia** (`stores/auth.ts`, `stores/ranking.ts`)
- **Composables de dados:** `useMatches.ts`, `usePredictions.ts`, `useGroups.ts`, `useRankings.ts`, `useProfile.ts`, `useAuth.ts`, `useGlobalClock.ts`, `useCountdown.ts`, `usePushNotifications.ts`
- **PWA:** `@vite-pwa/nuxt` — Workbox com `StaleWhileRevalidate` para `/api/matches` (120s) e `/api/rankings` (90s); `registerType: 'autoUpdate'`
- **Fontes externas:** Google Fonts (Anton, Archivo Narrow, JetBrains Mono) + Material Symbols no `<head>` — sem self-host aparente
- **Proxy dev:** Vite proxy `/api` e `/sanctum` → backend Laravel
- **Transições:** `page-fade` global + `transitions.css`
- **Polling conhecido:** `pages/jogos/index.vue` — `setInterval(fetchMatches, 60_000)` com pause em `visibilitychange`

### Backend (`backend/`)
- **Laravel** + **Sanctum** (sessão/cookie via `/sanctum`)
- **Rotas API:** `backend/routes/api.php`
- **Cache Redis:** rankings em `RankingController` — `Cache::remember("ranking:group:{id}", 90, ...)`; invalidação em `GroupController` / `RecalculateRankings`
- **Controllers principais:** `MatchController`, `PredictionController`, `GroupController`, `RankingController`, `UserController`, `AdminController`
- **Jobs:** ex. `RecalculateRankings`, `SendMatchNotification`

### Infra
- `docker-compose.yml`, `docker-compose.prod.yml` (se existir tuning de PHP/nginx/redis)

## Inventário de telas e impacto em performance

Revise o custo de cada rota (requests na mount, re-renders, listas longas, imagens):

| Rota | Arquivo | Foco de performance |
|------|---------|---------------------|
| `/jogos` | `pages/jogos/index.vue` | Lista grande, filtros, polling 60s, bandeiras |
| `/jogos/:id` | `pages/jogos/[id].vue` | Detalhe + palpites de grupo, submit |
| `/ranking` | `pages/ranking.vue` | Lista + pódio, refresh manual |
| `/grupos` | `pages/grupos/index.vue` | Lista de grupos |
| `/grupos/:id` | `pages/grupos/[id].vue` | Membros, requests, ranking do grupo |
| `/perfil` | `pages/perfil.vue` | Stats + histórico de palpites |
| `/login`, `/onboarding`, `/join/:token`, `/admin` | respectivos `pages/*` | Cold start, OAuth redirect, admin sync |

## Inventário de endpoints API (auditar cada um)

| Método | Rota | Controller | Notas |
|--------|------|------------|-------|
| GET | `/api/matches` | MatchController@index | Sem eager load aparente; retorna coleção completa |
| GET | `/api/matches/{id}` | MatchController@show | Query de peers + predictions com `with('user')` |
| GET | `/api/predictions` | PredictionController@index | `with('match')` |
| POST | `/api/predictions` | PredictionController@store | Escrita + possível recálculo |
| GET | `/api/groups` | GroupController@index | Lista |
| GET | `/api/groups/{id}` | GroupController@show | Detalhe |
| GET | `/api/groups/{id}/ranking` | RankingController@groupRanking | Cache 90s |
| GET | `/api/rankings/global` | RankingController@globalRanking | Cache 90s |
| GET | `/api/user` | UserController@show | Auth bootstrap |
| POST | `/api/admin/matches/sync` | AdminController@syncMatches | Operação pesada |

## O que analisar

### 1. Frontend — carregamento e bundle
- Tamanho do bundle JS/CSS (chunks Nuxt, tree-shaking, imports pesados)
- Impacto de `ssr: false` no First Contentful Paint e SEO (trade-offs)
- Fontes: render-blocking, subsetting, `font-display`, self-host vs CDN
- Material Symbols: carregar família inteira vs ícones usados
- CSS: Tailwind purge, `glass-card` + `backdrop-filter` (custo GPU)
- Code splitting por rota; componentes grandes importados eagerly (`jogos/index.vue` ~600+ linhas)
- Imagens: bandeiras (`flagUrl`), `loading="lazy"`, formatos, dimensões, CDN
- PWA: precache (`globPatterns`), tamanho do SW, estratégia vs dados dinâmicos
- Duplicação de fetch entre composables/stores/páginas na mesma sessão

### 2. Frontend — runtime e rede
- Polling em `/jogos` vs PWA cache SWR — risco de dados stale ou requests redundantes
- `useGlobalClock` / `useCountdown` — timers globais e impacto em bateria
- Chamadas `$fetch` sem deduplicação, cache em memória ou `useAsyncData` keys
- Waterfalls: auth → user → matches → predictions (sequência na boot)
- Pinia: estado compartilhado vs refetch em cada navegação
- Page transitions `out-in` — sensação de lentidão?
- Listas virtuais: necessário para N partidas/jogadores?
- Reatividade: computed pesados, sorts em todo tick, `v-for` sem `:key` estável

### 3. Backend — API e banco
- N+1 queries em Resources e controllers
- Índices em colunas filtradas (`status`, `stage`, `starts_at`, `group_id`, `match_id`, `user_id`)
- `MatchController@index` — retorna todos os jogos sempre? paginação?
- `MatchController@show` — subquery `GroupMember` + predictions: custo com muitos grupos
- Ranking: TTL 90s adequado? stampede no cache miss?
- Jobs síncronos vs fila (`RecalculateRankings`, notificações push)
- Serialização JSON (Resources vs arrays manuais)
- Admin sync — timeout, chunking, transações

### 4. Cache e consistência
- Redis ranking vs Workbox `rankings-cache` (90s) vs refetch manual — alinhamento
- Invalidação `Cache::forget` — cobre todos os casos de escrita?
- Sanctum/cookies — cache HTTP em respostas autenticadas (deve ser `private, no-store`)

### 5. Infra e produção
- PHP-FPM workers, OPcache, Laravel config/route cache em prod
- Nginx: gzip/brotli, HTTP/2, timeouts
- Redis persistence e memória
- Build frontend: `nuxt build` output size, assets em CDN
- Variáveis `NUXT_PUBLIC_API_URL`, proxy, CORS em prod

### 6. Métricas e validação (sugerir como medir)
Para cada achado crítico, indique **como reproduzir/medir**:
- Lighthouse (mobile, Slow 4G) — LCP, INP, TBT, bundle
- Chrome DevTools — Network waterfall, Coverage, Performance panel
- `nuxt analyze` ou rollup visualizer (se aplicável)
- Laravel Telescope/Debugbar ou `DB::listen` para query count por request
- Load test leve (ex.: k6) em `GET /api/matches` e `GET /api/rankings/global`

## Metodologia exigida

1. **Leia os arquivos** listados; não invente endpoints ou features.
2. **Trace fluxos críticos** ponta a ponta (cold start → login → /jogos → abrir partida → salvar palpite → ver ranking).
3. **Estime impacto** (usuário percebe? quantos ms/KB/request a mais?).
4. **Priorize:** P0 (degradação severa / escala), P1 (alto impacto), P2 (otimização incremental).
5. **Sugira solução concreta** (ex.: paginar matches, `useAsyncData` com key, índice DB, self-host fonts, `requestIdleCallback`) — sem implementar código completo.
6. **Separe quick wins de refactors** — não proponha reescrever o app sem ROI claro.

## Formato da resposta (obrigatório)

### 1. Resumo executivo (5–10 bullets)
Maiores gargalos e oportunidades de ganho rápido.

### 2. Baseline sugerido
Tabela: métrica | como medir | meta razoável para este app (SPA PWA mobile).

### 3. Frontend — carregamento e assets
Problemas priorizados com paths e sugestões.

### 4. Frontend — runtime, rede e estado
Polling, fetch duplicado, re-renders, timers.

### 5. Backend — API, queries e cache
Por endpoint ou controller; inclua suspeitas de N+1 e índices faltantes.

### 6. PWA, offline e consistência de dados
Workbox vs polling vs cache Laravel.

### 7. Infra e deploy (se aplicável)
Docker/prod apenas com base no que existir no repo.

### 8. Auditoria por tela/rota
Para cada rota do inventário: requests na mount, riscos, sugestões.

### 9. Backlog priorizado
| Prioridade | Área | Arquivo/Endpoint | Problema | Sugestão | Esforço (S/M/L) | Ganho esperado |

### 10. Top 5 quick wins
Mudanças de baixo esforço e alto impacto mensurável.

### 11. Plano de medição pós-correção
Checklist do que re-rodar após implementar as melhorias.

## Restrições

- Responda em **português (Brasil)**.
- Baseie-se no repositório; marque "validar em runtime/prod" quando depender de volume real de dados.
- **Não altere arquivos** — fase de análise apenas.
- Diferencie problemas de **dev** (ex.: `usePolling: true` no Vite) vs **produção**.
- Assuma usuário principal em **mobile 4G** e app instalado como PWA quando relevante.

Comece por `frontend/nuxt.config.ts`, `frontend/app/composables/`, `frontend/app/pages/jogos/`, `backend/routes/api.php`, `backend/app/Http/Controllers/Api/` e `docker-compose*.yml`, depois entregue o relatório no formato acima.
```

## FIM DO PROMPT

---

## Variações opcionais

Acrescente ao final do prompt, se quiser refinar o escopo:

**Foco em Lighthouse / Core Web Vitals**

```
Priorize LCP, INP e CLS em mobile. Para cada P0/P1, indique qual métrica melhora e meta numérica sugerida.
```

**Foco em escala da Copa (pico de tráfego)**

```
Simule mentalmente 10k usuários simultâneos na lista de jogos e no ranking global. Aponte gargalos de backend e cache primeiro.
```

**Saída para issues no GitHub**

```
Na seção 9 (Backlog), agrupe por área [perf-frontend], [perf-api], [perf-infra] com título de issue sugerido.
```

**Incluir budget de bundle**

```
Sugira um budget máximo (KB gzip) para o chunk inicial e para a rota /jogos, com base no que inferir do build Nuxt.
```
