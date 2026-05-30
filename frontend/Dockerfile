# ─── Stage: desenvolvimento ───────────────────────────────────────────────────
FROM node:20-alpine AS dev

WORKDIR /app

RUN npm install -g pnpm

COPY package.json pnpm-lock.yaml .npmrc ./
RUN pnpm install --frozen-lockfile

COPY . .

COPY .docker/entrypoint-dev.sh /usr/local/bin/entrypoint-dev.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint-dev.sh && chmod +x /usr/local/bin/entrypoint-dev.sh

EXPOSE 3000

ENTRYPOINT ["/usr/local/bin/entrypoint-dev.sh"]
CMD ["pnpm", "dev", "--host", "0.0.0.0"]


# ─── Stage: build ─────────────────────────────────────────────────────────────
FROM node:20-alpine AS builder

ARG NUXT_PUBLIC_VAPID_PUBLIC_KEY
ENV NUXT_PUBLIC_VAPID_PUBLIC_KEY=$NUXT_PUBLIC_VAPID_PUBLIC_KEY

WORKDIR /app

COPY package.json pnpm-lock.yaml .npmrc ./
RUN npm install -g pnpm && pnpm install --frozen-lockfile

COPY . .

RUN pnpm build


# ─── Stage: produção (Nginx serve o SPA estático) ─────────────────────────────
FROM nginx:1.25-alpine AS prod

COPY --from=builder /app/.output/public /usr/share/nginx/html
COPY .docker/nginx/spa.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
