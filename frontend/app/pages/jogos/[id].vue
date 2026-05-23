<script setup lang="ts">
import type { NavigationGuardNext } from 'vue-router'
import type { GroupPrediction, Match, MatchDetailResponse, UserPrediction } from '~/types/match'
import { flagUrl, isMatchTbd } from '~/types/match'
import MatchLocked from '~/components/MatchLocked.vue'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const config = useRuntimeConfig()
const apiUrl = config.public.apiUrl as string
const { user } = useAuth()
const { saving, saveError, savePrediction } = usePredictions()

const matchId = computed(() => String(route.params.id))

const match = ref<Match | null>(null)
const userPrediction = ref<UserPrediction | null>(null)
const groupPredictions = ref<GroupPrediction[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const homeScore = ref(0)
const awayScore = ref(0)

const localStatus = ref<Match['status']>('SCHEDULED')

watch(match, (m) => {
  if (!m) return
  localStatus.value = m.status
}, { immediate: true })

const isTbd = computed(() => (match.value ? isMatchTbd(match.value) : false))
const isInactive = computed(() =>
  match.value ? ['POSTPONED', 'CANCELLED'].includes(match.value.status) : false,
)

const kickoffPassed = computed(() => {
  if (!match.value) return false
  return new Date(match.value.starts_at).getTime() <= Date.now()
})

const isLocked = computed(() =>
  kickoffPassed.value
  || localStatus.value === 'LIVE'
  || localStatus.value === 'FINISHED'
  || isInactive.value,
)

const canPredict = computed(() =>
  match.value
  && !isLocked.value
  && !isTbd.value
  && localStatus.value === 'SCHEDULED',
)

const KNOCKOUT_LABELS: Record<string, string> = {
  LAST_32: 'Rodada de 32',
  LAST_16: 'Oitavas',
  QUARTER_FINALS: 'Quartas',
  SEMI_FINALS: 'Semifinal',
  THIRD_PLACE: '3º Lugar',
  FINAL: 'Final',
}

const metaLabel = computed(() => {
  if (!match.value) return ''
  if (match.value.group_name) {
    return match.value.stage === 'REGULAR_SEASON'
      ? `Rodada ${match.value.group_name}`
      : `Grupo ${match.value.group_name}`
  }
  return KNOCKOUT_LABELS[match.value.stage] ?? ''
})

const dateTimeLabel = computed(() => {
  if (!match.value) return ''
  const d = new Date(match.value.starts_at)
  const date = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: 'short',
    timeZone: 'America/Sao_Paulo',
  }).format(d).toUpperCase().replace('.', '')
  const time = new Intl.DateTimeFormat('pt-BR', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'America/Sao_Paulo',
  }).format(d)
  return `${date}, ${time}`
})

const pageTitle = computed(() => {
  if (!match.value) return 'Partida'
  const home = match.value.home_team ?? '?'
  const away = match.value.away_team ?? '?'
  return `${home} × ${away}`
})

const homeFlagSrc = computed(() => flagUrl(match.value?.home_flag))
const awayFlagSrc = computed(() => flagUrl(match.value?.away_flag))

const lockSubtitle = computed(() => {
  if (localStatus.value === 'LIVE') return 'O jogo está em andamento'
  if (localStatus.value === 'FINISHED') return 'Partida encerrada'
  if (isInactive.value) return 'Palpites indisponíveis para este jogo'
  if (kickoffPassed.value) return 'O jogo já começou'
  return undefined
})

const noPredictionMessage = computed(() => {
  if (userPrediction.value) return null
  if (isLocked.value) return 'Você não palpitou neste jogo'
  return null
})

const PREVIEW_COUNT = 3

const shuffledOthers = ref<GroupPrediction[]>([])

watch(groupPredictions, (preds) => {
  const others = preds.filter(p => p.user_id !== user.value?.id)
  const arr = [...others]
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    const tmp = arr[i]!; arr[i] = arr[j]!; arr[j] = tmp
  }
  shuffledOthers.value = arr
}, { immediate: true, deep: true })

const previewPredictions = computed(() => {
  const myPred = groupPredictions.value.find(p => p.user_id === user.value?.id)
  const others = shuffledOthers.value.slice(0, PREVIEW_COUNT)
  return myPred ? [myPred, ...others] : others
})

const hiddenCount = computed(() => {
  const othersTotal = groupPredictions.value.filter(p => p.user_id !== user.value?.id).length
  return Math.max(0, othersTotal - PREVIEW_COUNT)
})

const toastVisible = ref(false)
const toastMessage = ref('')
let toastTimer: ReturnType<typeof setTimeout> | null = null

function showToast(message: string) {
  if (toastTimer) clearTimeout(toastTimer)
  toastMessage.value = message
  toastVisible.value = true
  toastTimer = setTimeout(() => { toastVisible.value = false }, 4500)
}

const showUnsavedDialog = ref(false)
let pendingNavCallback: NavigationGuardNext | null = null

const hasUnsavedChanges = computed(() => {
  if (!canPredict.value) return false
  const savedHome = userPrediction.value?.home_score ?? 0
  const savedAway = userPrediction.value?.away_score ?? 0
  return homeScore.value !== savedHome || awayScore.value !== savedAway
})

onBeforeRouteLeave((_, __, next) => {
  if (!hasUnsavedChanges.value) return next()
  pendingNavCallback = next
  showUnsavedDialog.value = true
})

function confirmLeave() {
  showUnsavedDialog.value = false
  pendingNavCallback?.()
  pendingNavCallback = null
}

function cancelLeave() {
  showUnsavedDialog.value = false
  pendingNavCallback?.(false)
  pendingNavCallback = null
}


function applyPrediction(pred: UserPrediction | null) {
  userPrediction.value = pred
  homeScore.value = pred?.home_score ?? 0
  awayScore.value = pred?.away_score ?? 0
}

async function fetchMatchDetail() {
  loading.value = true
  loadError.value = null

  try {
    const res = await $fetch<MatchDetailResponse>(`/api/matches/${matchId.value}`, {
      baseURL: apiUrl,
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    match.value = res.data
    applyPrediction(res.user_prediction)
    groupPredictions.value = res.group_predictions ?? []
    localStatus.value = res.data.status
  } catch (err: unknown) {
    const status = (err as { statusCode?: number })?.statusCode
    loadError.value = status === 404
      ? 'Jogo não encontrado.'
      : 'Não foi possível carregar a partida.'
    match.value = null
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  if (!match.value || !canPredict.value) return

  const wasUpdate = !!userPrediction.value
  const saved = await savePrediction(match.value.id, homeScore.value, awayScore.value)
  if (!saved) return

  applyPrediction(saved)
  showToast(wasUpdate ? 'Palpite atualizado!' : 'Palpite salvo!')

  const currentUserId = user.value?.id ?? saved.user_id
  if (!currentUserId || !user.value) return

  const idx = groupPredictions.value.findIndex((p) => p.user_id === currentUserId)
  const entry: GroupPrediction = {
    ...saved,
    user_id: currentUserId,
    user: {
      id: user.value!.id,
      name: user.value!.name,
      avatar_url: user.value!.avatar_url ?? null,
    },
  }
  if (idx >= 0) {
    groupPredictions.value[idx] = entry
  } else {
    groupPredictions.value.push(entry)
  }
}

function handleKickoff() {
  if (localStatus.value === 'SCHEDULED') localStatus.value = 'LIVE'
}

onMounted(() => {
  fetchMatchDetail()
})

watch(matchId, () => {
  fetchMatchDetail()
})
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <UiSubPageHeader title="PALPITE" back-to="/jogos">
      <span
        class="material-symbols-outlined text-primary"
        style="font-variation-settings: 'FILL' 1"
      >sports_soccer</span>
    </UiSubPageHeader>

    <!-- Loading -->
    <p
      v-if="loading"
      class="px-margin py-8 font-body-lg text-body-lg text-on-surface-variant"
    >
      Carregando partida…
    </p>

    <!-- Error -->
    <div
      v-else-if="loadError"
      class="px-margin py-8 space-y-4"
    >
      <p class="font-body-lg text-body-lg text-error">
        {{ loadError }}
      </p>
      <button
        type="button"
        class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container py-3 font-label-caps text-label-caps uppercase text-on-surface transition-colors hover:bg-surface-container-high"
        @click="fetchMatchDetail"
      >
        <span class="material-symbols-outlined text-[18px]">refresh</span>
        TENTAR NOVAMENTE
      </button>
    </div>

    <!-- Match content -->
    <template v-else-if="match">
      <div class="pb-8 pt-4 px-margin-mobile space-y-4 max-w-xl mx-auto">

        <!-- Unified match card: teams + score -->
        <component
          :is="isLocked ? MatchLocked : 'div'"
          :subtitle="isLocked ? lockSubtitle : undefined"
        >
          <div class="glass-card rounded-2xl overflow-hidden">
            <!-- Date/time + status row -->
            <div class="flex flex-col items-center gap-1 border-b border-white/5 px-4 py-3">
              <span class="font-label-caps text-label-caps text-on-surface-variant">{{ dateTimeLabel }}</span>
              <div class="flex items-center gap-2">
                <MatchStatusBadge :status="localStatus" :match="match" />
                <span v-if="metaLabel" class="font-label-caps text-label-caps text-on-surface-variant/60">{{ metaLabel }}</span>
              </div>
            </div>

            <!-- Teams row -->
            <div class="flex items-center justify-around px-4 pt-5 pb-3">
              <div class="flex flex-col items-center gap-1.5">
                <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border-2 border-white/10 bg-surface-container-highest">
                  <img
                    v-if="homeFlagSrc && !isTbd"
                    :src="homeFlagSrc"
                    :alt="`Bandeira ${match.home_team}`"
                    width="40" height="40" loading="eager"
                    class="h-10 w-10 object-contain"
                  >
                  <span v-else class="font-label-caps text-label-caps text-on-surface-variant">?</span>
                </div>
                <span class="max-w-[90px] truncate text-center text-[13px] font-bold uppercase font-sans">
                  {{ match.home_team ?? '—' }}
                </span>
              </div>

              <span class="text-[15px] font-bold text-on-surface-variant/30">VS</span>

              <div class="flex flex-col items-center gap-1.5">
                <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border-2 border-white/10 bg-surface-container-highest">
                  <img
                    v-if="awayFlagSrc && !isTbd"
                    :src="awayFlagSrc"
                    :alt="`Bandeira ${match.away_team}`"
                    width="40" height="40" loading="eager"
                    class="h-10 w-10 object-contain"
                  >
                  <span v-else class="font-label-caps text-label-caps text-on-surface-variant">?</span>
                </div>
                <span class="max-w-[90px] truncate text-center text-[13px] font-bold uppercase font-sans">
                  {{ match.away_team ?? '—' }}
                </span>
              </div>
            </div>

            <!-- Score row -->
            <div v-if="!isTbd" class="flex items-center justify-around px-4 pb-6 pt-2">
              <!-- Home: botões à esquerda, número à direita -->
              <div class="flex items-center gap-2">
                <div class="flex flex-col gap-1.5">
                  <button
                    type="button"
                    :disabled="isLocked || homeScore >= 20"
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-container-high border border-white/10 transition-[background-color,transform] active:scale-95 disabled:opacity-40"
                    aria-label="Aumentar gols do time mandante"
                    @click="homeScore < 20 && homeScore++"
                  >
                    <span class="material-symbols-outlined text-[20px] text-primary">add</span>
                  </button>
                  <button
                    type="button"
                    :disabled="isLocked || homeScore <= 0"
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-container-high border border-white/10 transition-[background-color,transform] active:scale-95 disabled:opacity-40"
                    aria-label="Diminuir gols do time mandante"
                    @click="homeScore > 0 && homeScore--"
                  >
                    <span class="material-symbols-outlined text-[20px] text-primary">remove</span>
                  </button>
                </div>
                <div
                  class="flex h-16 w-16 items-center justify-center rounded-full border-2 bg-surface-container-lowest font-display-lg text-display-lg text-primary transition-colors"
                  :class="isLocked ? 'border-white/10 opacity-60' : 'border-primary/40'"
                >
                  {{ homeScore }}
                </div>
              </div>

              <!-- Away: número à esquerda, botões à direita -->
              <div class="flex items-center gap-2">
                <div
                  class="flex h-16 w-16 items-center justify-center rounded-full border-2 bg-surface-container-lowest font-display-lg text-display-lg text-primary transition-colors"
                  :class="isLocked ? 'border-white/10 opacity-60' : 'border-primary/40'"
                >
                  {{ awayScore }}
                </div>
                <div class="flex flex-col gap-1.5">
                  <button
                    type="button"
                    :disabled="isLocked || awayScore >= 20"
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-container-high border border-white/10 transition-[background-color,transform] active:scale-95 disabled:opacity-40"
                    aria-label="Aumentar gols do time visitante"
                    @click="awayScore < 20 && awayScore++"
                  >
                    <span class="material-symbols-outlined text-[20px] text-primary">add</span>
                  </button>
                  <button
                    type="button"
                    :disabled="isLocked || awayScore <= 0"
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-container-high border border-white/10 transition-[background-color,transform] active:scale-95 disabled:opacity-40"
                    aria-label="Diminuir gols do time visitante"
                    @click="awayScore > 0 && awayScore--"
                  >
                    <span class="material-symbols-outlined text-[20px] text-primary">remove</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- TBD notice -->
            <p
              v-else
              class="border-t border-white/5 px-4 py-4 text-center font-body-sm text-body-sm text-on-surface-variant"
            >
              Times ainda não definidos — palpite disponível quando confirmados.
            </p>
          </div>
        </component>

        <!-- Can predict actions -->
        <template v-if="canPredict && !isTbd">
          <!-- Save button -->
          <button
            type="button"
            :disabled="saving"
            class="font-label-caps text-label-caps w-full rounded-xl py-4 uppercase tracking-wider transition-[background-color,box-shadow,transform] active:scale-[0.98] disabled:opacity-60 bg-primary text-on-primary shadow-[0_0_20px_rgba(101,223,118,0.3)] hover:shadow-[0_0_30px_rgba(101,223,118,0.5)]"
            @click="handleSave"
          >
            {{ saving ? 'SALVANDO…' : (userPrediction ? 'ATUALIZAR PALPITE' : 'SALVAR PALPITE') }}
          </button>

          <div class="glass-card divide-y divide-white/5 rounded-xl text-sm">
            <div class="flex items-center justify-between px-4 py-3">
              <span class="font-body-sm text-body-sm text-on-surface-variant">Placar exato (ex: 2–1 = 2–1)</span>
              <span class="font-label-caps text-label-caps text-secondary-container">+3 pts</span>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
              <span class="font-body-sm text-body-sm text-on-surface-variant">Vencedor ou empate correto</span>
              <span class="font-label-caps text-label-caps text-primary">+1 pt</span>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
              <span class="font-body-sm text-body-sm text-on-surface-variant">Resultado errado</span>
              <span class="font-label-caps text-label-caps text-on-surface-variant">0 pts</span>
            </div>
          </div>

          <p v-if="saveError" class="text-center font-body-sm text-body-sm text-error">
            {{ saveError }}
          </p>
        </template>

        <!-- Locked state indicator -->
        <template v-else-if="isLocked">
          <p
            v-if="noPredictionMessage"
            class="text-center font-body-sm text-body-sm text-on-surface-variant"
          >
            {{ noPredictionMessage }}
          </p>
        </template>

        <!-- Group predictions -->
        <section class="border-t border-white/5 pt-stack-lg">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
              Palpites da galera
            </h3>
            <span
              v-if="groupPredictions.length > 0"
              class="font-label-caps text-[10px] text-on-surface-variant/40"
            >
              {{ groupPredictions.length }} palpite{{ groupPredictions.length !== 1 ? 's' : '' }}
            </span>
          </div>

          <p
            v-if="groupPredictions.length === 0"
            class="font-body-sm text-body-sm text-on-surface-variant/60"
          >
            Nenhum palpite da galera ainda.
          </p>

          <div v-else class="glass-card divide-y divide-white/[0.04] overflow-hidden rounded-xl">
            <div
              v-for="prediction in previewPredictions"
              :key="prediction.id"
              class="flex items-center gap-3 px-4 py-3 transition-colors"
              :class="prediction.user_id === user?.id ? 'bg-primary/[0.05]' : ''"
            >
              <!-- Avatar with "you" ring -->
              <div class="relative shrink-0">
                <UiAvatar
                  :name="prediction.user.name"
                  :src="prediction.user.avatar_url"
                  size="sm"
                />
                <span
                  v-if="prediction.user_id === user?.id"
                  class="absolute -inset-[3px] rounded-full border-2 border-primary/50"
                />
              </div>

              <!-- Name -->
              <div class="min-w-0 flex-1">
                <p class="truncate font-body-sm text-body-sm leading-tight text-on-surface">
                  {{ prediction.user.name }}
                </p>
                <span
                  v-if="prediction.user_id === user?.id"
                  class="font-label-caps text-[9px] uppercase tracking-widest text-primary"
                >você</span>
              </div>

              <!-- Score -->
              <span
                class="shrink-0 font-mono text-[18px] font-bold tabular-nums leading-none"
                :class="prediction.user_id === user?.id ? 'text-primary' : 'text-on-surface/70'"
              >{{ prediction.home_score }}<span class="mx-0.5 text-on-surface-variant/25">×</span>{{ prediction.away_score }}</span>
            </div>

            <!-- Hidden count -->
            <div v-if="hiddenCount > 0" class="flex items-center gap-2.5 px-4 py-3">
              <div class="flex -space-x-1.5">
                <div
                  v-for="i in Math.min(hiddenCount, 3)"
                  :key="i"
                  class="h-5 w-5 rounded-full border-2 border-surface-container bg-surface-container-highest"
                />
              </div>
              <span class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/40">
                +{{ hiddenCount }} outros palpites
              </span>
            </div>
          </div>
        </section>
      </div>
    </template>

    <!-- Unsaved changes guard -->
    <UiConfirmDialog
      :open="showUnsavedDialog"
      title="Palpite não salvo"
      message="Você alterou o palpite mas não salvou. Deseja sair e perder as alterações?"
      confirm-label="Sair sem salvar"
      cancel-label="Ficar e salvar"
      danger
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />

    <!-- Toast (inside root element to keep single-root for page transitions) -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div
          v-if="toastVisible"
          class="fixed bottom-24 left-1/2 z-50 -translate-x-1/2 whitespace-nowrap"
        >
          <div class="neon-glow-green flex items-center gap-2.5 rounded-full border border-primary/25 bg-surface-container-high px-4 py-2.5 shadow-lg shadow-black/40">
            <span
              class="material-symbols-outlined text-[18px] leading-none text-primary"
              style="font-variation-settings: 'FILL' 1"
            >check_circle</span>
            <span class="font-label-caps text-label-caps text-on-surface">{{ toastMessage }}</span>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
