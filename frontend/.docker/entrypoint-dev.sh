#!/bin/sh
set -e
cd /app

# Carrega frontend/.env no shell (docker restart não recarrega env_file)
if [ -f .env ]; then
  set -a
  # shellcheck disable=SC1091
  . ./.env
  set +a
fi

if [ ! -d node_modules ]; then
  pnpm install --frozen-lockfile
fi

# Regenera .nuxt quando ausente ou quando dependências mudaram
if [ ! -d .nuxt ] || [ package.json -nt .nuxt ] || [ pnpm-lock.yaml -nt .nuxt ]; then
  pnpm exec nuxt prepare
fi

exec "$@"
