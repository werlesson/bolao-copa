<script setup lang="ts">
definePageMeta({
  layout: false,
  middleware: ['auth'],
})

const TOTAL_SLIDES = 4
const currentSlide = ref(0)
const finishing = ref(false)

const { needsOnboarding, isReplay, completeOnboarding } = useOnboarding()
const {
  permission,
  loading: pushLoading,
  error: pushError,
  isSupported,
  subscribeAndSave,
  syncPermission,
} = usePushNotifications()

watchEffect(() => {
  if (!needsOnboarding.value && !isReplay.value) {
    navigateTo('/jogos', { replace: true })
  }
})

function nextSlide() {
  if (currentSlide.value < TOTAL_SLIDES - 1) {
    currentSlide.value += 1
    if (currentSlide.value === TOTAL_SLIDES - 1) {
      syncPermission()
    }
  }
}

function skipToEnd() {
  currentSlide.value = TOTAL_SLIDES - 1
  syncPermission()
}

async function handleEnablePush() {
  await subscribeAndSave()
}

async function handleFinish() {
  if (finishing.value) return
  finishing.value = true

  if (isSupported.value && permission.value === 'default') {
    await subscribeAndSave()
  }

  await completeOnboarding()
  await navigateTo('/jogos', { replace: true })
}

const steps = [
  {
    title: 'Boas-vindas',
    description: 'A elite das previsões de futebol',
  },
  {
    title: 'Como funciona',
    description: 'Palpite antes do apito inicial, acompanhe os jogos ao vivo e ganhe pontos a cada acerto.',
  },
  {
    title: 'Pontuação',
    description: 'Quanto mais preciso o palpite, mais pontos você leva para o ranking.',
  },
  {
    title: 'Fique por dentro',
    description: 'Ative as notificações para receber lembretes de jogos e saber quantos pontos ganhou.',
  },
] as const

const scoringRules = [
  {
    icon: 'star',
    label: 'Placar exato',
    points: '3 pts',
    detail: 'Acertou os gols das duas seleções',
    accent: 'text-secondary-container',
    bg: 'bg-secondary-container/10 border-secondary-container/20',
  },
  {
    icon: 'check_circle',
    label: 'Vencedor ou empate',
    points: '1 pt',
    detail: 'Acertou quem venceu ou se empatou',
    accent: 'text-primary',
    bg: 'bg-primary/10 border-primary/20',
  },
] as const
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-background text-on-surface">
    <!-- Header -->
    <header class="flex h-16 shrink-0 items-center justify-between border-b border-outline-variant/40 px-margin-mobile">
      <div class="flex items-center gap-2">
        <span
          class="material-symbols-outlined text-primary"
          style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24"
        >sports_soccer</span>
        <span class="font-headline-lg-mobile text-headline-lg-mobile text-primary tracking-tighter">BolãoCopa 2026</span>
      </div>
      <button
        v-if="currentSlide < TOTAL_SLIDES - 1"
        type="button"
        class="rounded px-3 py-2 font-label-caps text-label-caps uppercase tracking-wider text-on-surface-variant transition-colors hover:bg-surface-container-high"
        @click="skipToEnd"
      >
        PULAR
      </button>
    </header>

    <main class="flex flex-1 flex-col overflow-hidden px-margin-mobile py-6">
      <Transition name="slide-onboarding" mode="out-in">
      <div :key="currentSlide" class="flex flex-col items-stretch">
      <!-- Slide 1: Boas-vindas -->
      <OnboardingSlide
        v-if="currentSlide === 0"
        :title="steps[0].title"
        :description="steps[0].description"
      >
        <div class="mx-auto flex max-w-sm flex-col items-center gap-4">
          <div
            class="flex h-28 w-28 items-center justify-center"
            style="filter: drop-shadow(0 0 24px rgba(101,223,118,0.45));"
          >
            <span
              class="material-symbols-outlined text-primary"
              style="font-size: 88px; font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 48"
            >sports_soccer</span>
          </div>
          <p class="font-display-lg text-display-lg text-secondary-container leading-none">
            2026
          </p>
          <p class="text-center font-body-lg text-body-lg text-on-surface-variant max-w-xs">
            Palpite nos jogos, acumule pontos e dispute com seus amigos.
          </p>
        </div>
      </OnboardingSlide>

      <!-- Slide 2: Como funciona -->
      <OnboardingSlide
        v-else-if="currentSlide === 1"
        :title="steps[1].title"
        :description="steps[1].description"
      >
        <div class="mx-auto w-full max-w-md overflow-hidden rounded-xl">
          <div class="glass-card rounded-xl p-4 space-y-3">
            <!-- Live badge + group -->
            <div class="flex items-center justify-between">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 border border-primary/20 px-3 py-1">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary" />
                <span class="font-label-caps text-label-caps text-primary">AO VIVO</span>
              </span>
              <span class="font-label-caps text-label-caps text-on-surface-variant">GRUPO G</span>
            </div>
            <!-- Teams + score -->
            <div class="flex items-center justify-between gap-4">
              <div class="flex flex-1 flex-col items-center gap-1">
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-white/10 bg-surface-container">
                  <span class="text-2xl">🇧🇷</span>
                </div>
                <span class="font-label-caps text-label-caps">BRASIL</span>
              </div>
              <div class="flex items-center gap-2 font-display-lg text-display-lg text-on-surface">
                <span>2</span>
                <span class="text-on-surface-variant text-[24px]">:</span>
                <span>1</span>
              </div>
              <div class="flex flex-1 flex-col items-center gap-1">
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-white/10 bg-surface-container">
                  <span class="text-2xl">🇨🇭</span>
                </div>
                <span class="font-label-caps text-label-caps">SUÍÇA</span>
              </div>
            </div>
            <!-- Points badge -->
            <div class="flex justify-center pt-1 border-t border-white/5">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary-container/15 border border-secondary-container/30 px-4 py-1.5">
                <span
                  class="material-symbols-outlined text-[16px] text-secondary-container"
                  style="font-variation-settings: 'FILL' 1"
                >stars</span>
                <span class="font-label-caps text-label-caps text-secondary-container">+3 PONTOS</span>
              </span>
            </div>
          </div>
        </div>
      </OnboardingSlide>

      <!-- Slide 3: Pontuação -->
      <OnboardingSlide
        v-else-if="currentSlide === 2"
        :title="steps[2].title"
        :description="steps[2].description"
      >
        <div class="mx-auto w-full max-w-md space-y-3">
          <div
            v-for="rule in scoringRules"
            :key="rule.label"
            class="glass-card flex items-center justify-between rounded-xl border p-4"
            :class="rule.bg"
          >
            <div class="flex items-center gap-3">
              <span
                class="material-symbols-outlined text-[28px]"
                :class="rule.accent"
                :style="rule.icon === 'star' ? `font-variation-settings: 'FILL' 1` : undefined"
              >{{ rule.icon }}</span>
              <div class="text-left">
                <p class="font-title-md text-title-md text-on-surface">
                  {{ rule.label }}
                </p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                  {{ rule.detail }}
                </p>
              </div>
            </div>
            <span class="font-headline-lg-mobile text-headline-lg-mobile shrink-0 pl-4" :class="rule.accent">
              {{ rule.points }}
            </span>
          </div>
        </div>
      </OnboardingSlide>

      <!-- Slide 4: Push -->
      <OnboardingSlide
        v-else
        :title="steps[3].title"
        :description="steps[3].description"
      >
        <div class="mx-auto w-full max-w-md">
          <div class="glass-card rounded-xl border border-white/5 p-6 space-y-4">
            <!-- Bell icon -->
            <div class="flex justify-center">
              <div class="flex h-20 w-20 items-center justify-center rounded-full bg-primary/10 border border-primary/20">
                <span
                  class="material-symbols-outlined text-primary text-[44px]"
                  style="font-variation-settings: 'FILL' 1"
                >notifications</span>
              </div>
            </div>
            <!-- Features list -->
            <ul class="space-y-3 text-left">
              <li class="flex items-start gap-3 font-body-lg text-body-lg text-on-surface-variant">
                <span class="material-symbols-outlined text-[22px] text-secondary-container shrink-0" style="font-variation-settings: 'FILL' 1">alarm</span>
                Lembrete 1h antes para não perder o prazo do palpite
              </li>
              <li class="flex items-start gap-3 font-body-lg text-body-lg text-on-surface-variant">
                <span class="material-symbols-outlined text-[22px] text-primary shrink-0" style="font-variation-settings: 'FILL' 1">stars</span>
                Resultado e pontos imediatamente após o jogo
              </li>
            </ul>
            <!-- Status -->
            <div class="flex justify-center">
              <button
                v-if="isSupported && permission !== 'granted'"
                type="button"
                :disabled="pushLoading || permission === 'denied'"
                class="flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container-high px-6 py-3 font-label-caps text-label-caps text-on-surface transition-all hover:bg-surface-bright active:scale-[0.98] disabled:opacity-50"
                @click="handleEnablePush"
              >
                <span class="material-symbols-outlined text-[18px]">notifications</span>
                {{ pushLoading ? 'ATIVANDO...' : 'ATIVAR NOTIFICAÇÕES' }}
              </button>
              <p
                v-if="permission === 'granted'"
                class="inline-flex items-center gap-2 rounded-full bg-primary/10 border border-primary/20 px-4 py-2 font-label-caps text-label-caps text-primary"
              >
                <span
                  class="material-symbols-outlined text-[18px]"
                  style="font-variation-settings: 'FILL' 1"
                >check_circle</span>
                Notificações ativadas
              </p>
              <p
                v-else-if="permission === 'denied'"
                class="font-body-sm text-body-sm text-on-surface-variant text-center"
              >
                Permissão negada. Você pode ativar depois em Perfil.
              </p>
            </div>
            <p
              v-if="pushError"
              class="text-center font-body-sm text-body-sm text-error"
            >
              {{ pushError }}
            </p>
          </div>
        </div>
      </OnboardingSlide>
      </div>
      </Transition>
    </main>

    <!-- Footer -->
    <footer class="shrink-0 space-y-4 px-margin-mobile pb-[max(1.5rem,env(safe-area-inset-bottom))]">
      <OnboardingDots :total="TOTAL_SLIDES" :current="currentSlide" />

      <button
        v-if="currentSlide < TOTAL_SLIDES - 1"
        type="button"
        class="flex h-14 w-full items-center justify-center gap-2 rounded-xl bg-primary font-label-caps text-label-caps uppercase tracking-wider text-on-primary transition-all hover:brightness-110 active:scale-[0.98]"
        @click="nextSlide"
      >
        PRÓXIMO
        <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
      </button>

      <button
        v-else
        type="button"
        :disabled="finishing"
        class="flex h-14 w-full items-center justify-center rounded-xl bg-primary font-label-caps text-label-caps uppercase tracking-wider text-on-primary transition-all hover:brightness-110 active:scale-[0.98] disabled:opacity-60"
        @click="handleFinish"
      >
        {{ finishing ? 'CARREGANDO...' : 'COMEÇAR AGORA' }}
      </button>
    </footer>
  </div>
</template>
