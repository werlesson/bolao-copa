<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { user, logout } = useAuth()
const {
  statCardClass,
  stats,
  loading,
  error,
  savingName,
  nameError,
  fetchStats,
  updateName,
} = useProfile()
const {
  permission,
  loading: pushLoading,
  error: pushError,
  isSupported,
  subscribeAndSave,
  unsubscribeAndRemove,
  syncPermission,
} = usePushNotifications()

const nameDraft = ref('')
const editingName = ref(false)
const nameInputRef = ref<HTMLInputElement | null>(null)
const showLogoutDialog = ref(false)
const loggingOut = ref(false)
const pushEnabled = ref(false)
const pwa = usePWA()
const installingPwa = ref(false)

const isIosSafari = computed(() => {
  if (!import.meta.client) return false
  const ua = navigator.userAgent
  return /iPhone|iPad|iPod/.test(ua) && !/CriOS|FxiOS/.test(ua)
})

const showInstallApp = computed(() => {
  if (!pwa || pwa.isPWAInstalled) return false
  return Boolean(pwa.showInstallPrompt) || isIosSafari.value
})

const positionLabel = computed(() => {
  const pos = stats.value?.position
  if (pos == null) return '—'
  return `${pos}º`
})

const accuracyLabel = computed(() => {
  const acc = stats.value?.accuracy_percent
  if (acc == null) return '—'
  return `${acc}%`
})

const phaseChartPoints = computed(() => {
  const phases = stats.value?.phase_points
  if (!phases || phases.length === 0) return null
  const max = Math.max(...phases.map(p => p.points), 1)
  const w = 240, h = 80, pad = 8
  return phases.map((p, i) => {
    const x = pad + (i / Math.max(phases.length - 1, 1)) * (w - pad * 2)
    const y = h - pad - (p.points / max) * (h - pad * 2)
    return { x, y, label: p.label, points: p.points }
  })
})

const phasePolyline = computed(() => {
  const pts = phaseChartPoints.value
  if (!pts) return ''
  return pts.map(p => `${p.x},${p.y}`).join(' ')
})

const pushDenied = computed(() => isSupported.value && permission.value === 'denied')

watch(user, value => {
  if (value && !editingName.value) nameDraft.value = value.name
}, { immediate: true })

watch(
  () => [user.value?.has_push, permission.value] as const,
  ([hasPush, perm]) => {
    pushEnabled.value = Boolean(hasPush) && perm === 'granted'
  },
  { immediate: true },
)

onMounted(async () => {
  document.addEventListener('visibilitychange', syncPermission)
  if (user.value?.id) await fetchStats(user.value.id)
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', syncPermission)
})

function startEditingName() {
  if (user.value) nameDraft.value = user.value.name
  editingName.value = true
  nextTick(() => nameInputRef.value?.focus())
}

function cancelNameEdit() {
  editingName.value = false
  if (user.value) nameDraft.value = user.value.name
}

async function handleSaveName() {
  const ok = await updateName(nameDraft.value)
  if (ok) editingName.value = false
}

async function handleInstallApp() {
  if (!pwa?.showInstallPrompt || installingPwa.value) return
  installingPwa.value = true
  try {
    await pwa.install()
  }
  finally {
    installingPwa.value = false
  }
}

async function handlePushToggle() {
  if (pushLoading.value || pushDenied.value) return
  if (pushEnabled.value) {
    const ok = await unsubscribeAndRemove()
    if (ok) pushEnabled.value = false
    return
  }
  const ok = await subscribeAndSave()
  if (ok) pushEnabled.value = true
}

async function handleLogout() {
  loggingOut.value = true
  await logout()
}
</script>

<template>
  <div v-if="user" class="pb-6">
    <UiSubPageHeader title="PERFIL" />

    <!-- Hero -->
    <section class="diagonal-grad px-margin-mobile pb-6 pt-6">
      <div class="flex flex-col items-center gap-4 text-center">

        <!-- Avatar -->
        <div class="relative">
          <div class="h-24 w-24 overflow-hidden rounded-full border-2 border-primary/50 shadow-[0_0_28px_rgba(101,223,118,0.25)]">
            <img
              v-if="user.avatar_url"
              :src="user.avatar_url"
              :alt="user.name"
              width="96"
              height="96"
              class="h-full w-full object-cover"
              referrerpolicy="no-referrer"
            >
            <UiAvatar v-else :name="user.name" size="xl" class="!h-full !w-full" />
          </div>
          <button
            v-if="!editingName"
            type="button"
            aria-label="Editar nome"
            class="absolute bottom-0 right-0 rounded-full border-2 border-background bg-primary p-1 text-on-primary transition-all hover:brightness-110 active:scale-95"
            @click="startEditingName"
          >
            <span class="material-symbols-outlined text-[15px]">edit</span>
          </button>
        </div>

        <!-- Name: static or edit mode -->
        <div class="w-full max-w-xs">
          <template v-if="!editingName">
            <h2 class="font-headline-lg-mobile text-headline-lg-mobile text-on-surface">
              {{ user.name }}
            </h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant/70">
              {{ user.email }}
            </p>
          </template>
          <template v-else>
            <div class="space-y-2">
              <input
                ref="nameInputRef"
                v-model="nameDraft"
                type="text"
                maxlength="100"
                autocomplete="name"
                class="w-full rounded-t-lg border-b-2 border-primary bg-surface-container-low/60 px-3 py-2 text-center font-title-md text-title-md text-on-surface outline-none backdrop-blur-sm transition-colors"
                :disabled="savingName"
                @keydown.enter="handleSaveName"
                @keydown.escape="cancelNameEdit"
              >
              <p v-if="nameError" class="font-body-sm text-body-sm text-error">
                {{ nameError }}
              </p>
              <div class="flex gap-2">
                <button
                  type="button"
                  class="flex-1 rounded-lg border border-white/15 py-2 font-label-caps text-label-caps uppercase text-on-surface-variant transition-all hover:bg-white/5 active:scale-[0.98]"
                  @click="cancelNameEdit"
                >
                  CANCELAR
                </button>
                <button
                  type="button"
                  class="flex-1 rounded-lg bg-primary py-2 font-label-caps text-label-caps uppercase text-on-primary transition-all hover:brightness-110 active:scale-[0.98] disabled:opacity-60"
                  :disabled="savingName || nameDraft.trim().length < 2"
                  @click="handleSaveName"
                >
                  {{ savingName ? '…' : 'SALVAR' }}
                </button>
              </div>
            </div>
          </template>
        </div>

        <!-- Key stats -->
        <div class="grid w-full max-w-xs grid-cols-2 gap-3">
          <div class="glass-card flex flex-col items-center gap-1 rounded-xl p-4">
            <span class="font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">RANKING</span>
            <span class="font-headline-lg text-headline-lg leading-none text-primary">
              {{ loading ? '—' : positionLabel }}
            </span>
          </div>
          <div class="glass-card flex flex-col items-center gap-1 rounded-xl p-4">
            <span class="font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">PONTOS</span>
            <span class="font-headline-lg text-headline-lg leading-none text-secondary-container">
              {{ loading ? '—' : (stats?.total_points ?? '—') }}
            </span>
            <span class="font-label-caps text-label-caps text-secondary-container/70">PTS</span>
          </div>
        </div>

      </div>
    </section>

    <!-- Body -->
    <div class="space-y-3 px-margin-mobile pt-4">

      <p
        v-if="loading"
        class="text-center font-body-lg text-body-lg text-on-surface-variant"
      >
        Carregando estatísticas…
      </p>

      <div v-else-if="error" class="space-y-3">
        <p class="text-center font-body-lg text-body-lg text-error">
          {{ error }}
        </p>
        <button
          type="button"
          class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container py-3 font-label-caps text-label-caps uppercase text-on-surface transition-colors hover:bg-surface-container-high"
          @click="user?.id && fetchStats(user.id)"
        >
          <span class="material-symbols-outlined text-[18px]">refresh</span>
          TENTAR NOVAMENTE
        </button>
      </div>

      <template v-else-if="stats">
        <!-- Secondary stats grid -->
        <section class="grid grid-cols-3 gap-2">
          <div :class="[statCardClass, 'flex flex-col items-center gap-1 p-3']">
            <span
              class="material-symbols-outlined text-[18px] text-on-surface-variant/50"
              style="font-variation-settings: 'FILL' 0, 'wght' 300"
            >sports_soccer</span>
            <span class="font-label-caps text-label-caps text-on-surface-variant">PALPITES</span>
            <span class="mt-0.5 text-[22px] font-bold leading-none text-on-surface">{{ stats.total_predictions }}</span>
          </div>
          <div :class="[statCardClass, 'flex flex-col items-center gap-1 p-3']">
            <span
              class="material-symbols-outlined text-[18px] text-secondary-container/60"
              style="font-variation-settings: 'FILL' 1, 'wght' 400"
            >check_circle</span>
            <span class="font-label-caps text-label-caps text-on-surface-variant">EXATOS</span>
            <span class="mt-0.5 text-[22px] font-bold leading-none text-secondary-container">{{ stats.exact_scores }}</span>
          </div>
          <div :class="[statCardClass, 'flex flex-col items-center gap-1 p-3']">
            <span
              class="material-symbols-outlined text-[18px] text-primary/60"
              style="font-variation-settings: 'FILL' 0, 'wght' 300"
            >percent</span>
            <span class="font-label-caps text-label-caps text-on-surface-variant">APROVEIT.</span>
            <span class="mt-0.5 text-[22px] font-bold leading-none text-primary">{{ accuracyLabel }}</span>
          </div>
        </section>

        <!-- Phase performance chart -->
        <section :class="[statCardClass, 'overflow-hidden']">
          <div class="flex items-center gap-2 border-b border-white/5 bg-surface-container-low px-4 py-3">
            <span class="material-symbols-outlined text-[15px] text-primary/70">trending_up</span>
            <h3 class="font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
              DESEMPENHO POR FASE
            </h3>
          </div>
          <div
            v-if="stats.phase_points.length === 0"
            class="px-4 py-5 text-center font-body-sm text-body-sm text-on-surface-variant/60"
          >
            Nenhum ponto registrado por fase ainda.
          </div>
          <div v-else class="px-4 pb-4 pt-2">
            <svg
              v-if="phaseChartPoints"
              viewBox="0 0 240 96"
              class="w-full"
              aria-hidden="true"
            >
              <line x1="0" y1="80" x2="240" y2="80" stroke="rgba(255,255,255,0.06)" stroke-width="1" />
              <line x1="0" y1="48" x2="240" y2="48" stroke="rgba(255,255,255,0.04)" stroke-width="1" />
              <line x1="0" y1="16" x2="240" y2="16" stroke="rgba(255,255,255,0.04)" stroke-width="1" />
              <defs>
                <linearGradient id="greenFade" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#65df76" stop-opacity="0.25" />
                  <stop offset="100%" stop-color="#65df76" stop-opacity="0" />
                </linearGradient>
              </defs>
              <polygon
                v-if="phaseChartPoints.length > 1"
                :points="`${phaseChartPoints[0]!.x},80 ${phasePolyline} ${phaseChartPoints[phaseChartPoints.length - 1]!.x},80`"
                fill="url(#greenFade)"
              />
              <polyline
                :points="phasePolyline"
                fill="none"
                stroke="#65df76"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
              />
              <circle
                v-for="pt in phaseChartPoints"
                :key="pt.label"
                :cx="pt.x"
                :cy="pt.y"
                r="3.5"
                fill="#65df76"
              />
              <text
                v-for="pt in phaseChartPoints"
                :key="`lbl-${pt.label}`"
                :x="pt.x"
                :y="pt.y - 7"
                text-anchor="middle"
                fill="#bdcab9"
                font-size="8"
                font-family="'Archivo Narrow', sans-serif"
              >{{ pt.points }}</text>
            </svg>
            <div class="mt-1 flex justify-between">
              <span
                v-for="pt in phaseChartPoints"
                :key="pt.label"
                class="font-label-caps text-label-caps text-on-surface-variant"
                style="font-size: 10px;"
              >{{ pt.label }}</span>
            </div>
          </div>
        </section>
      </template>

      <!-- Settings section -->
      <section class="glass-card overflow-hidden rounded-xl">
        <div class="border-b border-white/5 px-4 py-3">
          <h3 class="font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
            CONFIGURAÇÕES
          </h3>
        </div>

        <!-- Install PWA row -->
        <div
          v-if="showInstallApp"
          class="flex items-center gap-3 border-b border-white/5 px-4 py-4"
        >
          <span
            class="material-symbols-outlined shrink-0 text-[22px] text-primary"
            style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24"
          >install_mobile</span>
          <div class="min-w-0 flex-1">
            <p class="font-body-lg text-body-lg text-on-surface">Instalar app</p>
            <p class="mt-0.5 font-label-caps text-[11px] text-on-surface-variant/50">
              <template v-if="pwa?.showInstallPrompt">
                Acesso rápido na tela inicial
              </template>
              <template v-else>
                No Safari: Compartilhar → Adicionar à Tela de Início
              </template>
            </p>
          </div>
          <button
            v-if="pwa?.showInstallPrompt"
            type="button"
            :disabled="installingPwa"
            class="shrink-0 rounded-lg bg-primary px-4 py-2 font-label-caps text-label-caps text-on-primary transition-all hover:brightness-110 active:scale-[0.98] disabled:opacity-60"
            @click="handleInstallApp"
          >
            {{ installingPwa ? '...' : 'INSTALAR' }}
          </button>
        </div>

        <!-- Push notifications row -->
        <div class="flex items-center gap-3 border-b border-white/5 px-4 py-4">
          <span
            class="material-symbols-outlined shrink-0 text-[22px] transition-colors"
            :class="pushEnabled ? 'text-primary' : 'text-on-surface-variant/50'"
            style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24"
          >notifications</span>

          <div class="min-w-0 flex-1">
            <p class="font-body-lg text-body-lg text-on-surface">Notificações push</p>
            <p
              v-if="!isSupported"
              class="mt-0.5 font-label-caps text-[11px] text-on-surface-variant/50"
            >
              Não suportado neste navegador
            </p>
            <p
              v-else-if="pushDenied"
              class="mt-0.5 font-label-caps text-[11px] text-error/80"
            >
              Bloqueadas. Vá em Configurações do navegador → Notificações para ativar.
            </p>
            <p
              v-else-if="pushError"
              class="mt-0.5 font-label-caps text-[11px] text-error"
            >
              {{ pushError }}
            </p>
            <p
              v-else
              class="mt-0.5 font-label-caps text-[11px] text-on-surface-variant/50"
            >
              {{ pushEnabled ? 'Ativado' : 'Desativado' }}
            </p>
          </div>

          <!-- Blocked indicator -->
          <span
            v-if="isSupported && pushDenied"
            class="material-symbols-outlined shrink-0 text-[20px] text-error/50"
          >block</span>

          <!-- Toggle -->
          <button
            v-else-if="isSupported"
            type="button"
            role="switch"
            :aria-checked="pushEnabled"
            :disabled="pushLoading"
            class="relative flex h-7 w-12 shrink-0 items-center rounded-full px-1 transition-colors duration-200 disabled:opacity-50"
            :class="pushEnabled ? 'bg-primary' : 'bg-surface-container-highest'"
            @click="handlePushToggle"
          >
            <span
              v-if="pushLoading"
              class="absolute inset-0 flex items-center justify-center"
            >
              <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-on-surface-variant/30 border-t-on-surface-variant" />
            </span>
            <span
              v-else
              class="block h-5 w-5 rounded-full shadow transition-transform duration-200"
              :class="pushEnabled ? 'translate-x-6 bg-on-primary' : 'translate-x-0 bg-on-surface-variant/60'"
            />
          </button>
        </div>

        <!-- Tutorial row -->
        <NuxtLink
          to="/onboarding?replay=1"
          class="flex items-center gap-3 border-b border-white/5 px-4 py-4 transition-colors hover:bg-white/5 active:bg-white/10"
        >
          <span class="material-symbols-outlined shrink-0 text-[22px] text-on-surface-variant/50">school</span>
          <span class="flex-1 font-body-lg text-body-lg text-on-surface">Ver tutorial</span>
          <span class="material-symbols-outlined text-[18px] text-on-surface-variant/25">chevron_right</span>
        </NuxtLink>

        <!-- Logout row -->
        <button
          type="button"
          :disabled="loggingOut"
          class="flex w-full items-center gap-3 border-t border-white/10 px-4 py-4 transition-colors hover:bg-error/5 active:bg-error/10 disabled:opacity-60"
          @click="showLogoutDialog = true"
        >
          <span class="material-symbols-outlined shrink-0 text-[22px] text-error/60">logout</span>
          <span class="flex-1 text-left font-body-lg text-body-lg text-error/90">
            {{ loggingOut ? 'Saindo…' : 'Sair da conta' }}
          </span>
        </button>
      </section>

    </div>

    <UiConfirmDialog
      :open="showLogoutDialog"
      title="Sair da conta"
      message="Tem certeza que deseja sair? Você precisará fazer login novamente para acessar o BolãoCopa."
      confirm-label="Sair"
      cancel-label="Cancelar"
      danger
      @confirm="handleLogout"
      @cancel="showLogoutDialog = false"
    />
  </div>
</template>
