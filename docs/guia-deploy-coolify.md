# Guia de Deploy — bolão-copa em Produção (Coolify + VPS CloudPrime)

> **Domínio:** `bolaodacopa.werlesson.dev`
> **Repositório:** `werlesson/bolao-copa` · branch `main`
> **Gerado em:** 2026-05-30

---

## 1. Resumo Executivo

O projeto **bolão-copa** é implantado como um stack Docker Compose (`docker-compose.prod.yml`) gerenciado pelo Coolify na VPS CloudPrime (São Paulo, ~2 GB RAM). O Traefik do Coolify recebe todo o tráfego HTTPS em `bolaodacopa.werlesson.dev` e roteia para o container `bolao_frontend` (nginx servindo a SPA Nuxt). O nginx do frontend faz proxy reverso de `/api` e `/sanctum` para o container `bolao_nginx` (nginx interno do backend), eliminando CORS. O backend roda Laravel 13 + PHP-FPM 8.4 com PostgreSQL 16 e Redis 7; filas são processadas pelo Horizon. Dependências externas: Google OAuth (Socialite + Sanctum cookie), Football-Data.org API e Web Push (VAPID). O certificado TLS é provisionado automaticamente pelo Let's Encrypt via Traefik, desde que o DNS esteja propagado antes do primeiro deploy.

---

## 2. Pré-requisitos (checklist)

- [ ] **Acesso SSH à VPS** — IP fixo disponível, porta 22 aberta
- [ ] **DNS propagado** — registro A `bolaodacopa → <IP_VPS>` criado no painel do registrador; verificar com:
  ```bash
  nslookup bolaodacopa.werlesson.dev
  dig +short bolaodacopa.werlesson.dev
  ```
- [ ] **Portas 80 e 443 abertas** — no firewall da CloudPrime (security group) E no UFW da VPS
- [ ] **Docker instalado e rodando** na VPS — `docker --version`
- [ ] **Coolify acessível** em `http://<IP_VPS>:8000` — conta admin criada
- [ ] **GitHub App conectado** ao Coolify (Settings → Sources → GitHub App)
- [ ] **Segredos gerados** e armazenados com segurança antes de iniciar o deploy:
  - `APP_KEY` (base64:...)
  - `DB_PASSWORD` (alfanumérico, sem caracteres especiais de shell)
  - `REDIS_PASSWORD` (idem)
  - `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`
  - `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
  - `FOOTBALL_DATA_API_KEY`
- [ ] **`docker-compose.prod.yml` completo** no repositório (ver Seção 7 — Lacunas Críticas)

---

## 3. Passo a Passo

---

### FASE 0 — Reinstalar o Coolify do Zero (VPS via SSH)

> **⚠️ ATENÇÃO: esta fase DESTRÓI todos os projetos, configurações e dados do Coolify atual. Execute apenas se quiser reinstalação limpa.**

#### Passo 0.1 — Conectar na VPS
```bash
ssh root@<IP_VPS>
```

#### Passo 0.2 — Parar e remover todos os containers do Coolify
```bash
docker ps -a --format "{{.Names}}" | grep coolify | xargs -r docker rm -f
```
**Por quê:** garante que nenhum processo antigo interfira na reinstalação.
**Validar:** `docker ps -a | grep coolify` → sem resultados.
**Se falhar:** alguns containers podem estar em estado "Removal In Progress"; aguarde 30s e repita.

#### Passo 0.3 — Remover volumes do Coolify
```bash
docker volume ls --format "{{.Name}}" | grep coolify | xargs -r docker volume rm
```
**Por quê:** limpa dados persistidos (banco de dados interno do Coolify, configurações).
**⚠️ Isso apaga o banco de dados do Coolify — operação irreversível.**

#### Passo 0.4 — Remover diretório de dados
```bash
rm -rf /data/coolify
```

#### Passo 0.5 — Remover redes do Coolify
```bash
docker network ls --format "{{.Name}}" | grep coolify | xargs -r docker network rm
```
**Se falhar:** algum container ainda usa a rede. Execute `docker ps -a` para identificar e remover o container primeiro.

#### Passo 0.6 — Reinstalar o Coolify
```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```
**Por quê:** baixa e executa o instalador oficial, que cria containers, redes e volumes frescos.
**Aguardar:** o processo leva 2–5 minutos. Monitore com `docker ps` até ver os containers `coolify` em `Up`.

#### Passo 0.7 — Acessar o painel e completar onboarding
1. Abra `http://<IP_VPS>:8000` no navegador
2. Crie a conta de admin (e-mail + senha)
3. Na tela "Server", escolha **Localhost** (a própria VPS)
4. Clique em **Validate Server** — o Coolify testa a conexão Docker local

**Validar:** painel carrega sem erros; menu lateral mostra "Servers > localhost" com status verde.

**Se a porta 8000 não responder:**
- Verifique `ufw status` — adicione `ufw allow 8000/tcp` temporariamente
- Verifique `docker ps | grep coolify-proxy` — o Traefik do Coolify deve estar `Up`
- Tente `systemctl restart docker` se o daemon travou durante a instalação

#### Passo 0.8 — Conectar GitHub App
1. Coolify → **Settings → Sources → Add → GitHub App**
2. Siga o fluxo de instalação do GitHub App no repositório `werlesson/bolao-copa`
3. Autorize o acesso ao repo

**Validar:** em Sources, o GitHub App aparece com status "Connected".

---

### FASE A — DNS e Rede

#### Passo A.1 — Criar registro DNS

No painel do registrador do domínio `werlesson.dev`, crie:

| Tipo | Nome | Valor | TTL |
|------|------|-------|-----|
| A | bolaodacopa | `<IP_VPS>` | 300 |

**Por quê:** o Traefik usará este domínio para o certificado Let's Encrypt. Sem DNS propagado, o ACME challenge falha e o HTTPS não funciona.

#### Passo A.2 — Verificar propagação
```bash
nslookup bolaodacopa.werlesson.dev 8.8.8.8
dig +short bolaodacopa.werlesson.dev @1.1.1.1
```
**Resultado esperado:** o IP da VPS em ambos os comandos.
**Se ainda não propagou:** aguarde (TTL 300 = até 5 min; registradores diferentes podem levar até 24h).

#### Passo A.3 — Abrir portas no firewall da VPS
```bash
ufw allow 80/tcp
ufw allow 443/tcp
ufw reload
ufw status
```

**Também verificar no painel CloudPrime:** Security Groups → regras de entrada → adicionar TCP 80 e TCP 443 para `0.0.0.0/0`.

> **⚠️ ORDEM OBRIGATÓRIA:** DNS deve estar propagado **antes** do primeiro deploy HTTPS. Se o Traefik tentar emitir o certificado Let's Encrypt com DNS ainda não resolvido, o domínio pode entrar em cooldown de rate-limit do ACME por até 1 hora.

---

### FASE B — Google Cloud Console

1. Acesse [console.cloud.google.com](https://console.cloud.google.com) → seu projeto OAuth
2. **APIs & Services → Credentials → OAuth 2.0 Client IDs** → edite o client existente
3. Em **Authorized redirect URIs**, adicione:
   ```
   https://bolaodacopa.werlesson.dev/api/auth/google/callback
   ```
4. Em **Authorized JavaScript origins**, adicione:
   ```
   https://bolaodacopa.werlesson.dev
   ```
5. **Mantenha** `http://localhost:3000` e `http://localhost:8000` se ainda desenvolve localmente
6. Salve e copie `Client ID` e `Client Secret`

**Por quê:** o `AuthController.php` (`app/Http/Controllers/Api/AuthController.php` linha 37) usa Socialite com `stateless()` e a URI de callback deve bater exatamente com o que o Google tem registrado — qualquer diferença gera `redirect_uri_mismatch`.

**Validar:** salvo com sucesso → nenhum erro no console do Google.

---

### FASE C — Gerar Segredos

Execute estes comandos **antes** de configurar o Coolify e guarde os resultados em local seguro (gerenciador de senhas).

#### C.1 — APP_KEY
```bash
docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```
Ou, se tiver PHP local:
```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

#### C.2 — DB_PASSWORD e REDIS_PASSWORD
```bash
# Gera senha alfanumérica de 32 chars (sem caracteres especiais que quebram shell/YAML)
openssl rand -base64 24 | tr -d '/+=' | head -c 32
```
Execute duas vezes: uma para DB, uma para Redis. **Use senhas diferentes.**

> **⚠️ CRÍTICO:** Uma vez feito o primeiro deploy, as senhas de Postgres e Redis ficam gravadas nos volumes Docker (`bolao_postgres_data`, `bolao_redis_data`). **Nunca altere `DB_PASSWORD` ou `REDIS_PASSWORD` após o primeiro deploy** sem um procedimento explícito de migração (dump + restore + recriação de volumes). Alterar a variável sem recriar o volume causa `FATAL: password authentication failed` na reconexão do app.

#### C.3 — Chaves VAPID
Execute dentro de um container backend (ou localmente se tiver PHP/Laravel):
```bash
docker run --rm php:8.4-cli sh -c "composer require minishlink/web-push --quiet && php -r \"
\$vapid = \Minishlink\WebPush\VAPID::createVapidKeys();
echo 'VAPID_PUBLIC_KEY='.\$vapid['publicKey'].PHP_EOL;
echo 'VAPID_PRIVATE_KEY='.\$vapid['privateKey'].PHP_EOL;
\""
```
Ou, se o projeto já estiver clonado localmente com dependências instaladas:
```bash
cd backend
php artisan webpush:vapid
```

> **⚠️ NUNCA regenere as chaves VAPID após usuários terem ativado notificações push.** As assinaturas push armazenadas no banco são vinculadas à chave pública. Trocar as chaves invalida todas as assinaturas existentes — os usuários precisarão reativar manualmente. Trate as chaves VAPID como permanentes.

---

### FASE D — Coolify: Criar o Resource

1. Coolify → **Projects** → **+ New Project**
   - Nome: `bolao-copa`
   - Environment: `production`

2. Dentro do projeto → **+ New Resource** → **Docker Compose**

3. Na tela de configuração:
   - **Source:** GitHub App (o que conectou na Fase 0.8)
   - **Repository:** `werlesson/bolao-copa`
   - **Branch:** `main`
   - **Docker Compose file:** `docker-compose.prod.yml`

4. Clique em **Save** (ainda não clique em Deploy)

**Por quê:** o Coolify lerá o compose file e criará as configurações de cada serviço.

**Se o repositório não aparecer:** verifique se o GitHub App tem acesso ao repo `bolao-copa` (GitHub → Settings → Applications → Coolify → Repository access).

---

### FASE E — Coolify: Environment Variables

Na tela do resource criado, vá em **Environment Variables** e adicione todas as variáveis abaixo. **Não adicione as variáveis da coluna "Automático" — elas já estão fixas no compose.**

#### Tabela completa de variáveis a configurar no Coolify

| Variável | Valor para `bolaodacopa.werlesson.dev` | Obrigatória | Notas |
|---|---|---|---|
| `DOMAIN` | `bolaodacopa.werlesson.dev` | **Sim** | Sem `https://`; usado em `APP_URL`, `SANCTUM_STATEFUL_DOMAINS` e label Traefik |
| `APP_NAME` | `Bolão da Copa` | **Sim** | Nome da aplicação |
| `APP_KEY` | `base64:...` (gerado na Fase C.1) | **Sim** | Chave de 32 bytes; sem ela o Laravel não inicia |
| `DB_PASSWORD` | `<senha_forte_sem_especiais>` | **Sim** | ⚠️ Não alterar após 1º deploy |
| `REDIS_PASSWORD` | `<senha_forte_sem_especiais>` | **Sim** | ⚠️ Não alterar após 1º deploy |
| `GOOGLE_CLIENT_ID` | `<do Google Console>` | **Sim** | Fase B |
| `GOOGLE_CLIENT_SECRET` | `<do Google Console>` | **Sim** | Fase B |
| `GOOGLE_REDIRECT_URI` | `https://bolaodacopa.werlesson.dev/api/auth/google/callback` | **Sim** | Deve bater exatamente com o Google Console |
| `FRONTEND_URL` | `https://bolaodacopa.werlesson.dev` | **Sim** | ⚠️ Lacuna — ver Seção 7; necessário para redirect pós-login Google |
| `FOOTBALL_DATA_API_KEY` | `<sua chave>` | **Sim** | football-data.org |
| `FOOTBALL_DATA_COMPETITION` | `WC` | Não | Default já é `WC` no compose |
| `VAPID_SUBJECT` | `mailto:werlessono@gmail.com` | **Sim** | E-mail do administrador |
| `VAPID_PUBLIC_KEY` | `<gerado na Fase C.3>` | **Sim** | ⚠️ Nunca regenerar com usuários ativos |
| `VAPID_PRIVATE_KEY` | `<gerado na Fase C.3>` | **Sim** | ⚠️ Nunca regenerar com usuários ativos |

#### Variáveis derivadas automaticamente pelo compose (NÃO configurar no Coolify)

Estas já estão fixas no bloco `x-app-env` do `docker-compose.prod.yml`:

| Variável | Valor fixo no compose | Por quê é automático |
|---|---|---|
| `APP_ENV` | `production` | Default no compose |
| `APP_DEBUG` | `false` | Default no compose |
| `APP_URL` | `https://${DOMAIN}` | Derivado de `DOMAIN` |
| `DB_CONNECTION` | `pgsql` | Fixo |
| `DB_HOST` | `postgres` | Nome do serviço no compose |
| `DB_PORT` | `5432` | Padrão PostgreSQL |
| `DB_DATABASE` | `bolao` | Fixo |
| `DB_USERNAME` | `bolao` | Fixo |
| `REDIS_HOST` | `redis` | Nome do serviço no compose |
| `REDIS_PORT` | `6379` | Padrão Redis |
| `CACHE_STORE` | `redis` | Fixo |
| `QUEUE_CONNECTION` | `redis` | Fixo |
| `SESSION_DRIVER` | `redis` | Fixo |
| `SESSION_LIFETIME` | `120` | Default |
| `SANCTUM_STATEFUL_DOMAINS` | `${DOMAIN}` | Derivado de `DOMAIN` |

---

### FASE F — Coolify: Domains (Traefik)

1. No resource, vá em **Domains** (ou na configuração do serviço `frontend`)
2. Configure o domínio público **somente no serviço `frontend`** (container `bolao_frontend`)
   - Valor: `https://bolaodacopa.werlesson.dev`
3. **Não configure domínio** no serviço `nginx` interno — ele é acessível apenas dentro da rede Docker

**Por quê:** o Traefik do Coolify usa o domínio configurado para:
- Gerar automaticamente o label `traefik.http.routers.*.rule=Host(\`bolaodacopa.werlesson.dev\`)`
- Solicitar certificado TLS ao Let's Encrypt via ACME HTTP-01 challenge
- Redirecionar tráfego HTTP → HTTPS automaticamente

**Relação com a variável `DOMAIN`:** ambos devem ser idênticos. A variável `DOMAIN` configura o backend (SANCTUM, APP_URL), enquanto o campo Domains do Coolify configura o roteamento Traefik.

> **⚠️ ORDEM OBRIGATÓRIA:** verifique `dig bolaodacopa.werlesson.dev` retornando o IP da VPS **antes** de clicar em Deploy. O Let's Encrypt faz uma requisição HTTP para `/.well-known/acme-challenge/` no domínio — se o DNS não resolver, o certificado falha.

---

### FASE G — Coolify: Build Variables (Frontend)

O Nuxt SPA embute variáveis `NUXT_PUBLIC_*` **em tempo de build** (não em runtime). Portanto, `NUXT_PUBLIC_VAPID_PUBLIC_KEY` precisa estar disponível durante o `pnpm build` do stage `builder` no `frontend/Dockerfile`.

No Coolify, para o serviço **frontend**:
1. Vá em **Build Variables** (ou "Build Arguments" — a nomenclatura varia conforme versão do Coolify)
2. Adicione:

| Variável de Build | Valor |
|---|---|
| `NUXT_PUBLIC_VAPID_PUBLIC_KEY` | `<mesmo valor de VAPID_PUBLIC_KEY>` |
| `NUXT_PUBLIC_API_URL` | _(deixe vazio ou omita)_ |

**Por quê `NUXT_PUBLIC_API_URL` vazio:** o `nuxt.config.ts` (linha 54) define `apiUrl: process.env.NUXT_PUBLIC_API_URL ?? ''`. Em produção, URLs relativas (`/api/...`) são usadas, e o nginx do frontend (`frontend/.docker/nginx/spa.conf` linhas 10–17) faz proxy para `http://nginx:80`. Colocar uma URL absoluta quebraria esse mecanismo e introduziria CORS.

**Por quê `NUXT_PUBLIC_VAPID_PUBLIC_KEY` como build variable:** o `frontend/Dockerfile` stage `builder` (linha 32) executa `pnpm build` sem receber build args explícitos. Para que o Nuxt/Vite injete a variável no bundle estático, ela deve estar disponível como variável de ambiente durante o build. O Coolify passa Build Variables como env vars no container de build.

> Se o frontend for redeployado sem alterar o valor de `NUXT_PUBLIC_VAPID_PUBLIC_KEY`, o build usará o valor cacheado. Qualquer mudança nesta variável exige um **redeploy com rebuild** (não apenas restart).

---

### FASE H — Pós-Deploy: Comandos Obrigatórios

Após todos os containers estarem `healthy`, execute na ordem abaixo via SSH na VPS:

```bash
# 1. Executar migrations (--force obrigatório em APP_ENV=production)
docker exec bolao_app php artisan migrate --force

# 2. Caches de configuração (lê .env / variáveis de ambiente e serializa)
docker exec bolao_app php artisan config:cache

# 3. Cache de rotas (acelera resolução de rotas)
docker exec bolao_app php artisan route:cache

# 4. Cache de views Blade
docker exec bolao_app php artisan view:cache

# 5. Reiniciar o Horizon para carregar configuração atualizada
docker exec bolao_horizon php artisan horizon:terminate
```

**Explicação de cada comando:**

| Comando | Por quê executar |
|---|---|
| `migrate --force` | Cria/atualiza tabelas no PostgreSQL. `--force` é exigido em `production` para confirmar que você sabe o que está fazendo. |
| `config:cache` | Serializa toda a configuração em `bootstrap/cache/config.php`, eliminando leitura de arquivos `.env` em cada requisição. **Obrigatório** para variáveis de ambiente serem lidas corretamente. |
| `route:cache` | Serializa o mapa de rotas. Reduz tempo de bootstrap; obrigatório se usar `config:cache`. |
| `view:cache` | Pré-compila templates Blade. Elimina compilação em tempo de requisição. |
| `horizon:terminate` | Encerra o worker atual graciosamente, forçando o Supervisor a reiniciá-lo com a nova configuração. |

**Sobre seeders:** verifique se existe `database/seeders/DatabaseSeeder.php` com dados obrigatórios. Se houver (ex.: competições padrão):
```bash
docker exec bolao_app php artisan db:seed --force
```
Execute apenas uma vez no primeiro deploy ou após truncar tabelas.

---

### FASE I — Deploy e Logs

#### Passo I.1 — Iniciar o Deploy
No Coolify, clique em **Deploy** no resource `bolao-copa`.

#### Passo I.2 — Acompanhar logs de build
- Coolify → resource → aba **Deployments** → clique no deploy em andamento
- Logs de build aparecem em tempo real
- Identifique os estágios: `postgres`, `redis`, `app` (build mais lento — Composer install), `nginx`, `frontend` (Nuxt build — mais lento de todos)

#### Tempo esperado (primeiro build, VPS 2 GB):

| Etapa | Tempo estimado |
|---|---|
| Pull de imagens base | 2–4 min |
| Build do backend (Composer) | 3–6 min |
| Build do frontend (pnpm + Nuxt) | 4–8 min |
| Subida dos containers | 1–2 min |
| **Total** | **10–20 min** |

> **Dica OOM:** builds simultâneos de Nuxt + Composer consomem muita RAM em VPS de 2 GB. Se o build morrer sem mensagem clara, verifique `dmesg | grep oom`. O `www.conf` (`backend/.docker/php/www.conf`) já está ajustado para `max_children=10` para runtime, mas o build pode travar por OOM. Nesse caso, tente em horário de menor carga.

#### Passo I.3 — Verificar health dos containers
```bash
docker ps --format "table {{.Names}}\t{{.Status}}"
```
**Ordem esperada de healthy:**
1. `bolao_postgres` — sobe primeiro (healthcheck TCP 5432)
2. `bolao_redis` — sobe segundo (healthcheck `redis-cli ping`)
3. `bolao_app` — aguarda postgres + redis (PHP-FPM + Supervisor)
4. `bolao_nginx` — aguarda app (proxy para PHP-FPM:9000)
5. `bolao_horizon` — aguarda redis (worker de filas)
6. `bolao_frontend` — aguarda nginx (serve SPA + proxy)

#### Passo I.4 — Logs de runtime
```bash
# Todos os containers do stack
docker compose -f /data/coolify/.../docker-compose.prod.yml logs -f

# Apenas o app (erros Laravel)
docker logs bolao_app -f

# Apenas o frontend (nginx access/error)
docker logs bolao_frontend -f
```

---

### FASE J — Validação Pós-Deploy

Execute cada verificação abaixo. Todos devem passar antes de considerar o deploy concluído.

| Verificação | Como validar | Resultado esperado |
|---|---|---|
| HTTPS funciona | `curl -I https://bolaodacopa.werlesson.dev` | `HTTP/2 200`, sem erros SSL |
| Certificado TLS | Abrir no browser → cadeado verde | Emitido por Let's Encrypt |
| SPA carrega | Acessar `https://bolaodacopa.werlesson.dev` | Tela de login aparece, sem erros JS no console |
| Proxy API funciona | `curl https://bolaodacopa.werlesson.dev/api/user` | `401 Unauthorized` (não 502) |
| Proxy Sanctum funciona | `curl https://bolaodacopa.werlesson.dev/sanctum/csrf-cookie` | `204 No Content` |
| Login Google | Clicar em "Entrar com Google" | Redireciona para Google → volta para `/jogos` |
| Cookie Sanctum | DevTools → Application → Cookies | Cookie `auth_token` presente, `HttpOnly`, `Secure` |
| Listar jogos | Navegar para `/jogos` logado | Lista de jogos carrega |
| Fazer palpite | Clicar em um jogo e submeter palpite | Confirmação sem erro |
| PWA instalável | DevTools → Application → Manifest | Manifest carregado sem erros |
| Service Worker | DevTools → Application → Service Workers | SW registrado e ativo |
| Push notification | Ativar notificações no perfil | `VAPID_PUBLIC_KEY` enviado corretamente |
| Horizon ativo | `docker exec bolao_horizon php artisan horizon:status` | `Horizon is running` |

---

### FASE K — Atualizações Futuras

#### Redeploy automático (webhook)
1. Coolify → resource → **Settings → Webhooks**
2. Copie a URL do webhook
3. No GitHub: repositório → **Settings → Webhooks → Add webhook**
   - Payload URL: URL copiada do Coolify
   - Content type: `application/json`
   - Event: **Just the push event**
4. Salve e faça um push de teste — o Coolify iniciará redeploy automaticamente

#### Quando é necessário rebuild do frontend
O frontend é um SPA estático gerado em build time. Rebuild obrigatório quando:
- Qualquer variável `NUXT_PUBLIC_*` mudar
- Dependências npm mudarem (`package.json`, `pnpm-lock.yaml`)
- Arquivos de código do frontend forem alterados

Para forçar rebuild no Coolify: **Deploy → Force Rebuild** (não apenas Restart).

#### Migrations em atualizações
Após cada deploy que inclua novas migrations:
```bash
docker exec bolao_app php artisan migrate --force
docker exec bolao_app php artisan config:cache
docker exec bolao_horizon php artisan horizon:terminate
```

#### Nunca faça em produção
- Não execute `php artisan migrate:fresh` (apaga todos os dados)
- Não altere `DB_PASSWORD`/`REDIS_PASSWORD` sem procedimento de migração
- Não regenere chaves VAPID com usuários push ativos
- Não execute `php artisan optimize:clear` sem logo em seguida reexecutar os caches

---

### FASE L — Troubleshooting

| Sintoma | Causa provável | Solução |
|---|---|---|
| Certificado SSL falha / HTTPS não funciona | DNS não propagado antes do deploy HTTPS | Verificar `dig bolaodacopa.werlesson.dev`; se correto, forçar renovação: Coolify → Domains → Renew Certificate |
| `502 Bad Gateway` ao acessar `/api/*` | Container `bolao_app` ou `bolao_nginx` não healthy | `docker ps` para ver status; `docker logs bolao_nginx` e `docker logs bolao_app` para ver erro |
| `502 Bad Gateway` ao acessar a SPA | Container `bolao_frontend` não healthy | `docker logs bolao_frontend`; verificar se o nginx consegue resolver `http://nginx:80` internamente |
| `redirect_uri_mismatch` no OAuth Google | URI no Google Console diferente de `GOOGLE_REDIRECT_URI` | Confirmar que ambos são `https://bolaodacopa.werlesson.dev/api/auth/google/callback` exatos |
| Cookie Sanctum não é enviado nas requisições | `SANCTUM_STATEFUL_DOMAINS` com valor errado | Confirmar que `DOMAIN=bolaodacopa.werlesson.dev` (sem porta, sem protocolo) |
| Redirect para `localhost` após login Google | `FRONTEND_URL` ausente ou errado | Adicionar `FRONTEND_URL=https://bolaodacopa.werlesson.dev` nas env vars do Coolify (ver Seção 7) |
| Container `bolao_postgres` unhealthy | `DB_PASSWORD` contém caracteres especiais (`@`, `#`, `$`) | Recriar com senha apenas alfanumérica; atenção: isso exige recriar o volume |
| `FATAL: password authentication failed` | `DB_PASSWORD` alterada após volumes criados | Não altere a senha após o 1º deploy sem recriar o volume e restaurar dump |
| Rede `coolify` não encontrada | Coolify não criou a rede externa | `docker network create coolify` manualmente; depois reiniciar o Coolify |
| OOM durante o build (Nuxt + Composer simultâneos) | VPS 2 GB RAM insuficiente durante builds simultâneos | Fazer redeploy fora do horário de pico; adicionar swap: `fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile` |
| Notificações push param de funcionar após redeploy | Chaves VAPID foram regeneradas | Nunca regenere VAPID com usuários ativos; reverta para as chaves originais |
| `php artisan config:cache` quebra variáveis | Variável vazia ou com caractere especial não escapado | Verificar no Coolify qual variável está vazia; testar com `docker exec bolao_app php artisan config:show app` |
| Horizon não processa filas | `bolao_horizon` stopped ou em erro | `docker restart bolao_horizon`; verificar `docker logs bolao_horizon` |
| Frontend mostra versão antiga após redeploy | Cache do nginx ou CDN do browser | No browser: Ctrl+Shift+R (hard refresh); no nginx: o `Cache-Control: immutable` para assets com hash garante que versões novas são buscadas |

---

## 4. Tabela Única de Variáveis de Ambiente

| Variável | Valor Produção (`bolaodacopa.werlesson.dev`) | Referência Dev Local | Onde Mudar Depois |
|---|---|---|---|
| `DOMAIN` | `bolaodacopa.werlesson.dev` | _(não existe no dev)_ | Coolify → Env Vars |
| `APP_NAME` | `Bolão da Copa` | `BolãoCopa` (default no compose) | Coolify → Env Vars |
| `APP_KEY` | `base64:<32-bytes>` | `backend/.env` → `APP_KEY` | Coolify → Env Vars |
| `APP_ENV` | `production` _(automático)_ | `local` em `.env` | compose `x-app-env` |
| `APP_DEBUG` | `false` _(automático)_ | `true` em `.env` | compose `x-app-env` |
| `APP_URL` | `https://bolaodacopa.werlesson.dev` _(automático)_ | `http://localhost:8000` | compose `x-app-env` |
| `FRONTEND_URL` | `https://bolaodacopa.werlesson.dev` | `http://localhost:3000` | Coolify → Env Vars _(+ patch no compose)_ |
| `DB_HOST` | `postgres` _(automático)_ | `localhost` ou `postgres` | compose `x-app-env` |
| `DB_PASSWORD` | `<senha forte>` | `backend/.env` → `DB_PASSWORD` | ⚠️ Nunca mudar após 1º deploy |
| `REDIS_HOST` | `redis` _(automático)_ | `localhost` ou `redis` | compose `x-app-env` |
| `REDIS_PASSWORD` | `<senha forte>` | `backend/.env` → `REDIS_PASSWORD` | ⚠️ Nunca mudar após 1º deploy |
| `SANCTUM_STATEFUL_DOMAINS` | `bolaodacopa.werlesson.dev` _(automático via DOMAIN)_ | `localhost:3000,localhost:8000` | compose `x-app-env` |
| `GOOGLE_CLIENT_ID` | `<do Google Console>` | `backend/.env` → mesmo valor | Coolify → Env Vars |
| `GOOGLE_CLIENT_SECRET` | `<do Google Console>` | `backend/.env` → mesmo valor | Coolify → Env Vars |
| `GOOGLE_REDIRECT_URI` | `https://bolaodacopa.werlesson.dev/api/auth/google/callback` | `http://localhost:8000/api/auth/google/callback` | Coolify → Env Vars + Google Console |
| `FOOTBALL_DATA_API_KEY` | `<sua chave>` | `backend/.env` → mesmo valor | Coolify → Env Vars |
| `FOOTBALL_DATA_COMPETITION` | `WC` | `WC` | Coolify → Env Vars |
| `VAPID_SUBJECT` | `mailto:werlessono@gmail.com` | `backend/.env` → mesmo valor | Coolify → Env Vars |
| `VAPID_PUBLIC_KEY` | `<gerado uma vez>` | `backend/.env` → mesmo valor | ⚠️ Nunca mudar com usuários push ativos |
| `VAPID_PRIVATE_KEY` | `<gerado uma vez>` | `backend/.env` → mesmo valor | ⚠️ Nunca mudar com usuários push ativos |
| `NUXT_PUBLIC_VAPID_PUBLIC_KEY` | `<igual VAPID_PUBLIC_KEY>` | _(build arg do Vite)_ | Coolify → Build Variables do serviço frontend |
| `NUXT_PUBLIC_API_URL` | _(vazio)_ | `http://localhost:8000` ou vazio | Coolify → Build Variables do serviço frontend |

---

## 5. Template de Variáveis (Copiável — Sem Segredos Reais)

Cole este bloco no campo de Environment Variables do Coolify e substitua os placeholders:

```env
# ─── Domínio ──────────────────────────────────────────────────────────────────
DOMAIN=bolaodacopa.werlesson.dev

# ─── Aplicação ────────────────────────────────────────────────────────────────
APP_NAME=Bolão da Copa
APP_KEY=base64:SUBSTITUA_PELO_VALOR_GERADO_NA_FASE_C1

# ─── Banco de Dados ───────────────────────────────────────────────────────────
DB_PASSWORD=SUBSTITUA_POR_SENHA_FORTE_SEM_CARACTERES_ESPECIAIS

# ─── Redis ────────────────────────────────────────────────────────────────────
REDIS_PASSWORD=SUBSTITUA_POR_SENHA_FORTE_SEM_CARACTERES_ESPECIAIS

# ─── Google OAuth ─────────────────────────────────────────────────────────────
GOOGLE_CLIENT_ID=SUBSTITUA_PELO_CLIENT_ID_DO_GOOGLE_CONSOLE
GOOGLE_CLIENT_SECRET=SUBSTITUA_PELO_CLIENT_SECRET_DO_GOOGLE_CONSOLE
GOOGLE_REDIRECT_URI=https://bolaodacopa.werlesson.dev/api/auth/google/callback

# ─── URL do Frontend (lacuna — ver Seção 7) ───────────────────────────────────
FRONTEND_URL=https://bolaodacopa.werlesson.dev

# ─── Football Data API ────────────────────────────────────────────────────────
FOOTBALL_DATA_API_KEY=SUBSTITUA_PELA_SUA_CHAVE_FOOTBALL_DATA
FOOTBALL_DATA_COMPETITION=WC

# ─── VAPID (Web Push) ─────────────────────────────────────────────────────────
VAPID_SUBJECT=mailto:SUBSTITUA_PELO_SEU_EMAIL
VAPID_PUBLIC_KEY=SUBSTITUA_PELA_CHAVE_PUBLICA_VAPID_GERADA_NA_FASE_C3
VAPID_PRIVATE_KEY=SUBSTITUA_PELA_CHAVE_PRIVADA_VAPID_GERADA_NA_FASE_C3
```

**Build Variables do serviço `frontend` (campo separado no Coolify):**
```env
NUXT_PUBLIC_VAPID_PUBLIC_KEY=MESMO_VALOR_DE_VAPID_PUBLIC_KEY
NUXT_PUBLIC_API_URL=
```

---

## 6. Lacunas do Repositório + Patches Sugeridos

### Lacuna 1 — CRÍTICA: `docker-compose.prod.yml` incompleto

**Arquivo:** `docker-compose.prod.yml`
**Problema:** O arquivo tem apenas 40 linhas — define o bloco `x-app-env` e inicia o serviço `postgres`, mas está truncado. Os serviços `redis`, `app`, `horizon`, `nginx`, `frontend` (com labels Traefik), volumes e rede `coolify` estão ausentes.
**Impacto:** Deploy impossível sem completar o arquivo.

**Patch sugerido** — adicionar ao `docker-compose.prod.yml` a partir da linha 41:

```yaml
    container_name: bolao_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE:-bolao}
      POSTGRES_USER: ${DB_USERNAME:-bolao}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - bolao_postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-bolao} -d ${DB_DATABASE:-bolao}"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - bolao_network


  # ─── Redis ───────────────────────────────────────────────────────────────────
  redis:
    image: redis:7-alpine
    container_name: bolao_redis
    restart: unless-stopped
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - bolao_redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - bolao_network


  # ─── App (PHP-FPM + Scheduler via Supervisor) ─────────────────────────────
  app:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: bolao_app
    restart: unless-stopped
    environment: *app-env
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - bolao_network


  # ─── Horizon (Queue Worker) ───────────────────────────────────────────────
  horizon:
    build:
      context: ./backend
      dockerfile: Dockerfile
    container_name: bolao_horizon
    restart: unless-stopped
    command: ["php", "artisan", "horizon"]
    environment: *app-env
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - bolao_network


  # ─── Nginx (proxy interno → PHP-FPM) ─────────────────────────────────────
  nginx:
    image: nginx:1.25-alpine
    container_name: bolao_nginx
    restart: unless-stopped
    volumes:
      - ./backend/.docker/nginx/nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - ./backend:/var/www/html:ro
    depends_on:
      - app
    networks:
      - bolao_network


  # ─── Frontend (SPA Nuxt servida por Nginx + Traefik) ─────────────────────
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
      target: prod
      args:
        NUXT_PUBLIC_VAPID_PUBLIC_KEY: ${VAPID_PUBLIC_KEY}
    container_name: bolao_frontend
    restart: unless-stopped
    depends_on:
      - nginx
    networks:
      - bolao_network
      - coolify
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.bolao-frontend.rule=Host(`${DOMAIN}`)"
      - "traefik.http.routers.bolao-frontend.entrypoints=https"
      - "traefik.http.routers.bolao-frontend.tls=true"
      - "traefik.http.routers.bolao-frontend.tls.certresolver=letsencrypt"
      - "traefik.http.services.bolao-frontend.loadbalancer.server.port=80"


volumes:
  bolao_postgres_data:
  bolao_redis_data:


networks:
  bolao_network:
    driver: bridge
  coolify:
    external: true
```

> **Nota sobre o `frontend/Dockerfile`:** o stage `builder` (linha 32) executa `pnpm build` sem receber `ARG`. Para o patch acima funcionar (passando `NUXT_PUBLIC_VAPID_PUBLIC_KEY` via `args`), o `frontend/Dockerfile` precisa declarar o `ARG` no stage `builder`:
> ```dockerfile
> FROM node:20-alpine AS builder
> ARG NUXT_PUBLIC_VAPID_PUBLIC_KEY
> ENV NUXT_PUBLIC_VAPID_PUBLIC_KEY=$NUXT_PUBLIC_VAPID_PUBLIC_KEY
> ```

---

### Lacuna 2 — ALTA: `FRONTEND_URL` ausente no compose

**Arquivo:** `docker-compose.prod.yml` bloco `x-app-env`
**Problema:** O `AuthController.php` (linha 71) usa `config('app.frontend_url')` para montar o redirect pós-login OAuth. A variável `FRONTEND_URL` não está mapeada no `x-app-env`.
**Impacto:** Após login com Google, o usuário é redirecionado para `null/jogos` ou `localhost/jogos` dependendo do valor padrão de `app.frontend_url` no `config/app.php`.

**Patch sugerido** — adicionar ao bloco `x-app-env` do `docker-compose.prod.yml`:
```yaml
x-app-env: &app-env
  # ... variáveis existentes ...
  FRONTEND_URL: ${FRONTEND_URL:-https://${DOMAIN}}
```

**Solução de contorno (sem alterar o compose):** adicionar `FRONTEND_URL=https://bolaodacopa.werlesson.dev` nas Environment Variables do Coolify. O Coolify passa todas as env vars para todos os serviços do compose, então a variável chegará ao container `bolao_app` mesmo sem estar no `x-app-env`. Contudo, a solução correta é adicionar ao compose para documentação e consistência.

---

### Lacuna 3 — ALTA: `ARG NUXT_PUBLIC_VAPID_PUBLIC_KEY` ausente no `frontend/Dockerfile`

**Arquivo:** `frontend/Dockerfile` stage `builder` (linha 23–32)
**Problema:** O `nuxt.config.ts` (linha 55) lê `process.env.NUXT_PUBLIC_VAPID_PUBLIC_KEY` em tempo de build. O Dockerfile não declara `ARG` para receber este valor, então mesmo que o Coolify passe via `args:`, o Vite/Nuxt não terá acesso.
**Impacto:** `vapidPublicKey` ficará vazio no bundle da SPA; notificações push não funcionarão.

**Patch sugerido** — editar `frontend/Dockerfile`:
```dockerfile
# ─── Stage: build ─────────────────────────────────────────────────────────────
FROM node:20-alpine AS builder

WORKDIR /app

# Declarar ARG para que o valor seja acessível como ENV durante o build
ARG NUXT_PUBLIC_VAPID_PUBLIC_KEY
ENV NUXT_PUBLIC_VAPID_PUBLIC_KEY=$NUXT_PUBLIC_VAPID_PUBLIC_KEY

COPY package.json pnpm-lock.yaml .npmrc ./
RUN npm install -g pnpm && pnpm install --frozen-lockfile

COPY . .

RUN pnpm build
```

---

### Lacuna 4 — MÉDIA: nginx interno do backend sem configuração de exemplo no repo

**Problema:** O serviço `nginx` no compose precisa de um `nginx.conf` em `backend/.docker/nginx/`. Se este arquivo não existir no repo, o container falha ao iniciar.
**Solução:** verificar se `backend/.docker/nginx/nginx.conf` existe no repositório. Se não existir, criar com configuração mínima de PHP-FPM:

```nginx
server {
    listen 80;
    root /var/www/html/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

### Lacuna 5 — BAIXA: versão PHP no Dockerfile vs. documentação

**Arquivo:** `backend/Dockerfile` linha 1
**Problema:** `FROM php:8.4-fpm-alpine` — o `backend/CLAUDE.md` menciona PHP 8.3 e as extensões foram testadas nessa versão.
**Impacto:** Baixo — PHP 8.4 é compatível com 8.3, mas pode haver warnings de deprecação não antecipados.
**Recomendação:** alinhar para `php:8.3-fpm-alpine` ou atualizar a documentação para 8.4.

---

## 7. Checklist Final — Produção "No Ar"

Marque cada item antes de considerar o deploy concluído:

- [ ] `docker-compose.prod.yml` completo no repositório (todos os 6 serviços + volumes + redes)
- [ ] `frontend/Dockerfile` com `ARG NUXT_PUBLIC_VAPID_PUBLIC_KEY` no stage `builder`
- [ ] DNS propagado: `dig bolaodacopa.werlesson.dev` retorna IP da VPS
- [ ] Portas 80 e 443 abertas (UFW + CloudPrime Security Groups)
- [ ] Google Console: redirect URI `https://bolaodacopa.werlesson.dev/api/auth/google/callback` adicionada
- [ ] Todas as env vars configuradas no Coolify (ver Fase E)
- [ ] `FRONTEND_URL=https://bolaodacopa.werlesson.dev` configurada no Coolify
- [ ] `NUXT_PUBLIC_VAPID_PUBLIC_KEY` configurada como Build Variable do serviço `frontend`
- [ ] Deploy concluído sem erros no log do Coolify
- [ ] Todos os containers com status `Up` e `healthy`: `docker ps`
- [ ] `docker exec bolao_app php artisan migrate --force` executado com sucesso
- [ ] `docker exec bolao_app php artisan config:cache` executado com sucesso
- [ ] `docker exec bolao_app php artisan route:cache` executado com sucesso
- [ ] `docker exec bolao_app php artisan view:cache` executado com sucesso
- [ ] `docker exec bolao_horizon php artisan horizon:terminate` executado
- [ ] `curl -I https://bolaodacopa.werlesson.dev` → `HTTP/2 200`
- [ ] Certificado TLS: `curl -v https://bolaodacopa.werlesson.dev 2>&1 | grep "issuer"` → Let's Encrypt
- [ ] Login Google end-to-end funcionando (redireciona para `/jogos`, não para localhost)
- [ ] Cookie `auth_token` presente no browser com flags `HttpOnly` e `Secure`
- [ ] `curl https://bolaodacopa.werlesson.dev/api/user` → `401` (não 502)
- [ ] `docker exec bolao_horizon php artisan horizon:status` → `Horizon is running`
- [ ] Webhook GitHub configurado para redeploy automático
- [ ] Senhas `DB_PASSWORD` e `REDIS_PASSWORD` documentadas em gerenciador seguro
- [ ] Chaves VAPID documentadas em gerenciador seguro com aviso de não-regeneração

---

*Guia gerado com base nos arquivos: `docker-compose.prod.yml`, `frontend/Dockerfile`, `frontend/.docker/nginx/spa.conf`, `backend/Dockerfile`, `backend/.docker/php/www.conf`, `backend/app/Http/Controllers/Api/AuthController.php`, `frontend/nuxt.config.ts`.*
