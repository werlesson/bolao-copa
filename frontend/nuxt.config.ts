export default defineNuxtConfig({
  ssr: false,

  typescript: {
    tsConfig: {
      compilerOptions: {
        types: ['node'],
      },
    },
  },

  devServer: {
    port: 3000,
  },

  experimental: {
    // Required on Nuxt 4.4.5 with ssr:false — disabling causes "No entry found in rollupOptions.input".
    viteEnvironmentApi: true,
  },

  vite: {
    server: {
      watch: {
        usePolling: true,
        interval: 300,
      },
      hmr: {
        clientPort: 3000,
      },
      // Proxy /api and /sanctum through the Vite dev server so the browser
      // makes same-origin requests — no CORS at all.
      // NUXT_PROXY_TARGET: http://localhost:8000 (standalone) | http://nginx (Docker).
      proxy: {
        '/api': {
          target: process.env.NUXT_PROXY_TARGET || 'http://localhost:8000',
          changeOrigin: true,
        },
        '/sanctum': {
          target: process.env.NUXT_PROXY_TARGET || 'http://localhost:8000',
          changeOrigin: true,
        },
      },
    },
  },

  modules: [
    '@vite-pwa/nuxt',
    '@pinia/nuxt',
    '@nuxtjs/tailwindcss',
  ],

  runtimeConfig: {
    public: {
      apiUrl: process.env.NUXT_PUBLIC_API_URL ?? '',
      vapidPublicKey: process.env.NUXT_PUBLIC_VAPID_PUBLIC_KEY || '',
    },
  },

  css: ['~/assets/css/transitions.css'],

  app: {
    pageTransition: { name: 'page-fade', mode: 'out-in' },
    head: {
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-title', content: 'BolãoCopa' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          // Material Symbols: eixos fixados em opsz=24, wght=400, GRAD=0.
          // Somente FILL varia (0 → sem preenchimento, 1 → preenchido),
          // reduzindo o payload de ~300 KB para ~60 KB.
          href: 'https://fonts.googleapis.com/css2?family=Anton&family=Archivo+Narrow:ital,wght@0,400;0,600;0,700;1,400&family=JetBrains+Mono:wght@700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0..1,0&display=swap',
        },
        { rel: 'apple-touch-icon', href: '/icons/icon-192.png' },
      ],
    },
  },

  pwa: {
    registerType: 'autoUpdate',
    client: {
      installPrompt: true,
    },
    // devOptions.enabled is intentionally false: viteEnvironmentApi breaks
    // @vite-pwa/nuxt's dev SW middleware. public/sw.js handles dev instead.
    devOptions: {
      enabled: false,
    },
    manifest: {
      name: 'BolãoCopa 2026',
      short_name: 'BolãoCopa',
      description: 'Bolão da Copa do Mundo 2026',
      start_url: '/jogos',
      scope: '/',
      theme_color: '#121414',
      background_color: '#121414',
      display: 'standalone',
      orientation: 'portrait',
      lang: 'pt-BR',
      icons: [
        { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
        { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
      ],
    },
    workbox: {
      cleanupOutdatedCaches: true,
      // Pré-cacheia apenas o App Shell (HTML, CSS, ícones).
      // Chunks JS são cacheados sob demanda via CacheFirst abaixo,
      // evitando bloquear a ativação do SW em um install payload gigante.
      globPatterns: ['**/*.{css,ico,png,svg}'],
      // Arquivos maiores que 512 KB são excluídos do precache (fallback para rede).
      maximumFileSizeToCacheInBytes: 512 * 1024,
      // Inject push notification handlers into the generated SW
      importScripts: ['/push-handler.js'],
      runtimeCaching: [
        // Navegações HTML: sempre da rede (NetworkFirst) para evitar tela branca na PWA
        {
          urlPattern: ({ request }) => request.mode === 'navigate',
          handler: 'NetworkFirst',
          options: {
            cacheName: 'html-cache',
            networkTimeoutSeconds: 5,
            expiration: { maxEntries: 5, maxAgeSeconds: 86400 },
          },
        },
        // Chunks JS do Nuxt: CacheFirst com validade de 24h (têm hash no nome).
        {
          urlPattern: /\/_nuxt\/.+\.js$/,
          handler: 'NetworkFirst',
          options: {
            cacheName: 'nuxt-chunks',
            expiration: { maxEntries: 80, maxAgeSeconds: 86400 },
          },
        },
        // Matches: NetworkFirst evita que o SW dispare revalidação em background
        // simultânea ao polling do JS, eliminando o double-request problem.
        {
          urlPattern: /\/api\/matches/,
          handler: 'NetworkFirst',
          options: {
            cacheName: 'matches-cache',
            networkTimeoutSeconds: 3,
            expiration: { maxAgeSeconds: 20 },
          },
        },
        {
          urlPattern: /\/api\/rankings/,
          handler: 'StaleWhileRevalidate',
          options: { cacheName: 'rankings-cache', expiration: { maxAgeSeconds: 90 } },
        },
        {
          urlPattern: /\/api\/(groups\/[^/]+\/ranking\/bulletin|rankings\/global\/bulletin)/,
          handler: 'StaleWhileRevalidate',
          options: { cacheName: 'ranking-bulletins-cache', expiration: { maxAgeSeconds: 90 } },
        },
      ],
    },
  },
})
