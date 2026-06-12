# BolãoCopa 2026 — Prompt de Criação Completo
> Laravel 13 + Nuxt 4 + Docker + PostgreSQL
> Para uso com Claude Code. Todas as decisões de produto e design definidas.
> Este documento é a fonte de verdade do projeto. Leia-o inteiro antes de escrever qualquer código.

---

## 1. Visão geral do projeto

**BolãoCopa** é uma aplicação web PWA para palpites de resultados da Copa do Mundo 2026.

- Usuários fazem palpites de placar antes de cada jogo
- Ganham pontos conforme o acerto (placar exato = 3 pts, vencedor/empate = 1 pt)
- Competem em rankings dentro de grupos criados por eles mesmos
- Todo usuário participa automaticamente do grupo global ao criar conta
- Os resultados são sincronizados automaticamente via football-data.org a cada 2 minutos

**Arquitetura:** dois repositórios separados — `backend/` (Laravel 13 API REST) e `frontend/` (Nuxt 4 SPA/PWA). O frontend consome a API via HTTP. **Não usar Inertia.js.** Idioma da aplicação: **português apenas**.

---

## 2. Stack completa

### Backend — `backend/`
- **Laravel 13** (lançado 17/03/2026) + **PHP 8.3**
- **PostgreSQL 16** via container Docker (sem Supabase)
- **Redis 7** via container Docker (cache de rankings e resultados)
- **Laravel Sanctum** para autenticação via token em cookie httpOnly
- **Laravel Socialite** para Google OAuth
- **Laravel Horizon** para gerenciamento de filas
- **Scheduler** rodando a cada minuto via loop Docker (executa `schedule:run`)
- **minishlink/web-push** para notificações push (Web Push API)
- Deploy via **Coolify** no VPS São Paulo

### Frontend — `frontend/`
- **Nuxt 4** com TypeScript
- `ssr: false` — SPA pura (sem server-side rendering)
- **TailwindCSS** com design system definido neste prompt
- **Pinia** para gerenciamento de estado
- **shadcn-vue** para componentes base
- **Zod + vee-validate** para validação de formulários
- **VueUse** para utilitários reativos
- **@vite-pwa/nuxt** para PWA (service worker, manifest, cache offline)
- Deploy via Coolify (Nginx servindo SPA estático compilado)

### Infraestrutura Docker
- **Desenvolvimento:** `docker-compose.yml` na raiz — serviços: `postgres`, `redis`, `app` (PHP-FPM), `nginx`, `horizon`, `scheduler`, `frontend`
- **Produção:** `docker-compose.prod.yml` — serviços: `postgres`, `redis`, `app` (com Supervisor gerenciando php-fpm + horizon + scheduler num único container), `nginx`, `frontend` (Nginx + SPA estático)

### API de resultados
- **football-data.org** — plano gratuito, sem cartão de crédito
- Endpoint: `GET https://api.football-data.org/v4/competitions/WC/matches`
- Job `SyncMatchResults` roda a cada 2 minutos via scheduler
- Resultados ficam em cache Redis com TTL de 90 segundos
- Ao detectar novo resultado: recalcula pontuações e invalida cache de rankings

---

## 3. Estrutura de diretórios

### Backend (Laravel 13)
```
backend/
  app/
    Http/
      Controllers/Api/
        AuthController.php          # Google OAuth + logout
        MatchController.php         # listagem e detalhe de jogos
        PredictionController.php    # upsert de palpites
        GroupController.php         # CRUD grupos, invite, join, ban, transfer, requests
        RankingController.php       # ranking por grupo e global
        UserController.php          # perfil, nome, push subscription, onboarding
        AdminController.php         # painel admin
      Requests/                     # Form Requests para validação
      Resources/                    # API Resources para transformar respostas
    Jobs/
      SyncMatchResults.php          # sincroniza resultados da API externa
      RecalculateRankings.php       # recalcula rankings após resultado
      GenerateRankingBulletin.php   # resumo pós-jogo por grupo (template ou Gemini)
      SendMatchNotification.php     # push ao finalizar jogo
      SendUpcomingMatchReminders.php # push 1h antes para quem não palpitou
    Services/
      FootballDataService.php       # wrapper da API football-data.org
      ScoringService.php            # lógica de pontuação 3pts/1pt/0
      RankingMovementService.php    # detecta destaques e templates de bulletin
      GeminiBulletinGenerator.php   # gera texto via Gemini 2.5 Flash-Lite
      BulletinContentValidator.php  # valida resposta da IA antes de gravar
      PushNotificationService.php   # envio de notificações via web-push
    Contracts/
      RankingBulletinGenerator.php  # interface do gerador de bulletin
    Models/
      User.php
      Group.php
      GroupMember.php
      GroupJoinRequest.php
      GroupBan.php
      Match.php
      Prediction.php
      Ranking.php
      RankingBulletin.php           # resumo pós-jogo por (grupo, jogo)
    Observers/
      UserObserver.php              # ao criar user: adiciona ao grupo global
  database/
    migrations/                     # uma migration por tabela
    seeders/
      GlobalGroupSeeder.php         # cria o grupo "Geral Copa 2026"
  .docker/
    nginx/default.conf
    php/local.ini
    supervisor/supervisord.conf     # usado apenas em produção
  Dockerfile
  CLAUDE.md
  .env
```

### Frontend (Nuxt 4)
```
frontend/
  app/                              # Nuxt 4: todo código da aplicação fica aqui
    assets/
    components/
      match/
        MatchCard.vue               # card de jogo na listagem
        ScoreInput.vue              # input de palpite com +/- buttons
        MatchLocked.vue             # overlay de palpite encerrado
        CountdownTimer.vue          # countdown reativo até o kickoff
        MatchStatusBadge.vue        # badges: AO VIVO | Adiado | Cancelado | TBD
      ranking/
        RankingRow.vue              # linha do ranking com posição, avatar, pts
        RankingBulletinBanner.vue   # resumo pós-jogo expandível/ocultável
        PodiumTop3.vue              # pódio visual para top 3
      group/
        GroupCard.vue               # card de grupo na listagem
        GroupInviteCard.vue         # card com link copiável e botão regenerar
        MemberRow.vue               # linha de membro na lista do grupo
        JoinRequestRow.vue          # linha de solicitação pendente
      onboarding/
        OnboardingSlide.vue         # slide individual do onboarding
        OnboardingDots.vue          # indicador de progresso
      ui/
        BottomNav.vue               # navegação inferior com 4 tabs
        Avatar.vue                  # foto Google com fallback de iniciais
        PhaseBadge.vue              # pill com fase atual da Copa
        PointsBadge.vue             # badge verde de pontos ganhos
    composables/
      useAuth.ts                    # login, logout, estado do usuário
      useCountdown.ts               # setInterval reativo, emite evento 'kickoff'
      usePushNotifications.ts       # solicitar permissão + subscribe + salvar
      useMatches.ts                 # buscar e filtrar jogos
      usePredictions.ts             # buscar e salvar palpites
      useRankings.ts                # buscar rankings por grupo
      useRankingBulletin.ts         # fetch bulletin por aba (global/grupo)
      useRankingBulletinPrefs.ts     # ocultar/reexibir banner (localStorage)
      useGroups.ts                  # CRUD grupos, convites, membros
      useOnboarding.ts              # controle de exibição (localStorage + API)
    middleware/
      auth.ts                       # redireciona /login se não autenticado
      guest.ts                      # redireciona /jogos se já autenticado
      admin.ts                      # redireciona /jogos se não is_admin
    pages/
      login.vue
      onboarding.vue
      jogos/
        index.vue
        [id].vue
      ranking.vue
      grupos/
        index.vue
        novo.vue
        [id].vue
      join/
        [token].vue
      perfil.vue
      admin/
        index.vue
    plugins/
      pwa.client.ts                 # registra SW + lógica de push
  public/
    icons/                          # ícones PWA (192px, 512px)
  stitch_reference/                 # design exportado do Stitch (ver seção 5)
  .docker/
    nginx/spa.conf
  Dockerfile
  nuxt.config.ts
  tailwind.config.ts
  CLAUDE.md
  .env
```

---

## 4. Banco de dados

Usar PostgreSQL 16. Todas as PKs são UUID. Usar `uuid_generate_v4()` como default.

```sql
-- ─── users ────────────────────────────────────────────────────────────────────
-- Representa um usuário autenticado via Google OAuth.
-- avatar_url vem do Google e é salvo no cadastro.
-- name é editável pelo usuário (diferente do nome do Google).
-- push_subscription armazena o objeto JSON completo do Web Push (endpoint + keys).
-- onboarding_done é marcado true após o usuário concluir os 4 slides.
-- is_admin é definido manualmente no banco — apenas um admin.
CREATE TABLE users (
  id                uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  name              varchar(255) NOT NULL,
  email             varchar(255) UNIQUE NOT NULL,
  avatar_url        text,
  google_id         varchar(255) UNIQUE,
  push_subscription jsonb,
  onboarding_done   boolean NOT NULL DEFAULT false,
  is_admin          boolean NOT NULL DEFAULT false,
  created_at        timestamp,
  updated_at        timestamp
);

-- ─── groups ───────────────────────────────────────────────────────────────────
-- Representa um grupo de bolão.
-- owner_id é null apenas no grupo global (is_global = true).
-- invite_token é um hash único usado no link /join/{token}.
--   Permanente até ser regenerado pelo dono. Regenerar invalida o anterior.
-- require_approval: se true, entrar no grupo cria uma join_request PENDING
--   em vez de group_member direto.
-- max_members: null significa sem limite. Validar no join.
-- O grupo global não pode ser deletado, não tem dono, require_approval é sempre false,
--   e nenhum membro pode sair dele.
CREATE TABLE groups (
  id               uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  name             varchar(255) NOT NULL,
  slug             varchar(255) UNIQUE NOT NULL,
  invite_token     varchar(255) UNIQUE NOT NULL,
  owner_id         uuid REFERENCES users(id) ON DELETE SET NULL,
  is_global        boolean NOT NULL DEFAULT false,
  require_approval boolean NOT NULL DEFAULT false,
  max_members      integer,
  created_at       timestamp,
  updated_at       timestamp
);

-- ─── group_members ────────────────────────────────────────────────────────────
-- Membros ativos de um grupo. Constraint UNIQUE garante que um usuário
-- não pode estar duas vezes no mesmo grupo.
CREATE TABLE group_members (
  id        uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  group_id  uuid NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
  user_id   uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  joined_at timestamp NOT NULL DEFAULT NOW(),
  UNIQUE(group_id, user_id)
);

-- ─── group_join_requests ──────────────────────────────────────────────────────
-- Solicitações de entrada quando require_approval = true.
-- Status: PENDING | APPROVED | REJECTED | CANCELLED
-- PENDING: aguardando decisão do dono.
-- APPROVED: dono aprovou — criar group_member e atualizar status.
-- REJECTED: dono recusou — sem notificação ao solicitante.
-- CANCELLED: solicitante cancelou antes de receber resposta.
-- Usuário recusado pode tentar novamente (criar novo request se status != PENDING).
-- UNIQUE(group_id, user_id) garante só um request ativo por combinação.
CREATE TABLE group_join_requests (
  id         uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  group_id   uuid NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
  user_id    uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status     varchar(20) NOT NULL DEFAULT 'PENDING',
  created_at timestamp,
  updated_at timestamp,
  UNIQUE(group_id, user_id)
);

-- ─── group_bans ───────────────────────────────────────────────────────────────
-- Registra usuários removidos pelo dono do grupo.
-- Usuário banido não pode reentrar no grupo (retornar 403 no join).
-- Usuário que SAIU SOZINHO não é banido e pode reentrar normalmente.
-- banned_by registra qual dono executou a remoção.
CREATE TABLE group_bans (
  id         uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  group_id   uuid NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
  user_id    uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  banned_by  uuid NOT NULL REFERENCES users(id),
  created_at timestamp,
  UNIQUE(group_id, user_id)
);

-- ─── matches ──────────────────────────────────────────────────────────────────
-- Jogos sincronizados da football-data.org.
-- external_id é o ID do jogo na API externa — usado para upsert no sync.
-- home_flag e away_flag são códigos ISO do país (ex: "BR", "FR") usados
--   para renderizar bandeiras no frontend.
-- starts_at é o kickoff em UTC — usado para bloquear palpites.
-- stage: GROUP_STAGE | ROUND_OF_16 | QUARTER_FINALS | SEMI_FINALS | FINAL
-- status: SCHEDULED | LIVE | FINISHED | POSTPONED | CANCELLED
-- home_score e away_score refletem o placar ao fim dos 90 minutos.
--   Prorrogação e pênaltis são IGNORADOS para fins de pontuação.
-- Jogo POSTPONED ou CANCELLED: zerar points_earned das predictions.
-- Jogo com home_team ou away_team como "TBD": bloquear palpite no frontend
--   com texto "Times ainda não definidos".
CREATE TABLE matches (
  id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  external_id integer UNIQUE NOT NULL,
  home_team   varchar(255),
  away_team   varchar(255),
  home_flag   varchar(10),
  away_flag   varchar(10),
  starts_at   timestamp NOT NULL,
  stage       varchar(50) NOT NULL,
  group_name  varchar(50),
  status      varchar(20) NOT NULL DEFAULT 'SCHEDULED',
  home_score  integer,
  away_score  integer,
  synced_at   timestamp,
  created_at  timestamp,
  updated_at  timestamp
);

-- ─── predictions ──────────────────────────────────────────────────────────────
-- Palpites dos usuários. Um palpite por usuário por jogo (upsert).
-- Editável até starts_at exato — backend retorna 422 se starts_at <= now().
-- Um palpite vale para TODOS os grupos do usuário simultaneamente.
--   Não existe palpite por grupo — existe palpite por jogo.
-- points_earned fica null até o jogo terminar.
--   Calculado pelo ScoringService ao processar o resultado.
-- Se o jogo for POSTPONED ou CANCELLED: points_earned = null, não pontua.
CREATE TABLE predictions (
  id            uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id       uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  match_id      uuid NOT NULL REFERENCES matches(id) ON DELETE CASCADE,
  home_score    integer NOT NULL,
  away_score    integer NOT NULL,
  points_earned integer,
  created_at    timestamp,
  updated_at    timestamp,
  UNIQUE(user_id, match_id)
);

-- ─── rankings ─────────────────────────────────────────────────────────────────
-- Rankings calculados e persistidos para performance.
-- Um registro por usuário por grupo.
-- Recalculado pelo job RecalculateRankings após cada resultado.
-- Cache Redis: chave "ranking:group:{group_id}", TTL 90s.
--   RankingController lê do Redis. Job invalida o cache após gravar.
-- Tiebreaker: em caso de empate em total_points, vence quem tem mais exact_scores.
-- Ao sair ou ser removido de um grupo: deletar o registro de rankings desse grupo.
-- Ao reentrar (saiu sozinho, não banido): recriar rankings recalculando
--   a partir das predictions existentes — pontos anteriores são restaurados.
CREATE TABLE rankings (
  id                uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id           uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  group_id          uuid NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
  total_points      integer NOT NULL DEFAULT 0,
  exact_scores      integer NOT NULL DEFAULT 0,
  correct_results   integer NOT NULL DEFAULT 0,
  total_predictions integer NOT NULL DEFAULT 0,
  updated_at        timestamp,
  UNIQUE(user_id, group_id)
);
```

---

## 5. Design system

O design foi gerado no Stitch e exportado para `stitch_reference/`. **Os arquivos do Stitch são referência visual forte, não lei absoluta.** As regras de negócio e comportamento definidos neste prompt prevalecem sempre. Em caso de conflito entre o visual do Stitch e uma regra deste prompt, **a regra prevalece.**

Como usar:
- `screen.png` de cada pasta: referência visual de alto valor — cores, tipografia, espaçamento, hierarquia e atmosfera devem ser respeitados ao máximo
- `code.html` de cada pasta: mostra os tokens Tailwind aplicados na prática — usar como guia de implementação
- Telas do Stitch com funcionalidades não implementadas (`encontrar_amigos`, `descobrir_grupos`): ignorar o comportamento, aproveitar o estilo visual

### Mapeamento de telas Stitch → componentes Vue

| Pasta no stitch_reference | Usar como referência para |
|---|---|
| `login/` | Tela `/login` |
| `onboarding_palpites/` + `onboarding_grupos/` | Slides do onboarding |
| `regulamento_e_pontua_o/` | Slide de pontuação no onboarding |
| `home_jogos/` | Tela `/jogos` (listagem) |
| `detalhes_da_partida/` | Tela `/jogos/[id]` (detalhe + palpitar) |
| `ranking/` | Tela `/ranking` |
| `meus_grupos/` | Tela `/grupos` |
| `criar_novo_grupo/` | Tela `/grupos/novo` |
| `detalhes_do_grupo/` | Tela `/grupos/[id]` |
| `perfil_do_usu_rio/` | Tela `/perfil` |
| `notifica_es/` | Badges de notificação e push |
| `chaveamento_do_torneio/` | Cards da fase eliminatória |
| `classifica_o_dos_grupos/` | Agrupamento de jogos por fase |
| `compartilhar_resultado/` | Compartilhamento (opcional) |
| `descobrir_grupos/` | Estilo visual de cards de grupo apenas |
| `encontrar_amigos/` | Estilo visual de lista de usuários apenas |

### tailwind.config.ts — tokens obrigatórios

```ts
export default {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        'background':                '#111319',  // fundo base de todas as telas
        'surface':                   '#111319',
        'surface-dim':               '#111319',
        'surface-bright':            '#373940',
        'surface-container-lowest':  '#0c0e14',
        'surface-container-low':     '#191b22',  // fundo de cards
        'surface-container':         '#1e1f26',  // fundo de inputs e modais
        'surface-container-high':    '#282a30',  // botões secundários
        'surface-container-highest': '#33343b',  // bordas e separadores
        'on-surface':                '#e2e2eb',  // texto primário
        'on-surface-variant':        '#d1c5ae',  // texto secundário dourado-acinzentado
        'outline':                   '#9a907b',
        'outline-variant':           '#4e4634',  // bordas de cards normais
        'primary':                   '#ffe7af',  // dourado claro — texto de destaque
        'primary-container':         '#f5c842',  // amarelo copa — cor CTA principal
        'on-primary':                '#3d2e00',
        'on-primary-container':      '#6c5400',
        'on-primary-fixed':          '#241a00',  // texto em botões amarelos
        'primary-fixed':             '#ffe08f',
        'primary-fixed-dim':         '#eec13c',
        'surface-tint':              '#eec13c',
        'secondary':                 '#4de082',  // verde — pontos positivos
        'secondary-container':       '#00b55d',
        'on-secondary':              '#003919',
        'on-secondary-container':    '#003e1c',
        'inverse-surface':           '#e2e2eb',
        'inverse-on-surface':        '#2e3037',
        'inverse-primary':           '#755b00',
        'error':                     '#ffb4ab',
        'error-container':           '#93000a',
        'on-error':                  '#690005',
        'on-error-container':        '#ffdad6',
        // aliases funcionais para uso direto nos componentes
        'success':                   '#4de082',  // verde de pontos ganhos
        'success-bg':                '#00210c',  // fundo de badge de pontos
        'live':                      '#4de082',  // cor do dot AO VIVO
      },
      borderRadius: {
        DEFAULT: '0.25rem',
        sm:      '0.25rem',
        md:      '0.5rem',
        lg:      '0.5rem',   // padrão de cards
        xl:      '0.75rem',  // modais e seções
        full:    '9999px',   // pills e badges redondas
      },
      spacing: {
        unit:           '4px',
        gutter:         '12px',  // espaço entre cards
        margin:         '16px',  // margem lateral das telas
        'card-padding': '12px',  // padding interno dos cards
      },
      fontFamily: {
        sans: ['Geist', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],  // placares e números
      },
      fontSize: {
        'display-score': ['32px', { lineHeight: '40px',  letterSpacing: '-0.04em', fontWeight: '700' }],
        'headline-lg':   ['24px', { lineHeight: '32px',  letterSpacing: '-0.02em', fontWeight: '700' }],
        'headline-md':   ['18px', { lineHeight: '24px',  letterSpacing: '-0.02em', fontWeight: '700' }],
        'body-lg':       ['16px', { lineHeight: '24px',  fontWeight: '400' }],
        'body-md':       ['14px', { lineHeight: '20px',  fontWeight: '400' }],
        'label-sm':      ['12px', { lineHeight: '16px',  fontWeight: '600' }],
      },
    },
  },
}
```

### Fontes — carregar via Google Fonts no nuxt.config.ts
```
Geist: wght@400;600;700
JetBrains Mono: wght@700
Material Symbols Outlined: wght,FILL@100..700,0..1
```

### Ícones — Material Symbols Outlined exclusivamente
```html
<span class="material-symbols-outlined">sports_soccer</span>
```
Ícones usados no projeto: `sports_soccer`, `calendar_today`, `leaderboard`, `group`, `person`, `edit`, `add`, `lock`, `stars`, `remove`, `check`, `notifications`, `share`, `settings`, `logout`, `arrow_back`, `content_copy`

### Regras visuais obrigatórias
- Background de todas as telas: `bg-background` (`#111319`) — nunca usar branco
- Cards padrão: `bg-surface-container-low border border-outline-variant rounded-lg p-card-padding`
- Card de jogo AO VIVO: trocar `border-outline-variant` por `border-primary-container` (borda dourada)
- Placares e números importantes: `font-mono text-display-score text-primary-container`
- Botão CTA primário: `bg-primary-container text-on-primary-fixed font-bold rounded-lg`
- Badge AO VIVO: dot `w-2 h-2 rounded-full bg-live animate-pulse` + texto `text-live font-label-sm font-bold`
- Badge de pontos ganhos: `bg-success-bg text-success font-mono text-label-sm rounded-[6px] px-2 py-0.5`
- Sem gradientes, sem sombras, sem glow — apenas tonal layering via cor de superfície
- Texto primário sempre em `text-on-surface`, secundário em `text-on-surface-variant`

---

## 6. Pontuação

```php
// app/Services/ScoringService.php

public function calculate(int $predHome, int $predAway, int $realHome, int $realAway): int
{
    // Regra 1: placar exato → 3 pontos
    if ($predHome === $realHome && $predAway === $realAway) {
        return 3;
    }

    // Regra 2: vencedor correto ou empate correto → 1 ponto
    if ($this->getResult($predHome, $predAway) === $this->getResult($realHome, $realAway)) {
        return 1;
    }

    // Regra 3: errou tudo → 0 pontos
    return 0;
}

private function getResult(int $home, int $away): string
{
    if ($home > $away) return 'home';
    if ($away > $home) return 'away';
    return 'draw';
}

// ATENÇÃO: Na fase eliminatória (mata-mata), usar sempre home_score e away_score
// dos 90 minutos normais. Prorrogação e pênaltis são IGNORADOS.
// A API football-data.org já retorna os placares por período separados —
// usar sempre o campo de tempo normal, não o placar final com acréscimos.
```

**Tiebreaker no ranking:** empate em `total_points` → desempata por `exact_scores` (mais placares exatos ganha).

---

## 7. Regras de negócio detalhadas

### 7.1 Palpites

- **Prazo:** editável via upsert até o `starts_at` exato do jogo. O backend retorna HTTP 422 se `match.starts_at <= Carbon::now()`.
- **Upsert:** `Prediction::updateOrCreate(['user_id' => $userId, 'match_id' => $matchId], ['home_score' => $h, 'away_score' => $a])`. Não existe "criar" e "editar" separados — é sempre upsert.
- **Escopo:** um palpite vale para **todos os grupos** que o usuário participa. Não existe palpite por grupo.
- **Visibilidade:** palpites de todos os membros dos grupos do usuário são **sempre visíveis**, antes e depois do jogo.
- **Jogo TBD:** se `home_team` ou `away_team` for null ou "TBD", o botão "Palpitar" fica desabilitado com texto "Times ainda não definidos". Quando o sync preencher os times, o estado atualiza automaticamente.
- **Jogo POSTPONED ou CANCELLED:** `points_earned = null`, não pontua, exibir badge "Adiado" ou "Cancelado" no card.

### 7.2 Bloqueio visual após kickoff

- O `ScoreInput` fica visível mas desabilitado (`opacity-40`, `pointer-events-none`).
- Um overlay escuro com ícone `lock` (Material Symbols, FILL=1) e textos "PALPITES ENCERRADOS" e "O jogo já começou" cobre o input.
- Exibir o palpite que o usuário fez (ou "Não palpitaste" se não palpitou).
- Referência visual: `stitch_reference/detalhes_da_partida/screen.png` seção inferior.

### 7.3 Countdown

- Composable `useCountdown(startsAt: Date)` com `setInterval` de 1 segundo.
- Formato: `"em 2h 14min"` quando > 1h | `"em 34min 12s"` quando entre 1min e 1h | `"em 58s"` quando < 1min | `"AO VIVO"` ao zerar.
- Ao zerar: emitir evento `'kickoff'` para que o `MatchCard` atualize o estado visual sem precisar de reload.
- Exibir countdown apenas para jogos com `starts_at` nas próximas 24 horas. Para jogos mais distantes: exibir data e horário fixos no formato `dd/mm/yyyy HH:mm`.

### 7.4 Ordenação dos jogos na listagem

1. Status LIVE primeiro
2. Status SCHEDULED em ordem cronológica crescente de `starts_at`
3. Status FINISHED por último (colapsados por padrão, botão "Ver encerrados" para expandir)
4. Status POSTPONED e CANCELLED no fim, após os FINISHED

### 7.5 Fase atual da Copa

- Badge pill no topo da tela `/jogos`.
- Derivar do `stage` dos jogos com status LIVE. Se não houver jogos LIVE, usar o `stage` do próximo jogo SCHEDULED.
- Traduzir os valores da API para português: GROUP_STAGE → "Fase de Grupos", ROUND_OF_16 → "Oitavas de Final", QUARTER_FINALS → "Quartas de Final", SEMI_FINALS → "Semifinais", FINAL → "Final".

### 7.6 Grupos — regras gerais

- **Grupo global:** criado via `GlobalGroupSeeder`, `is_global = true`, `owner_id = null`. Validar no backend:
  - Não pode ser deletado (HTTP 403)
  - Nenhum membro pode sair (HTTP 403)
  - `require_approval` nunca pode ser true
  - `max_members` sempre null
- **Observer:** `UserObserver::created()` adiciona automaticamente o novo usuário ao grupo global via `GroupMember::create()`.
- **Criar grupos:** qualquer usuário pode criar grupos ilimitados.
- **Configurações editáveis pelo dono:** `name`, `require_approval`, `max_members`.
- **Transferir ownership:** `PATCH /api/groups/{id}/transfer { new_owner_id }` — só o owner atual pode executar.
- **Renomear grupo:** `PATCH /api/groups/{id} { name }` — só o owner.

### 7.7 Grupos — entrar via convite

- Link: `/join/{invite_token}` — permanente até ser regenerado.
- Regenerar invalida o anterior. Confirmação no frontend antes de regenerar: "O link atual será invalidado. Continuar?"
- Só o owner pode regenerar: `POST /api/groups/{id}/invite/regenerate`.
- **Fluxo para usuário NÃO autenticado:**
  1. Clica em `/join/{token}` sem estar logado
  2. Frontend salva o token em `sessionStorage('pending_invite_token')`
  3. Redireciona para `/login`
  4. Após autenticar via Google, o callback verifica `sessionStorage`
  5. Se houver token pendente: chama `POST /api/groups/join/{token}` automaticamente
  6. Limpa o `sessionStorage` e redireciona para `/grupos/{id}` do grupo
- **Fluxo para usuário autenticado:** mostra tela de confirmação com nome do grupo e botão "Entrar".

### 7.8 Grupos — aprovação de membros

- Se `require_approval = true`: `POST /api/groups/join/{token}` cria `GroupJoinRequest` com `status = PENDING` em vez de criar `GroupMember`.
- Solicitante vê status "Aguardando aprovação" na tela `/join/{token}`.
- Solicitante pode cancelar enquanto PENDING: `DELETE /api/groups/{id}/requests/{requestId}`.
- Owner vê badge com contagem de solicitações pendentes no card do grupo.
- Owner aprova: `POST /api/groups/{id}/requests/{requestId}/approve` → cria `GroupMember` + atualiza status para APPROVED.
- Owner rejeita: `POST /api/groups/{id}/requests/{requestId}/reject` → status = REJECTED, sem notificação ao solicitante.
- Usuário rejeitado pode tentar novamente (criar novo request) — validar que não há request PENDING.

### 7.9 Grupos — remover e banir membros

- Owner remove membro: `DELETE /api/groups/{id}/members/{userId}`
  1. Cria `GroupBan` (usuário não pode reentrar)
  2. Deleta `GroupMember`
  3. Deleta `Ranking` do usuário nesse grupo (pontos removidos do ranking)
- Usuário banido tentando entrar: retornar HTTP 403 "Você não pode entrar neste grupo".
- Usuário que **saiu sozinho** (não banido): pode reentrar normalmente. Ao reentrar, `RecalculateRankings` reprocessa as `predictions` existentes para restaurar os pontos.

### 7.10 Membros visíveis

Qualquer membro pode ver a lista completa de membros do grupo.

### 7.11 Onboarding

- Exibir se `localStorage.getItem('onboarding_seen')` for null/ausente **OU** se `user.onboarding_done === false`.
- Verificar `localStorage` primeiro — se ausente, exibir mesmo que `onboarding_done` seja true no banco (caso de reinstalação).
- **4 slides:**
  1. Boas-vindas: logo BolãoCopa + "A elite das previsões de futebol"
  2. Como funciona: palpite antes do kickoff, acompanhe ao vivo, ganhe pontos
  3. Pontuação: tabela visual (exato = 3 pts, vencedor/empate = 1 pt)
  4. Notificações: solicitar permissão push + botão "Começar Agora"
- Ao concluir: `localStorage.setItem('onboarding_seen', '1')` + `PATCH /api/user/onboarding`.
- Link "Ver tutorial novamente" disponível na tela `/perfil`.
- Referências visuais: `stitch_reference/onboarding_palpites/`, `onboarding_grupos/`, `regulamento_e_pontua_o/`.

### 7.12 Notificações push

- **Resultado do jogo:** ao mudar `status → FINISHED`, notificar todos os usuários com palpite naquele jogo que têm `push_subscription`. Mensagem: `"França 2×1 Portugal — você ganhou 3 pts! 🎯"` ou `"França 2×1 Portugal — 0 pts dessa vez 😔"`.
- **Lembrete pré-jogo:** 1h antes do kickoff (`starts_at`), notificar usuários que **não** palpitaram naquele jogo e têm `push_subscription`. Mensagem: `"⏰ França × Portugal começa em 1h — faça seu palpite!"`.
- Backend: biblioteca `minishlink/web-push`. Chaves VAPID configuradas em `.env`.
- Frontend: solicitar permissão no slide 4 do onboarding via `usePushNotifications`. Salvar subscription via `PUT /api/user/push-subscription`.

### 7.13 Perfil e estatísticas

- Avatar: `<img>` com `avatar_url` do Google. Fallback: `<div>` com iniciais (primeiras letras do nome) + cor de fundo gerada por hash do nome.
- Nome editável: input inline com botão salvar via `PATCH /api/user { name }`.
- Estatísticas em destaque no topo: posição no ranking global + total de pontos.
- Grid de estatísticas: total de palpites, placares exatos, resultados corretos, aproveitamento %.
- Desempenho por fase: pontos ganhos em cada fase (Grupos, Oitavas, Quartas, Semifinais, Final).
- Toggle de notificações push: ligar/desligar via `PUT`/`DELETE /api/user/push-subscription`.
- Link "Ver tutorial novamente" que redireciona para `/onboarding`.
- Botão "Sair" com confirmação.

### 7.14 Painel admin

- Rota `/admin` protegida pelo middleware `admin.ts` (`user.is_admin === true`).
- Funcionalidades: listar todos os jogos com status e último sync, botão "Forçar Sync" (`POST /api/admin/matches/sync`), listar usuários com contagem de palpites.
- Admin é definido diretamente no banco (`UPDATE users SET is_admin = true WHERE email = '...'`).
- Apenas um admin — `is_admin` não é gerenciado pela interface.

---

## 8. Endpoints da API

```
# ─── Auth ────────────────────────────────────────────────────────────────────
POST  /api/auth/google/redirect         # redireciona para Google OAuth
GET   /api/auth/google/callback         # processa callback, retorna cookie Sanctum
POST  /api/auth/logout                  # invalida token

# ─── Matches ─────────────────────────────────────────────────────────────────
GET   /api/matches                      # lista jogos
                                        # query: ?status=LIVE|SCHEDULED|FINISHED
                                        #        ?stage=GROUP_STAGE|ROUND_OF_16|...
GET   /api/matches/{id}                 # detalhe do jogo + palpites dos grupos do user

# ─── Predictions ─────────────────────────────────────────────────────────────
GET   /api/predictions                  # palpites do usuário autenticado
POST  /api/predictions                  # upsert { match_id, home_score, away_score }
                                        # retorna 422 se starts_at <= now()

# ─── Groups ──────────────────────────────────────────────────────────────────
GET   /api/groups                       # grupos que o usuário participa
POST  /api/groups                       # criar { name, require_approval, max_members }
GET   /api/groups/{id}                  # detalhe do grupo
PATCH /api/groups/{id}                  # editar { name, require_approval, max_members } — owner
DELETE /api/groups/{id}                 # deletar — owner (não permitido no grupo global)
POST  /api/groups/{id}/leave            # sair — não permitido no grupo global
DELETE /api/groups/{id}/members/{userId} # remover membro e banir — owner
PATCH /api/groups/{id}/transfer         # { new_owner_id } — transferir ownership
POST  /api/groups/{id}/invite/regenerate # regenerar invite_token — owner
GET   /api/groups/{id}/ranking          # ranking do grupo (com cache Redis)
GET   /api/groups/{id}/ranking/bulletin # último(s) resumo(s) pós-jogo do grupo
GET   /api/groups/{id}/requests         # solicitações PENDING — owner apenas
POST  /api/groups/{id}/requests/{id}/approve # aprovar solicitação — owner
POST  /api/groups/{id}/requests/{id}/reject  # rejeitar solicitação — owner
DELETE /api/groups/{id}/requests/{id}   # cancelar solicitação — próprio solicitante

POST  /api/groups/join/{token}          # entrar no grupo via token
                                        # se require_approval = false: cria GroupMember
                                        # se require_approval = true: cria GroupJoinRequest PENDING
                                        # se banido: retorna 403
                                        # se grupo lotado: retorna 422

# ─── Rankings ────────────────────────────────────────────────────────────────
GET   /api/rankings/global              # ranking do grupo global (is_global = true)
GET   /api/rankings/global/bulletin     # último(s) resumo(s) do grupo global

# ─── User ────────────────────────────────────────────────────────────────────
GET   /api/user                         # dados do usuário autenticado
PATCH /api/user                         # { name } — editar nome
PATCH /api/user/onboarding              # marcar onboarding_done = true
PUT   /api/user/push-subscription       # { subscription: {...} } — salvar Web Push subscription
DELETE /api/user/push-subscription      # remover subscription

# ─── Admin ───────────────────────────────────────────────────────────────────
GET   /api/admin/matches                # lista todos os jogos com status e synced_at
POST  /api/admin/matches/sync           # dispara SyncMatchResults agora
GET   /api/admin/users                  # lista usuários com total de palpites
```

---

## 9. Jobs e Scheduler

```php
// app/Console/Kernel.php (ou routes/console.php no Laravel 13)
Schedule::job(new SyncMatchResults)->everyTwoMinutes();
Schedule::job(new SendUpcomingMatchReminders)->hourly();

// ─── SyncMatchResults ─────────────────────────────────────────────────────
// 1. GET football-data.org/v4/competitions/WC/matches
// 2. Para cada jogo: upsert em matches (external_id como chave)
// 3. Se status mudou para FINISHED:
//    a. Buscar todas as predictions do jogo
//    b. Para cada prediction: calcular points_earned via ScoringService
//    c. Salvar points_earned em cada prediction
//    d. Despachar RecalculateRankings::dispatch($matchId)
//    e. Despachar SendMatchNotification::dispatch($matchId)
// 4. Se status mudou para POSTPONED ou CANCELLED:
//    a. Setar points_earned = null em todas as predictions do jogo
//    b. Despachar RecalculateRankings::dispatch($matchId)
// 5. Atualizar synced_at

// ─── RecalculateRankings ──────────────────────────────────────────────────
// 1. Buscar todos os grupos que têm pelo menos um membro com palpite no jogo
// 2. Snapshot positionsBefore → recalcular stats → positionsAfter + last_position
// 3. Para cada grupo com palpite no jogo: GenerateRankingBulletin::dispatch(...)
// 4. Deletar chave Redis "ranking:group:{groupId}" para invalidar cache

// ─── GenerateRankingBulletin ─────────────────────────────────────────────
// 1. Montar MovementContext (highlights curados, máx. 3)
// 2. Se isSignificant + AI_RANKING_ENABLED + budget OK → Gemini → validador
// 3. Senão (ou falha) → template PHP gratuito
// 4. UPSERT em ranking_bulletins (group_id, match_id) + invalidar cache bulletin

// ─── SendMatchNotification ────────────────────────────────────────────────
// 1. Buscar predictions do jogo com users que têm push_subscription
// 2. Para cada prediction: montar mensagem personalizada com pontos
// 3. Enviar via PushNotificationService (minishlink/web-push)

// ─── SendUpcomingMatchReminders ───────────────────────────────────────────
// 1. Buscar jogos com starts_at entre now()+55min e now()+65min
// 2. Para cada jogo: buscar users SEM prediction naquele jogo
//    que têm push_subscription
// 3. Enviar lembrete via PushNotificationService
```

---

## 10. Nuxt 4 — configuração e convenções

### Estrutura de diretórios (Nuxt 4)
No Nuxt 4, todo o código da aplicação fica dentro da pasta `app/`. O `nuxt.config.ts` permanece na raiz.

```
frontend/
  app/                  ← todo código aqui (pages, components, composables, etc.)
    app.vue
    error.vue
    pages/
    components/
    composables/
    middleware/
    plugins/
    assets/
  public/
  shared/               ← tipos e utilitários compartilhados (Nuxt 4)
  server/               ← não usado (SPA pura)
  nuxt.config.ts        ← na raiz, fora do app/
  tailwind.config.ts    ← na raiz
```

### nuxt.config.ts
```ts
export default defineNuxtConfig({
  ssr: false,  // SPA pura — sem server-side rendering

  // Nuxt 4: compatibilityVersion não é mais necessário (é o padrão)

  modules: [
    '@vite-pwa/nuxt',
    '@pinia/nuxt',
    '@nuxtjs/tailwindcss',
  ],

  runtimeConfig: {
    public: {
      apiUrl: process.env.NUXT_PUBLIC_API_URL,
      vapidPublicKey: process.env.NUXT_PUBLIC_VAPID_PUBLIC_KEY,
    }
  },

  app: {
    head: {
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' }
      ],
      link: [
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Geist:wght@400;600;700&family=JetBrains+Mono:wght@700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap'
        }
      ]
    }
  },

  pwa: {
    manifest: {
      name: 'BolãoCopa 2026',
      short_name: 'BolãoCopa',
      theme_color: '#111319',
      background_color: '#111319',
      display: 'standalone',
      orientation: 'portrait',
      icons: [
        { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
        { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
      ]
    },
    workbox: {
      navigateFallback: '/',
      globPatterns: ['**/*.{js,css,html,png,svg,ico,woff2}'],
      runtimeCaching: [
        {
          urlPattern: /\/api\/matches/,
          handler: 'StaleWhileRevalidate',
          options: { cacheName: 'matches-cache', expiration: { maxAgeSeconds: 120 } }
        },
        {
          urlPattern: /\/api\/rankings/,
          handler: 'StaleWhileRevalidate',
          options: { cacheName: 'rankings-cache', expiration: { maxAgeSeconds: 90 } }
        },
        {
          urlPattern: /\/api\/(groups\/[^/]+\/ranking\/bulletin|rankings\/global\/bulletin)/,
          handler: 'StaleWhileRevalidate',
          options: { cacheName: 'ranking-bulletins-cache', expiration: { maxAgeSeconds: 90 } }
        }
      ]
    }
  }
})
```

### Convenções Vue / Nuxt 4
- Sempre usar Vue 3 Composition API com `<script setup lang="ts">`
- Imports automáticos estão ativos — não importar composables, `ref`, `computed`, etc. manualmente
- Middleware: usar `defineNuxtRouteMiddleware` (Nuxt 4 suporta async nativamente)
- Busca de dados: usar `useFetch` ou `useAsyncData` (em SPA, equivalente a fetch reativo)
- `useFetch` no Nuxt 4 tem **shallow reactivity por padrão** — usar `{ deep: true }` quando precisar de reatividade profunda no `data`
- Datas: sempre formatar como `dd/mm/yyyy` na exibição
- Navegação: usar `navigateTo()` em vez de `useRouter().push()`

---

## 11. Laravel 13 — configuração e convenções

### Criação do projeto
```bash
composer create-project laravel/laravel:^13.0 backend
```

### Convenções Laravel 13
- PHP 8.3 obrigatório
- Usar **PHP Attributes** nos Models quando possível (nova feature do Laravel 13):
  ```php
  #[Table('users')]
  #[FillableAttributes(['name', 'email', 'avatar_url'])]
  class User extends Authenticatable { ... }
  ```
- Usar **Form Requests** para toda validação de entrada
- Usar **API Resources** para toda resposta da API (nunca retornar Model diretamente)
- Usar `Queue::route()` no AppServiceProvider para centralizar configuração de filas
- Scheduler: usar `Schedule::` facade em `routes/console.php` (Laravel 13 deprecou `Kernel.php`)
- Cache: usar `Cache::touch()` para renovar TTL sem re-buscar valor

### Autenticação
- Token Sanctum em **cookie httpOnly** — nunca em localStorage ou header Authorization
- Google OAuth via `laravel/socialite` com driver `google`
- No callback: buscar ou criar user, gerar token Sanctum, retornar em cookie, redirecionar para o frontend

---

## 12. Detalhes de implementação críticos

1. **Cookie Sanctum:** configurar `SESSION_DOMAIN` e `SANCTUM_STATEFUL_DOMAINS` no `.env`. O frontend deve enviar `credentials: 'include'` em todas as requisições.

2. **Upsert de palpite:** `Prediction::updateOrCreate(['user_id' => $userId, 'match_id' => $matchId], ['home_score' => $h, 'away_score' => $a])`. Antes do upsert: verificar `match.starts_at <= now()` e retornar 422.

3. **Redirect de convite com autenticação:** salvar token em `sessionStorage('pending_invite_token')` ANTES do redirect para login. No callback do OAuth, verificar e processar. Limpar após uso para não re-entrar no próximo login.

4. **Cache de ranking Redis:** chave `ranking:group:{groupId}`. `RankingController` lê do Redis primeiro; se não existir, busca do banco e salva no Redis com TTL 90s. `RecalculateRankings` job apaga a chave após gravar no banco.

5. **Grupo global imutável:** adicionar verificação em `GroupController` para qualquer operação que altere grupos: se `group->is_global`, retornar 403 para DELETE, leave, e mudança de `require_approval`.

6. **Onboarding duplo check:** `useOnboarding` verifica `localStorage` primeiro. Se `onboarding_seen` estiver ausente, exibir independente do valor de `user.onboarding_done`. Isso garante que reinstalações do PWA mostrem o onboarding novamente.

7. **Avatar com fallback:** `<Avatar>` component exibe `<img src="avatar_url">` se disponível. Se não: `<div>` com as iniciais do nome, cor de fundo determinada por `hashCode(name) % palette.length`.

8. **Safe area iOS:** `padding-bottom: env(safe-area-inset-bottom)` no `BottomNav`. `viewport-fit=cover` no meta viewport. Usar Tailwind: `pb-[env(safe-area-inset-bottom)]`.

9. **VAPID keys:** gerar uma única vez com `php artisan webpush:vapid`. **Nunca regenerar em produção** — invalida todas as subscriptions existentes.

10. **Placar de mata-mata:** a API football-data.org retorna `regularTime`, `extraTime` e `penalties` separados. Usar sempre `regularTime.homeTeam` e `regularTime.awayTeam` para `home_score` e `away_score`. Nunca usar o placar final com acréscimos.

11. **Shallow reactivity no Nuxt 4:** `useFetch` e `useAsyncData` têm shallow reactivity por padrão. Para objetos aninhados que precisam de reatividade, usar `{ deep: true }` ou desestruturar com `toRefs`.

12. **Offline PWA:** página `app/error.vue` serve como fallback offline. Exibir mensagem "Você está sem conexão" com design consistente com o app.

---

## 13. Variáveis de ambiente

### backend/.env (desenvolvimento)
```env
APP_NAME=BolãoCopa
APP_ENV=local
APP_KEY=                                    # gerar com php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=postgres                            # nome do serviço Docker
DB_PORT=5432
DB_DATABASE=bolao
DB_USERNAME=bolao
DB_PASSWORD=secret

REDIS_HOST=redis                            # nome do serviço Docker
REDIS_PORT=6379
REDIS_PASSWORD=null

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=cookie
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:3000

FOOTBALL_DATA_API_KEY=                      # obter em football-data.org
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback

VAPID_PUBLIC_KEY=                           # gerar com php artisan webpush:vapid
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:seu@email.com

# Resumos de ranking (Gemini) — ver backend/README.md
AI_RANKING_ENABLED=false
GEMINI_API_KEY=                             # https://aistudio.google.com/
GEMINI_MODEL=gemini-2.5-flash-lite
GEMINI_MAX_OUTPUT_TOKENS=64
GEMINI_TEMPERATURE=0.55
BULLETIN_PROMPT_VERSION=3
AI_RANKING_DAILY_BUDGET=0
```

### frontend/.env (desenvolvimento)
```env
NUXT_PUBLIC_API_URL=http://localhost:8000
NUXT_PUBLIC_VAPID_PUBLIC_KEY=              # mesmo valor de VAPID_PUBLIC_KEY do backend
```

### Produção (adicionar no Coolify)
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.seudominio.com
DB_HOST=postgres
DB_PASSWORD=senha_forte_aqui
REDIS_PASSWORD=senha_redis_aqui
SESSION_DOMAIN=.seudominio.com
SANCTUM_STATEFUL_DOMAINS=app.seudominio.com
GOOGLE_REDIRECT_URI=https://api.seudominio.com/api/auth/google/callback
```

---

## 14. Ordem de implementação

### Fase 1 — Backend base
1. Migrations (uma por tabela, na ordem: users, groups, group_members, group_join_requests, group_bans, matches, predictions, rankings)
2. Models com relacionamentos e PHP Attributes (Laravel 13)
3. GlobalGroupSeeder (cria grupo global)
4. UserObserver (adiciona ao grupo global no `created`)
5. Google OAuth com Socialite + Sanctum (cookie httpOnly)
6. FootballDataService + Job SyncMatchResults + Scheduler

### Fase 2 — Backend negócio
7. ScoringService (3pts/1pt/0)
8. Job RecalculateRankings com invalidação Redis
9. MatchController + PredictionController (endpoints de jogos e palpites)
10. GroupController completo (CRUD, invite, join, ban, transfer, requests)
11. RankingController com cache Redis
12. PushNotificationService + Jobs de notificação
13. AdminController
14. UserController (perfil, nome, push subscription, onboarding)

### Fase 3 — Frontend setup
15. `tailwind.config.ts` com todos os tokens da seção 5
16. `nuxt.config.ts` com PWA, Pinia, fontes e meta viewport
17. Componentes UI base: `BottomNav`, `Avatar`, `PhaseBadge`, `PointsBadge`
18. Fluxo de autenticação: `/login`, middleware `auth`/`guest`, `useAuth`, cookie

### Fase 4 — Frontend telas
19. Onboarding (4 slides + `useOnboarding`) — ref: `onboarding_palpites/`, `onboarding_grupos/`, `regulamento_e_pontua_o/`
20. `/jogos` — listagem com `MatchCard`, `CountdownTimer`, `MatchStatusBadge` — ref: `home_jogos/`
21. `/jogos/[id]` — detalhe com `ScoreInput`, `MatchLocked`, palpites — ref: `detalhes_da_partida/`
22. `/ranking` — `RankingBulletinBanner`, `RankingTopGroups`, `RankingRow` — ref: `ranking/`
23. `/grupos` — listagem com `GroupCard` — ref: `meus_grupos/`
24. `/grupos/novo` — formulário de criação — ref: `criar_novo_grupo/`
25. `/grupos/[id]` — detalhe com `GroupInviteCard`, `MemberRow`, `JoinRequestRow` — ref: `detalhes_do_grupo/`
26. `/perfil` — estatísticas, toggle push, edição de nome — ref: `perfil_do_usu_rio/`
27. `/join/[token]` — fluxo de convite com redirect
28. `/admin` — painel admin com middleware `admin`

### Fase 5 — PWA e produção
29. `usePushNotifications` (permissão + subscribe + save)
30. Testar PWA: installability, offline fallback, push notifications
31. `docker-compose.prod.yml` com Supervisor
32. Deploy no Coolify
