<script setup lang="ts">
import type { Match } from '~/types/match'
import { flagUrl } from '~/types/match'
import { dateKey, groupMatchesByDate } from '~/composables/useMatches'
import { matchPollIntervalMs } from '~/utils/matchPolling'

definePageMeta({ middleware: 'auth' })

const { matches, loading, error, currentPhase, fetchMatches, predictionFor } = useMatches()
const showFinished = ref(false)
const showFilterSheet = ref(false)
const cookieOpts = { default: () => null as string | null, maxAge: 60 * 60 * 24 * 90 }
const viewModeCookieOpts = { default: () => 'agenda' as 'agenda' | 'encerrados', maxAge: 60 * 60 * 24 * 90 }
const viewMode = useCookie<'agenda' | 'encerrados'>('jogos-view-mode', viewModeCookieOpts)
const activeStage = useCookie<string | null>('jogos-filter-stage', cookieOpts)
const activeGroup = useCookie<string | null>('jogos-filter-group', cookieOpts)
const activeTeam = useCookie<string | null>('jogos-filter-team', cookieOpts)
const activeDate = useCookie<string | null>('jogos-filter-date', cookieOpts)
const teamSearch = ref('')

let pollTimer: ReturnType<typeof setTimeout> | null = null

async function refreshMatches() {
  const fast = matchPollIntervalMs(matches.value) <= 20_000
  await fetchMatches({ bustCache: fast })
}

function schedulePoll() {
  if (pollTimer) {
    clearTimeout(pollTimer)
    pollTimer = null
  }
  if (document.visibilityState !== 'visible') return

  const delay = matchPollIntervalMs(matches.value)
  pollTimer = setTimeout(async () => {
    await refreshMatches()
    schedulePoll()
  }, delay)
}

function startPolling() {
  schedulePoll()
}

function stopPolling() {
  if (pollTimer) {
    clearTimeout(pollTimer)
    pollTimer = null
  }
}

function handleVisibilityChange() {
  if (document.visibilityState === 'visible') {
    refreshMatches().then(() => startPolling())
  } else {
    stopPolling()
  }
}

onMounted(() => {
  refreshMatches().then(() => startPolling())
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onUnmounted(() => {
  stopPolling()
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})

const STAGE_ORDER: Record<string, number> = {
  GROUP_STAGE: 0, REGULAR_SEASON: 0,
  LAST_32: 1, LAST_16: 2, QUARTER_FINALS: 3,
  SEMI_FINALS: 4, THIRD_PLACE: 5, FINAL: 6,
}

const STAGE_LABELS: Record<string, string> = {
  GROUP_STAGE: 'Grupos', REGULAR_SEASON: 'Grupos',
  LAST_32: 'Rodada 32', LAST_16: 'Oitavas',
  QUARTER_FINALS: 'Quartas', SEMI_FINALS: 'Semifinal',
  THIRD_PLACE: '3º Lugar', FINAL: 'Final',
}

const availableStages = computed(() => {
  const seen = new Set<string>()
  for (const m of matches.value) seen.add(m.stage)
  return [...seen]
    .sort((a, b) => (STAGE_ORDER[a] ?? 99) - (STAGE_ORDER[b] ?? 99))
    .map(stage => ({ value: stage, label: STAGE_LABELS[stage] ?? stage }))
})

const isGroupStageActive = computed(() =>
  !activeStage.value
  || activeStage.value === 'GROUP_STAGE'
  || activeStage.value === 'REGULAR_SEASON',
)

const availableGroups = computed(() => {
  if (!isGroupStageActive.value) return []
  const groups = new Set<string>()
  for (const m of matches.value) {
    if (m.group_name && (m.stage === 'GROUP_STAGE' || m.stage === 'REGULAR_SEASON')) {
      groups.add(m.group_name)
    }
  }
  return [...groups].sort()
})

const availableTeams = computed(() => {
  const teamMap = new Map<string, string | null>()
  for (const m of matches.value) {
    if (m.home_team && !teamMap.has(m.home_team)) teamMap.set(m.home_team, m.home_flag)
    if (m.away_team && !teamMap.has(m.away_team)) teamMap.set(m.away_team, m.away_flag)
  }
  return [...teamMap.entries()]
    .map(([name, flag]) => ({ name, flag: flagUrl(flag) }))
    .sort((a, b) => a.name.localeCompare(b.name))
})

const filteredTeams = computed(() => {
  // Narrow to teams that appear in the active stage
  let base = availableTeams.value
  if (activeStage.value) {
    const stageTeams = new Set<string>()
    for (const m of matches.value) {
      if (m.stage === activeStage.value) {
        if (m.home_team) stageTeams.add(m.home_team)
        if (m.away_team) stageTeams.add(m.away_team)
      }
    }
    base = base.filter(t => stageTeams.has(t.name))
  }
  const q = teamSearch.value.toLowerCase().trim()
  if (!q) return base
  return base.filter(t => t.name.toLowerCase().includes(q))
})

function formatDateChip(iso: string): string {
  const date = new Date(iso)
  const today = new Date()
  const sameDay = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
  if (sameDay(date, today)) return 'Hoje'
  const tomorrow = new Date()
  tomorrow.setDate(today.getDate() + 1)
  if (sameDay(date, tomorrow)) return 'Amanhã'
  return new Intl.DateTimeFormat('pt-BR', {
    day: 'numeric', month: 'short', timeZone: 'America/Sao_Paulo',
  }).format(date).replace('.', '')
}

const availableDates = computed(() => {
  const dateMap = new Map<string, string>()
  for (const m of matches.value) {
    const key = dateKey(m.starts_at)
    if (!dateMap.has(key)) dateMap.set(key, m.starts_at)
  }
  return [...dateMap.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([key, iso]) => ({ key, iso, label: formatDateChip(iso) }))
})

const activeDateLabel = computed(() =>
  availableDates.value.find(d => d.key === activeDate.value)?.label ?? null,
)

const matchCountByDate = computed(() => {
  const counts: Record<string, number> = {}
  for (const m of matches.value) {
    if (m.status === 'POSTPONED' || m.status === 'CANCELLED') continue
    const k = dateKey(m.starts_at)
    counts[k] = (counts[k] ?? 0) + 1
  }
  return counts
})

const dateCards = computed(() => {
  const today = new Date()
  const sameDay = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear()
    && a.getMonth() === b.getMonth()
    && a.getDate() === b.getDate()

  return availableDates.value.map(({ key, iso }) => {
    const date = new Date(iso)
    const tz = 'America/Sao_Paulo'
    const isToday = sameDay(date, today)

    const weekday = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', timeZone: tz })
      .format(date).replace('.', '').toUpperCase().slice(0, 3)

    const day = new Intl.DateTimeFormat('pt-BR', { day: 'numeric', timeZone: tz }).format(date)

    const month = new Intl.DateTimeFormat('pt-BR', { month: 'short', timeZone: tz })
      .format(date).replace('.', '').toUpperCase().slice(0, 3)

    return { key, weekday, day, month, isToday, count: matchCountByDate.value[key] ?? 0 }
  })
})

function setStage(stage: string | null) {
  activeStage.value = activeStage.value === stage ? null : stage
  if (!isGroupStageActive.value) activeGroup.value = null
}

function setGroup(group: string) {
  activeGroup.value = activeGroup.value === group ? null : group
}

function setTeam(team: string) {
  activeTeam.value = activeTeam.value === team ? null : team
}

function setDate(key: string) {
  activeDate.value = activeDate.value === key ? null : key
}

function clearFilters() {
  activeStage.value = null
  activeGroup.value = null
  activeTeam.value = null
  activeDate.value = null
  teamSearch.value = ''
}

const hasActiveFilters = computed(() =>
  activeStage.value !== null || activeGroup.value !== null
  || activeTeam.value !== null || activeDate.value !== null,
)

const activeFilterCount = computed(() =>
  [activeStage.value, activeGroup.value, activeTeam.value, activeDate.value].filter(Boolean).length,
)

function applyFilters(list: Match[]): Match[] {
  return list.filter((m) => {
    if (activeStage.value && m.stage !== activeStage.value) return false
    if (activeGroup.value && m.group_name !== activeGroup.value) return false
    if (activeTeam.value && m.home_team !== activeTeam.value && m.away_team !== activeTeam.value) return false
    if (activeDate.value && dateKey(m.starts_at) !== activeDate.value) return false
    return true
  })
}

const liveMatches = computed(() =>
  applyFilters(matches.value.filter(m => m.status === 'LIVE')),
)

const scheduledMatches = computed(() =>
  applyFilters(matches.value.filter(m => m.status === 'SCHEDULED')),
)

const finishedMatches = computed(() =>
  applyFilters(matches.value.filter(m => m.status === 'FINISHED')),
)

const tailMatches = computed(() =>
  applyFilters(matches.value.filter(m => m.status === 'POSTPONED' || m.status === 'CANCELLED')),
)

const scheduledGroups = computed(() => groupMatchesByDate(scheduledMatches.value))
const finishedGroups = computed(() => groupMatchesByDate(finishedMatches.value))
const finishedGroupsReversed = computed(() =>
  [...finishedGroups.value].sort((a, b) => b.key.localeCompare(a.key)),
)

const isResultsMode = computed(() => viewMode.value === 'encerrados')

const showFinishedSection = computed(() => showFinished.value || activeDate.value !== null)

const showEmptyAgenda = computed(() =>
  !isResultsMode.value && !loading.value && !error.value && matches.value.length > 0
  && liveMatches.value.length === 0 && scheduledMatches.value.length === 0
  && finishedMatches.value.length === 0,
)

const showEmptyResults = computed(() =>
  isResultsMode.value && !loading.value && !error.value && matches.value.length > 0
  && finishedMatches.value.length === 0,
)

const resultsSummary = computed(() => {
  let totalPoints = 0
  for (const m of finishedMatches.value) {
    const pts = predictionFor(m.id)?.points_earned
    if (pts !== null && pts !== undefined) totalPoints += pts
  }
  return { totalPoints }
})

const skeletonCount = 4

const viewChipBase = 'flex-1 rounded-full py-2 font-label-caps text-[11px] uppercase tracking-widest transition-colors duration-150'
const viewChipInactive = 'text-on-surface-variant'
const viewChipActive = 'bg-primary text-on-primary shadow-sm shadow-primary/20'

const chipBase = 'rounded-full border px-3 py-1.5 font-label-caps text-[11px] uppercase tracking-widest transition-colors duration-150'
const chipInactive = 'border-white/10 text-on-surface-variant hover:border-white/20 hover:text-on-surface'
const chipActive = 'border-primary/30 bg-primary/10 text-primary'
</script>

<template>
  <div>
    <UiSubPageHeader title="JOGOS">
      <button
        type="button"
        class="relative flex h-8 w-8 items-center justify-center rounded-full transition-colors hover:bg-white/5"
        :class="hasActiveFilters ? 'text-primary' : 'text-on-surface-variant'"
        :aria-label="hasActiveFilters ? `${activeFilterCount} filtro(s) ativo(s)` : 'Filtrar jogos'"
        @click="showFilterSheet = true"
      >
        <span
          class="material-symbols-outlined text-[22px] leading-none"
          :style="hasActiveFilters ? 'font-variation-settings: \'FILL\' 1' : ''"
        >tune</span>
        <span
          v-if="activeFilterCount > 0"
          class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[9px] font-bold text-on-primary"
        >{{ activeFilterCount }}</span>
      </button>
    </UiSubPageHeader>

    <!-- Active filter summary strip -->
    <div
      v-if="hasActiveFilters"
      class="flex items-center gap-2 border-b border-white/5 bg-background px-margin py-2"
    >
      <span
        class="material-symbols-outlined text-[14px] leading-none text-primary/70 shrink-0"
        style="font-variation-settings: 'FILL' 1"
      >filter_alt</span>
      <div class="flex min-w-0 flex-1 gap-1.5 overflow-x-auto hide-scrollbar">
        <span
          v-if="activeStage"
          class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 font-label-caps text-[10px] uppercase tracking-widest text-primary"
        >{{ STAGE_LABELS[activeStage] ?? activeStage }}</span>
        <span
          v-if="activeGroup"
          class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 font-label-caps text-[10px] uppercase tracking-widest text-primary"
        >Gr. {{ activeGroup }}</span>
        <span
          v-if="activeTeam"
          class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 font-label-caps text-[10px] uppercase tracking-widest text-primary"
        >{{ activeTeam }}</span>
        <span
          v-if="activeDateLabel"
          class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 font-label-caps text-[10px] uppercase tracking-widest text-primary"
        >{{ activeDateLabel }}</span>
      </div>
      <button
        type="button"
        class="shrink-0 text-on-surface-variant/50 transition-opacity hover:opacity-80"
        aria-label="Limpar filtros"
        @click="clearFilters"
      >
        <span class="material-symbols-outlined text-[16px] leading-none">close</span>
      </button>
    </div>

    <div class="px-margin py-4">
      <UiPhaseBadge :phase="currentPhase" class="mb-4" />

      <div v-if="matches.length > 0" class="mb-4 flex rounded-full border border-white/10 bg-surface-container-low p-1">
        <button
          type="button"
          :class="[viewChipBase, !isResultsMode ? viewChipActive : viewChipInactive]"
          :aria-pressed="!isResultsMode"
          @click="viewMode = 'agenda'"
        >
          Agenda
        </button>
        <button
          type="button"
          :class="[viewChipBase, isResultsMode ? viewChipActive : viewChipInactive]"
          :aria-pressed="isResultsMode"
          @click="viewMode = 'encerrados'"
        >
          Encerrados
        </button>
      </div>

      <div v-if="loading && matches.length === 0" class="space-y-2">
        <UiMatchCardSkeleton v-for="n in skeletonCount" :key="n" />
      </div>

      <p v-else-if="error" class="mb-4 font-body-lg text-body-lg text-error">{{ error }}</p>

      <button
        v-if="error"
        type="button"
        class="mb-6 flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container py-3 font-label-caps text-label-caps uppercase text-on-surface transition-colors hover:bg-surface-container-high"
        @click="refreshMatches"
      >
        <span class="material-symbols-outlined text-[18px]">refresh</span>
        TENTAR NOVAMENTE
      </button>

      <p
        v-else-if="!loading && matches.length === 0"
        class="font-body-lg text-body-lg text-on-surface-variant"
      >
        Nenhum jogo disponível no momento.
      </p>

      <template v-if="matches.length > 0">
        <!-- Empty state: agenda -->
        <p v-if="showEmptyAgenda" class="mb-4 font-body-lg text-body-lg text-on-surface-variant">
          <template v-if="hasActiveFilters">Nenhum jogo encontrado com os filtros selecionados.</template>
          <template v-else>Não há jogos na agenda agora.</template>
        </p>

        <!-- Empty state: resultados -->
        <p v-if="showEmptyResults" class="mb-4 font-body-lg text-body-lg text-on-surface-variant">
          <template v-if="hasActiveFilters">Nenhum resultado com os filtros selecionados.</template>
          <template v-else>Nenhum jogo encerrado.</template>
        </p>

        <!-- RESULTADOS (modo encerrados) -->
        <template v-if="isResultsMode">
          <div
            v-if="finishedMatches.length > 0"
            class="mb-6 flex items-center justify-between rounded-xl border border-white/10 bg-surface-container px-4 py-3"
          >
            <div>
              <p class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                Jogos encerrados
              </p>
              <p class="font-title-md text-title-md text-on-surface">
                {{ finishedMatches.length }}
                {{ finishedMatches.length === 1 ? 'partida' : 'partidas' }}
              </p>
            </div>
            <div class="text-right">
              <p class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                Seus pontos
              </p>
              <p
                class="font-headline-lg-mobile text-headline-lg-mobile leading-none tracking-tighter"
                :class="resultsSummary.totalPoints > 0 ? 'text-secondary-container' : 'text-on-surface-variant/40'"
              >
                {{ resultsSummary.totalPoints > 0 ? `+${resultsSummary.totalPoints}` : '0' }}
              </p>
            </div>
          </div>

          <section v-for="group in finishedGroupsReversed" :key="group.key" class="mb-8">
            <div class="mb-4 flex items-center gap-3">
              <h2 class="shrink-0 font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
                {{ group.label }}
              </h2>
              <div class="h-px flex-1 bg-outline-variant" />
            </div>
            <div class="space-y-2">
              <MatchCard
                v-for="match in group.matches"
                :key="match.id"
                :match="match"
                :prediction="predictionFor(match.id)"
                results-mode
              />
            </div>
          </section>
        </template>

        <!-- AO VIVO -->
        <section v-if="!isResultsMode && liveMatches.length > 0" class="mb-8">
          <div class="mb-4 flex items-center gap-2">
            <span class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-primary" />
            <h2 class="shrink-0 font-label-caps text-label-caps uppercase tracking-widest text-primary">Ao Vivo</h2>
            <span class="font-label-caps text-[10px] text-primary/50">· {{ liveMatches.length }}</span>
            <div class="h-px flex-1 bg-primary/15" />
          </div>
          <div class="space-y-2">
            <MatchCard
              v-for="match in liveMatches"
              :key="match.id"
              :match="match"
              :prediction="predictionFor(match.id)"
            />
          </div>
        </section>

        <!-- PRÓXIMOS (agrupados por data) -->
        <template v-if="!isResultsMode">
          <section v-for="group in scheduledGroups" :key="group.key" class="mb-8">
            <div class="mb-4 flex items-center gap-3">
              <h2 class="shrink-0 font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
                {{ group.label }}
              </h2>
              <div class="h-px flex-1 bg-outline-variant" />
            </div>
            <div class="space-y-2">
              <MatchCard
                v-for="match in group.matches"
                :key="match.id"
                :match="match"
                :prediction="predictionFor(match.id)"
              />
            </div>
          </section>
        </template>

        <!-- ENCERRADOS (colapsável, só no modo agenda) -->
        <div v-if="!isResultsMode && finishedMatches.length > 0" class="mb-8">
          <button
            v-if="activeDate === null"
            type="button"
            class="mb-4 flex w-full items-center gap-3 active:opacity-70"
            @click="showFinished = !showFinished"
          >
            <h2 class="shrink-0 font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
              Encerrados
            </h2>
            <span class="font-label-caps text-[10px] text-on-surface-variant/40">{{ finishedMatches.length }}</span>
            <div class="h-px flex-1 bg-outline-variant" />
            <span class="material-symbols-outlined shrink-0 text-[20px] leading-none text-on-surface-variant/60 transition-transform duration-200"
              :class="showFinished ? 'rotate-180' : ''"
            >keyboard_arrow_down</span>
          </button>
          <div v-else class="mb-4 flex items-center gap-3">
            <h2 class="shrink-0 font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
              Encerrados
            </h2>
            <div class="h-px flex-1 bg-outline-variant" />
          </div>

          <template v-if="showFinishedSection">
            <section v-for="group in finishedGroups" :key="group.key" class="mb-6">
              <div class="mb-3 flex items-center gap-3">
                <h3 class="shrink-0 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                  {{ group.label }}
                </h3>
                <div class="h-px flex-1 bg-outline-variant/50" />
              </div>
              <div class="space-y-2">
                <MatchCard
                  v-for="match in group.matches"
                  :key="match.id"
                  :match="match"
                  :prediction="predictionFor(match.id)"
                />
              </div>
            </section>
          </template>
        </div>

        <!-- ADIADOS E CANCELADOS -->
        <section v-if="!isResultsMode && tailMatches.length > 0" class="mb-8">
          <div class="mb-4 flex items-center gap-3">
            <h2 class="shrink-0 font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
              Adiados e Cancelados
            </h2>
            <div class="h-px flex-1 bg-outline-variant" />
          </div>
          <div class="space-y-2">
            <MatchCard
              v-for="match in tailMatches"
              :key="match.id"
              :match="match"
              :prediction="predictionFor(match.id)"
            />
          </div>
        </section>
      </template>
    </div>

    <!-- Bottom sheet (teleported to avoid transform-context issues during page transitions) -->
    <Teleport to="body">
      <Transition name="overlay-fade">
        <div
          v-if="showFilterSheet"
          class="fixed inset-0 z-40 bg-black/60 backdrop-blur-[2px]"
          @click="showFilterSheet = false"
        />
      </Transition>

      <Transition name="sheet-up">
        <div
          v-if="showFilterSheet"
          class="fixed bottom-0 left-0 right-0 z-50 flex max-h-[85vh] flex-col rounded-t-2xl bg-surface-container"
          style="padding-bottom: env(safe-area-inset-bottom)"
        >
          <!-- Drag handle -->
          <div class="flex justify-center pb-2 pt-3">
            <div class="h-1 w-10 rounded-full bg-on-surface-variant/20" />
          </div>

          <!-- Sheet header -->
          <div class="flex items-center justify-between px-margin pb-3 pt-1">
            <span class="font-headline-lg-mobile text-headline-lg-mobile uppercase tracking-tighter text-on-surface">
              Filtros
            </span>
            <button
              type="button"
              class="flex h-8 w-8 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-white/5"
              @click="showFilterSheet = false"
            >
              <span class="material-symbols-outlined text-[20px] leading-none">close</span>
            </button>
          </div>

          <!-- Scrollable sections -->
          <div class="flex-1 overflow-y-auto px-margin">

            <!-- FASE -->
            <div v-if="availableStages.length > 1" class="mb-6">
              <p class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">Fase</p>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  :class="[chipBase, activeStage === null ? chipActive : chipInactive]"
                  @click="setStage(null)"
                >Todas</button>
                <button
                  v-for="stage in availableStages"
                  :key="stage.value"
                  type="button"
                  :class="[chipBase, activeStage === stage.value ? chipActive : chipInactive]"
                  @click="setStage(stage.value)"
                >{{ stage.label }}</button>
              </div>
            </div>

            <!-- GRUPO -->
            <div v-if="isGroupStageActive && availableGroups.length > 1" class="mb-6">
              <p class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">Grupo</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="group in availableGroups"
                  :key="group"
                  type="button"
                  :class="[chipBase, activeGroup === group ? chipActive : chipInactive]"
                  @click="setGroup(group)"
                >{{ group }}</button>
              </div>
            </div>

            <!-- SELEÇÃO -->
            <div v-if="availableTeams.length > 0" class="mb-6">
              <div class="mb-3 flex items-center justify-between">
                <p class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">Seleção</p>
                <span
                  v-if="activeStage"
                  class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/35"
                >{{ filteredTeams.length }} disponíveis</span>
              </div>

              <!-- Search -->
              <div class="relative mb-3">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] leading-none text-on-surface-variant/40">search</span>
                <input
                  v-model="teamSearch"
                  type="search"
                  placeholder="Buscar seleção..."
                  class="w-full rounded-lg border border-white/10 bg-surface-container-high py-2 pl-9 pr-9 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/40 focus:border-primary/40 focus:outline-none"
                >
                <button
                  v-if="teamSearch"
                  type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2"
                  @click="teamSearch = ''"
                >
                  <span class="material-symbols-outlined text-[14px] leading-none text-on-surface-variant/40">close</span>
                </button>
              </div>

              <!-- Flag grid -->
              <div v-if="filteredTeams.length > 0" class="grid grid-cols-4 gap-2">
                <button
                  v-for="team in filteredTeams"
                  :key="team.name"
                  type="button"
                  class="flex flex-col items-center gap-1.5 rounded-xl border py-2.5 transition-all duration-150 active:scale-95"
                  :class="activeTeam === team.name
                    ? 'border-primary/40 bg-primary/10'
                    : 'border-white/10 hover:border-white/15'"
                  @click="setTeam(team.name)"
                >
                  <!-- Flag circle -->
                  <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border p-[5px]"
                    :class="activeTeam === team.name
                      ? 'border-primary/30 bg-surface-container-high'
                      : 'border-white/10 bg-surface-container-highest'"
                  >
                    <img
                      v-if="team.flag"
                      :src="team.flag"
                      :alt="team.name"
                      class="h-full w-full object-contain"
                      loading="lazy"
                    >
                    <span v-else class="font-label-caps text-[8px] text-on-surface-variant">?</span>
                  </div>
                  <!-- Name -->
                  <span
                    class="w-full truncate px-1 text-center font-label-caps text-[9px] uppercase leading-none tracking-wide"
                    :class="activeTeam === team.name ? 'text-primary' : 'text-on-surface-variant/70'"
                  >{{ team.name }}</span>
                </button>
              </div>

              <p v-else class="font-body-sm text-body-sm text-on-surface-variant/50">
                Nenhuma seleção encontrada.
              </p>
            </div>

            <!-- DATA -->
            <div v-if="dateCards.length > 1" class="mb-6">
              <p class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">Data</p>
              <div class="overflow-x-auto hide-scrollbar">
                <div class="flex gap-2 pb-1">
                  <button
                    v-for="d in dateCards"
                    :key="d.key"
                    type="button"
                    class="flex w-[54px] shrink-0 flex-col items-center rounded-xl border py-2.5 transition-all duration-150 active:scale-95"
                    :class="activeDate === d.key
                      ? 'border-primary/40 bg-primary/10'
                      : 'border-white/10 hover:border-white/20'"
                    @click="setDate(d.key)"
                  >
                    <!-- Today dot -->
                    <span
                      class="mb-1 h-[5px] w-[5px] rounded-full transition-colors"
                      :class="d.isToday
                        ? (activeDate === d.key ? 'bg-primary' : 'bg-primary/50')
                        : 'opacity-0'"
                    />
                    <!-- Weekday -->
                    <span
                      class="font-label-caps text-[8px] leading-none tracking-widest"
                      :class="activeDate === d.key ? 'text-primary/70' : 'text-on-surface-variant/50'"
                    >{{ d.weekday }}</span>
                    <!-- Day number -->
                    <span
                      class="font-display text-[22px] leading-tight"
                      :class="activeDate === d.key ? 'text-primary' : 'text-on-surface'"
                    >{{ d.day }}</span>
                    <!-- Month -->
                    <span
                      class="font-label-caps text-[8px] leading-none tracking-widest"
                      :class="activeDate === d.key ? 'text-primary/70' : 'text-on-surface-variant/50'"
                    >{{ d.month }}</span>
                    <!-- Match count dots -->
                    <div class="mt-1.5 flex h-[5px] items-center gap-[3px]">
                      <span
                        v-for="i in Math.min(d.count, 3)"
                        :key="i"
                        class="h-[3px] w-[3px] rounded-full"
                        :class="activeDate === d.key ? 'bg-primary/60' : 'bg-on-surface-variant/30'"
                      />
                    </div>
                  </button>
                </div>
              </div>
            </div>

          </div>

          <!-- Footer actions -->
          <div class="border-t border-white/5 px-margin pb-4 pt-3">
            <div class="flex gap-3">
              <button
                v-if="hasActiveFilters"
                type="button"
                class="rounded-lg border border-white/10 px-5 py-2.5 font-label-caps text-label-caps text-on-surface-variant transition-colors hover:bg-white/5"
                @click="clearFilters"
              >LIMPAR</button>
              <button
                type="button"
                class="flex-1 rounded-lg bg-primary py-2.5 font-label-caps text-label-caps text-on-primary shadow-lg shadow-primary/20 transition-[background-color,transform] duration-150 active:scale-[0.98]"
                @click="showFilterSheet = false"
              >VER JOGOS</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
