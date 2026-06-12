# BolãoCopa — Backend

API Laravel 13 do BolãoCopa 2026: palpites, grupos, rankings e sincronização de resultados via football-data.org.

## Desenvolvimento (Docker)

```bash
# Na raiz do repositório
docker compose up -d

docker exec bolao_app php artisan migrate
docker exec bolao_app php artisan test
```

Horizon processa filas (`GenerateRankingBulletin`, `RecalculateRankings`, etc.). Após alterar `.env`, reinicie:

```bash
docker compose restart app horizon
```

## Testes

```bash
docker exec bolao_app php artisan test
docker exec bolao_app php artisan test --filter=Bulletin
```

Fixtures golden: `tests/Fixtures/bulletins/`.

---

## Resumos de ranking (bulletins)

Comentários curtos sobre movimentação no ranking após cada jogo, exibidos na tela `/ranking` (aba Geral e grupos).

### Fluxo

1. Jogo finaliza → `RecalculateRankings` recalcula pontos e captura posições antes/depois
2. `GenerateRankingBulletin` gera **1 mensagem por par (grupo, jogo)**
3. Se há destaque relevante **e** IA ligada → Gemini (`gemini-2.5-flash-lite`)
4. Validador rejeita resposta inválida → fallback para template PHP (custo zero)
5. Texto gravado em `ranking_bulletins` — **não regenera** ao refresh

### Destaques que disparam IA

- Troca de liderança
- Entrada/saída do pódio
- Salto ou queda ≥ 3 posições

Ranking estável → só template, sem chamada à API.

### API

```
GET /api/groups/{group}/ranking/bulletin?limit=1
GET /api/rankings/global/bulletin?limit=1
```

- Grupo privado: membro autenticado
- Global: qualquer usuário autenticado
- Cache Redis 90s (`ranking:group:{id}:bulletins:{limit}`)

### Variáveis de ambiente

```env
# false = só templates (recomendado para começar)
AI_RANKING_ENABLED=false

# Chave em https://aistudio.google.com/ — nunca commitar
GEMINI_API_KEY=

GEMINI_MODEL=gemini-2.5-flash-lite
GEMINI_MAX_OUTPUT_TOKENS=64
GEMINI_TEMPERATURE=0.55
BULLETIN_PROMPT_VERSION=3

# 0 = sem limite; ex.: 100 limita chamadas/dia via Redis
AI_RANKING_DAILY_BUDGET=0
```

### Ativar IA

1. Copie as variáveis acima para `backend/.env`
2. Cole `GEMINI_API_KEY`
3. `AI_RANKING_ENABLED=true`
4. `docker compose restart app horizon`

### Dados de demonstração

```bash
docker exec bolao_app php artisan db:seed --class=DemoDataSeeder --force
docker exec bolao_app php artisan db:seed --class=RankingBulletinDemoSeeder --force
docker exec bolao_app php artisan cache:clear
```

Aguarde o Horizon processar os jobs (~5s) e abra `/ranking`.

---

## Checklist manual (QA)

### Banner na UI

- [ ] `/ranking` — aba **Geral** exibe banner acima do pódio quando há bulletin
- [ ] Trocar para grupo privado — bulletin diferente por aba
- [ ] Placar do jogo aparece na faixa do card; **texto não repete placar**
- [ ] Badge **Resumo IA** quando `source=ai`; sem badge extra quando template
- [ ] **Ocultar** (X) → chip compacto; toque reexpande
- [ ] Recarregar página mantém preferência de ocultar (mesmo bulletin)
- [ ] Após novo jogo (novo `bulletinId`) → banner auto-expande

### Backend / API

- [ ] `GET /api/groups/{id}/ranking/bulletin` — 200 para membro
- [ ] Mesmo endpoint — **403** para não-membro
- [ ] `GET /api/rankings/global/bulletin` — 200 para autenticado
- [ ] Resposta vazia (`data: []`) quando grupo não tem bulletin — banner não renderiza

### IA e fallback

- [ ] `AI_RANKING_ENABLED=false` → todos os bulletins `source=template`
- [ ] `AI_RANKING_ENABLED=true` + jogo com troca de liderança → `source=ai` (com key válida)
- [ ] Logs `bulletin.ai_rejected` / `bulletin.ai_failed` em fallback para template

### Regressão

- [ ] Ranking e pódio continuam corretos após recálculo
- [ ] `php artisan test` — suite completa verde

---

## Referência Laravel

Documentação oficial: [laravel.com/docs](https://laravel.com/docs)
