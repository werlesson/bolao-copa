# Prompt para guia de deploy no Coolify — bolão-copa

Use este arquivo em uma nova conversa com o Claude (ou outro agente), com o repositório **bolão-copa** aberto para leitura dos arquivos.

## Como usar

1. Abra o projeto no Cursor/Claude Code com o workspace em `bolao-copa`.
2. **Antes de colar o prompt**, substitua no bloco abaixo os placeholders:
   - `SEU_DOMINIO.com` → domínio real (ex.: `bolao.meusite.com`)
   - `SEU_REPOSITORIO` → ex.: `werlesson/bolao-copa`
3. Copie todo o bloco entre `--- INÍCIO DO PROMPT ---` e `--- FIM DO PROMPT ---`.
4. Cole como primeira mensagem na nova sessão.

---

## INÍCIO DO PROMPT

```
Você é um engenheiro DevOps/SRE sênior especializado em Laravel, Nuxt e Coolify. Sua tarefa é produzir um **GUIA DE DEPLOY COMPLETO, PASSO A PASSO**, para colocar o projeto **bolão-copa** em produção em uma **VPS com Coolify já instalado**, usando o domínio **bolaodacopa.werlesson.dev** (já registrado e com DNS configurável).

## Objetivo

Leia o repositório (especialmente `docker-compose.prod.yml`, Dockerfiles, configs Laravel/Nuxt) e gere um manual **detalhado, sequencial e sem ambiguidade** para alguém que já tem:
- VPS Ubuntu com Coolify rodando
- Domínio **bolaodacopa.werlesson.dev** (pode ser subdomínio) pronto para apontar por registro DNS tipo A
- Repositório Git: **werlesson/bolao-copa**, branch `main`
- Credenciais Google OAuth, Football Data API e chaves VAPID (podem ser as mesmas do ambiente local de desenvolvimento)

**Não implemente código** nesta resposta — apenas o guia. Se identificar **lacunas no repositório** que impedem deploy correto (ex.: variável ausente no compose), liste-as em seção separada com patch sugerido em markdown.

## Contexto técnico do projeto (valide no código)

### Arquitetura de produção
- Deploy via **Docker Compose**: `docker-compose.prod.yml` na raiz do repo
- **Um único domínio público** (`bolaodacopa.werlesson.dev`): Traefik (Coolify) → container `frontend` (nginx + SPA Nuxt)
- O nginx do frontend faz **proxy** de `/api` e `/sanctum` para o nginx interno do backend (rede Docker `bolao_network`)
- `NUXT_PUBLIC_API_URL` deve ficar **vazio** em produção (URLs relativas)
- Backend: Laravel 13 + PostgreSQL 16 + Redis 7 + Horizon + Sanctum (cookie httpOnly)
- Frontend: Nuxt 4, `ssr: false` (SPA), build estático servido por nginx
- Rede externa obrigatória: `coolify` (criada pelo Coolify na VPS)
- VPS alvo: ~2GB RAM — PHP-FPM já limitado em `backend/.docker/php/www.conf` (max_children=10)

### Serviços no compose (não invente outros)
| Serviço | Container | Exposto ao público? |
|---------|-----------|---------------------|
| postgres | bolao_postgres | Não |
| redis | bolao_redis | Não |
| app | bolao_app | Não (PHP-FPM + scheduler) |
| horizon | bolao_horizon | Não |
| nginx | bolao_nginx | Não (API interna) |
| frontend | bolao_frontend | Sim (via Traefik + Domains no Coolify) |

### Variáveis que o compose **substitui** (${VAR} no Coolify)
Leia `docker-compose.prod.yml` bloco `x-app-env` e documente cada uma com: obrigatória?, exemplo, onde obter, erros comuns.

Inclua obrigatoriamente:
- `DOMAIN` (sem https://) — usado em APP_URL, SANCTUM_STATEFUL_DOMAINS e label Traefik Host()
- `APP_KEY`, `APP_NAME`
- `DB_PASSWORD`, `REDIS_PASSWORD` (usuário inventa; não há gerador no projeto)
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- `FOOTBALL_DATA_API_KEY`, `FOOTBALL_DATA_COMPETITION` (default WC)
- `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`

### Variáveis **derivadas automaticamente** pelo compose (não colocar no Coolify)
Liste o que já está fixo: DB_HOST=postgres, REDIS_HOST=redis, APP_ENV=production, APP_DEBUG=false, APP_URL=https://${DOMAIN}, etc.

### Lacunas conhecidas — o guia DEVE tratar
1. **`FRONTEND_URL`** — usado em `AuthController` callback (`config('app.frontend_url')`) mas **não** está em `docker-compose.prod.yml`. Explique impacto (redirect pós-login Google pode ir para localhost) e dê solução: adicionar `FRONTEND_URL: https://${DOMAIN}` ao `x-app-env` OU variável extra no Coolify se o compose for atualizado.
2. **`NUXT_PUBLIC_VAPID_PUBLIC_KEY`** — necessário no **build** do frontend (`frontend/Dockerfile` stage builder); não está no compose. Explique como configurar **Build Arguments / Build Variables** no Coolify para o serviço `frontend` (mesmo valor de `VAPID_PUBLIC_KEY`).
3. **Primeiro deploy** — senhas de Postgres/Redis gravadas no volume; alertar para não mudar `DB_PASSWORD`/`REDIS_PASSWORD` depois sem procedimento de migração.

## Pré-requisitos (seção do guia)

Detalhe cada item com comandos de verificação:
1. DNS: registro A de `bolaodacopa.werlesson.dev` → IP da VPS; como testar (`nslookup`, `dig`)
2. Firewall: portas 80 e 443 abertas (painel Cloud Prime + UFW na VPS) — comandos `ufw allow` exatos
3. Coolify acessível (porta 8000 ou URL admin) — não expor 8000 publicamente se possível
4. GitHub conectado ao Coolify (GitHub App)
5. Valores preparados em planilha/arquivo seguro antes do deploy

## Passo a passo obrigatório (estrutura da resposta)

Numere cada passo. Para cada passo inclua:
- **O que fazer** (cliques no Coolify quando aplicável)
- **Por quê** (1 frase)
- **Como validar** (comando ou URL)
- **Se falhar** (troubleshooting em 2–4 bullets)

### Seções mínimas

#### A. DNS e rede
- Criar registro A (nome @ ou subdomínio conforme bolaodacopa.werlesson.dev)
- Propagação e teste
- Firewall 80/443

#### B. Google Cloud Console (antes ou logo após deploy)
- Authorized redirect URI: `https://bolaodacopa.werlesson.dev/api/auth/google/callback`
- Authorized JavaScript origins: `https://bolaodacopa.werlesson.dev`
- Reutilizar mesmo Client ID/Secret do dev; manter localhost se ainda desenvolve local

#### C. Gerar segredos (comandos copy-paste)
- `APP_KEY` (php one-liner ou artisan)
- `DB_PASSWORD` e `REDIS_PASSWORD` (como inventar senhas fortes; PowerShell opcional)
- VAPID: `php artisan webpush:vapid` no backend — aviso para **nunca regenerar** após usuários com push ativo

#### D. Coolify — criar resource
- New Project → New Resource → **Docker Compose**
- Repositório, branch `main`, arquivo `docker-compose.prod.yml`
- Conectar GitHub App

#### E. Coolify — Environment Variables
Tabela completa:

| Variável | Valor exemplo para bolaodacopa.werlesson.dev | Obrigatória | Notas |

Inclua linha explícita:
- `DOMAIN=bolaodacopa.werlesson.dev` (sem protocolo)
- `GOOGLE_REDIRECT_URI=https://bolaodacopa.werlesson.dev/api/auth/google/callback`

#### F. Coolify — Domains (Traefik / "Domains for nginx")
- Explicar que é o domínio público do serviço **frontend**, não o nginx interno do backend
- Valor: `https://bolaodacopa.werlesson.dev`
- Relação com variável `DOMAIN` e Let's Encrypt
- Ordem: DNS propagado **antes** do primeiro deploy HTTPS

#### G. Coolify — Build variables (frontend)
- `NUXT_PUBLIC_VAPID_PUBLIC_KEY=<igual VAPID_PUBLIC_KEY>`
- `NUXT_PUBLIC_API_URL` vazio ou omitido

#### H. Coolify — Post-deployment command
Comandos exatos após containers healthy, por exemplo:
```
docker exec bolao_app php artisan migrate --force
docker exec bolao_app php artisan config:cache
docker exec bolao_app php artisan route:cache
docker exec bolao_app php artisan view:cache
docker exec bolao_horizon php artisan horizon:terminate
```
Explique cada comando. Mencionar `php artisan db:seed` apenas se existir seeder necessário no repo (verificar).

#### I. Deploy e logs
- Clicar Deploy; onde ver logs de build e runtime
- Tempo esperado primeiro build
- Ordem de healthchecks (postgres → redis → app → nginx → frontend)

#### J. Validação pós-deploy (checklist)
URLs e resultados esperados:
- `https://bolaodacopa.werlesson.dev` — SPA carrega
- `https://bolaodacopa.werlesson.dev` — 200 (Laravel health)
- `https://bolaodacopa.werlesson.dev/api/...` — não 502 (proxy ok)
- Login Google end-to-end
- Listar jogos, fazer palpite (se dados existirem)
- PWA / service worker (opcional)

#### K. Atualizações futuras (redeploy)
- Push na main → redeploy no Coolify
- Quando rebuild do frontend é necessário (mudança em NUXT_PUBLIC_*)

#### L. Troubleshooting (tabela)
| Sintoma | Causa provável | Solução |
Incluir: certificado SSL falha, 502 Bad Gateway, redirect_uri_mismatch, cookie Sanctum, redirect para localhost após login, container unhealthy, rede `coolify` não encontrada, OOM na VPS 2GB.

## Formato da resposta

1. **Resumo em 5 linhas** — o que será instalado e qual URL final
2. **Pré-requisitos** (checklist marcável)
3. **Passo a passo numerado** (A–L acima) — detalhado
4. **Tabela única de variáveis de ambiente** (Coolify) com colunas: Nome | Valor para bolaodacopa.werlesson.dev | Dev local (referência) | Onde mudar depois
5. **Template .env produção** (bloco copiável só com placeholders, sem segredos reais)
6. **Lacunas do repositório** (se houver) + patches sugeridos
7. **Checklist final** antes de considerar produção “no ar”

## Restrições

- Responda em **português (Brasil)**.
- Use **bolaodacopa.werlesson.dev** consistentemente em todos os exemplos.
- Baseie-se nos arquivos reais do repo; cite paths (`docker-compose.prod.yml`, `frontend/.docker/nginx/spa.conf`, etc.).
- Não assuma subdomínio separado para API (`api.`) — o projeto usa mesmo host.
- Não inclua senhas ou secrets reais no guia — só placeholders.
- Seja explícito sobre diferença **dev** (`docker-compose.yml`, `backend/.env` local) vs **produção** (Coolify env vars apenas).

Comece lendo `docker-compose.prod.yml`, `frontend/Dockerfile`, `frontend/.docker/nginx/spa.conf`, `backend/Dockerfile`, `backend/app/Http/Controllers/Api/AuthController.php`, `frontend/nuxt.config.ts` e `prompt-final.md` (seção deploy/env), depois entregue o guia completo.
```

## FIM DO PROMPT

---

## Placeholders para substituir antes de colar

| Placeholder | Seu valor |
|-------------|-----------|
| `SEU_DOMINIO.com` | ex.: `bolao.meudominio.com` |
| `SEU_REPOSITORIO` | ex.: `werlesson/bolao-copa` |

## Variação opcional

Se a VPS ainda for só IP (sem DNS pronto), acrescente ao final do prompt:

```
O DNS ainda não está pronto. Inclua um apêndice "Deploy temporário por IP" com limitações (sem Let's Encrypt em IP, Google OAuth provavelmente indisponível) e um apêndice "Migrar para domínio" com lista do que alterar no Coolify e no Google Console.
```
