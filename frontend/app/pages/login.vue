<script setup lang="ts">
definePageMeta({
  layout: false,
  middleware: 'guest',
})

const route = useRoute()
const { login } = useAuth()
const loading = ref(false)
const loginError = ref<string | null>(null)
let resetTimer: ReturnType<typeof setTimeout> | null = null

const inviteMessage = computed(() => {
  const message = route.query.message
  return typeof message === 'string' ? message : null
})

function handleLogin() {
  loading.value = true
  loginError.value = null
  login()
  // Se o usuário fechar o popup OAuth, reseta o estado e exibe erro após 20s
  resetTimer = setTimeout(() => {
    loading.value = false
    loginError.value = 'Não foi possível entrar. Tente novamente.'
  }, 20000)
}

onUnmounted(() => {
  if (resetTimer) clearTimeout(resetTimer)
})
</script>

<template>
  <div class="relative flex min-h-dvh flex-col overflow-hidden bg-background text-on-surface">
    <!-- Stadium atmosphere -->
    <div
      class="pointer-events-none absolute inset-0 -z-10"
      style="background: radial-gradient(ellipse 90% 55% at 50% 35%, rgba(0,83,28,0.5) 0%, rgba(0,57,17,0.22) 45%, transparent 72%);"
    />
    <div
      class="pointer-events-none absolute inset-0 -z-10 opacity-[0.05]"
      style="background-image: repeating-linear-gradient(0deg, rgba(101,223,118,0.4) 0px, transparent 1px, transparent 44px, rgba(101,223,118,0.4) 45px), repeating-linear-gradient(90deg, rgba(101,223,118,0.4) 0px, transparent 1px, transparent 64px, rgba(101,223,118,0.4) 65px);"
    />
    <!-- Bottom fade para integrar gradiente com área de login -->
    <div
      class="pointer-events-none absolute bottom-0 left-0 right-0 -z-10 h-56"
      style="background: linear-gradient(to top, rgba(18,20,20,0.95) 0%, transparent 100%);"
    />

    <!-- Logo section -->
    <div class="flex flex-1 flex-col items-center justify-center gap-4 px-margin-mobile text-center">
      <div
        class="flex h-28 w-28 items-center justify-center"
        style="filter: drop-shadow(0 0 30px rgba(101,223,118,0.5)) drop-shadow(0 0 60px rgba(101,223,118,0.25));"
      >
        <span
          class="material-symbols-outlined text-primary"
          style="font-size: 96px; font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 48"
        >sports_soccer</span>
      </div>

      <div>
        <h1 class="font-headline-lg text-headline-lg uppercase tracking-tighter text-primary leading-none">
          BOLÃO COPA
        </h1>
        <p class="mt-2 font-body-lg text-body-lg text-on-surface">
          A elite das previsões de futebol
        </p>
      </div>
    </div>

    <!-- Login section -->
    <div class="px-margin-mobile pb-[max(2.5rem,env(safe-area-inset-bottom))] space-y-4">
      <p
        v-if="inviteMessage"
        class="glass-card rounded-xl border-l-4 border-l-primary/40 px-4 py-3 text-center font-body-sm text-body-sm text-on-surface"
      >
        {{ inviteMessage }}
      </p>

      <p
        v-if="loginError"
        class="rounded-xl border border-error/20 bg-error/10 px-4 py-3 text-center font-body-sm text-body-sm text-error"
      >
        {{ loginError }}
      </p>

      <h2 class="text-center font-headline-lg-mobile text-headline-lg-mobile uppercase text-on-surface">
        ENTRAR NO BOLÃO
      </h2>

      <button
        type="button"
        :disabled="loading"
        aria-label="Continuar com Google"
        class="flex h-14 w-full items-center justify-center gap-3 rounded-xl border border-white/15 bg-surface-container-high px-4 transition-[background-color,transform] hover:bg-surface-bright active:scale-[0.98] disabled:opacity-60"
        @click="handleLogin"
      >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4" />
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
        </svg>
        <span class="font-label-caps text-label-caps text-on-surface">
          {{ loading ? 'REDIRECIONANDO...' : 'CONTINUAR COM GOOGLE' }}
        </span>
      </button>
    </div>
  </div>
</template>
