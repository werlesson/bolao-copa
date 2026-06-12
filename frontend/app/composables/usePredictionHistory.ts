import type { Match, PredictionWithMatch } from '~/types/match'
import { flagUrl } from '~/types/match'
import { dateKey, formatDateSection } from '~/composables/useMatches'
import { unwrapList } from '~/utils/api'

export type PredictionViewMode = 'todos' | 'aguardando' | 'finalizados'
export type OutcomeFilter = 'exact' | 'partial' | 'miss' | null

export const MAX_POINTS_PER_MATCH = 3

const STAGE_ORDER: Record<string, number> = {
  GROUP_STAGE: 0,
  REGULAR_SEASON: 0,
  LAST_32: 1,
  LAST_16: 2,
  QUARTER_FINALS: 3,
  SEMI_FINALS: 4,
  THIRD_PLACE: 5,
  FINAL: 6,
}

export const STAGE_LABELS: Record<string, string> = {
  GROUP_STAGE: 'Grupos',
  REGULAR_SEASON: 'Grupos',
  LAST_32: 'Rodada 32',
  LAST_16: 'Oitavas',
  QUARTER_FINALS: 'Quartas',
  SEMI_FINALS: 'Semifinal',
  THIRD_PLACE: '3º Lugar',
  FINAL: 'Final',
}

export const OUTCOME_LABELS: Record<Exclude<OutcomeFilter, null>, string> = {
  exact: 'Placar exato (+3)',
  partial: 'Acertou vencedor (+1)',
  miss: 'Errou (0)',
}

export interface PredictionDateGroup {
  key: string
  label: string
  items: PredictionWithMatch[]
}

export interface PredictionHistorySummary {
  totalPoints: number
  accuracyPercent: number | null
  predictionCount: number
  finishedCount: number
}

export function predictionOutcome(
  prediction: PredictionWithMatch,
): 'exact' | 'partial' | 'miss' | null {
  if (prediction.match.status !== 'FINISHED' || prediction.points_earned === null) {
    return null
  }
  if (prediction.points_earned === 3) return 'exact'
  if (prediction.points_earned === 1) return 'partial'
  return 'miss'
}

function sortPredictions(list: PredictionWithMatch[], reverse: boolean): PredictionWithMatch[] {
  return [...list].sort((a, b) => {
    const diff = new Date(a.match.starts_at).getTime() - new Date(b.match.starts_at).getTime()
    return reverse ? -diff : diff
  })
}

export function usePredictionHistory() {
  const config = useRuntimeConfig()
  const apiUrl = config.public.apiUrl as string

  const predictions = ref<PredictionWithMatch[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const viewMode = ref<PredictionViewMode>('todos')
  const teamSearch = ref('')

  const cookieOpts = { default: () => null as string | null, maxAge: 60 * 60 * 24 * 90 }
  const activeStage = useCookie<string | null>('palpites-filter-stage', cookieOpts)
  const activeTeam = useCookie<string | null>('palpites-filter-team', cookieOpts)
  const activeDate = useCookie<string | null>('palpites-filter-date', cookieOpts)
  const activeOutcome = useCookie<OutcomeFilter>('palpites-filter-outcome', {
    default: () => null,
    maxAge: 60 * 60 * 24 * 90,
  })

  async function fetchHistory() {
    loading.value = true
    error.value = null

    try {
      const response = await $fetch<PredictionWithMatch[] | { data: PredictionWithMatch[] }>(
        '/api/predictions',
        {
          baseURL: apiUrl,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        },
      )

      const list = unwrapList(response).filter(
        (item): item is PredictionWithMatch => Boolean(item.match),
      )

      predictions.value = sortPredictions(list, true)
    } catch (err: unknown) {
      const status = (err as { statusCode?: number })?.statusCode
      error.value = status === 401
        ? 'Sessão expirada. Faça login novamente.'
        : 'Não foi possível carregar seus palpites.'
      predictions.value = []
      console.error('[usePredictionHistory] /api/predictions failed', err)
    } finally {
      loading.value = false
    }
  }

  function matchesViewMode(item: PredictionWithMatch): boolean {
    if (viewMode.value === 'finalizados') {
      return item.match.status === 'FINISHED'
    }
    if (viewMode.value === 'aguardando') {
      return item.match.status === 'SCHEDULED' || item.match.status === 'LIVE'
    }
    return true
  }

  function matchesFilters(item: PredictionWithMatch): boolean {
    if (activeStage.value && item.match.stage !== activeStage.value) return false
    if (
      activeTeam.value
      && item.match.home_team !== activeTeam.value
      && item.match.away_team !== activeTeam.value
    ) {
      return false
    }
    if (activeDate.value && dateKey(item.match.starts_at) !== activeDate.value) return false
    if (activeOutcome.value) {
      const outcome = predictionOutcome(item)
      if (outcome !== activeOutcome.value) return false
    }
    return true
  }

  const filtered = computed(() =>
    predictions.value.filter(item => matchesViewMode(item) && matchesFilters(item)),
  )

  const grouped = computed((): PredictionDateGroup[] => {
    const reverse = viewMode.value === 'finalizados'
    const sorted = sortPredictions(filtered.value, reverse)
    const map = new Map<string, PredictionWithMatch[]>()

    for (const item of sorted) {
      const key = dateKey(item.match.starts_at)
      const bucket = map.get(key) ?? []
      bucket.push(item)
      map.set(key, bucket)
    }

    return [...map.entries()]
      .sort(([a], [b]) => (reverse ? b.localeCompare(a) : a.localeCompare(b)))
      .map(([key, items]) => ({
        key,
        label: formatDateSection(items[0]!.match.starts_at),
        items,
      }))
  })

  const summary = computed((): PredictionHistorySummary => {
    const finishedInFilter = filtered.value.filter(item => item.match.status === 'FINISHED')
    const totalPoints = finishedInFilter.reduce(
      (sum, item) => sum + (item.points_earned ?? 0),
      0,
    )
    const maxPossible = finishedInFilter.length * MAX_POINTS_PER_MATCH
    const accuracyPercent = maxPossible > 0
      ? Math.round((totalPoints / maxPossible) * 100)
      : null

    return {
      totalPoints,
      accuracyPercent,
      predictionCount: filtered.value.length,
      finishedCount: finishedInFilter.length,
    }
  })

  const availableStages = computed(() => {
    const seen = new Set<string>()
    for (const item of predictions.value) seen.add(item.match.stage)
    return [...seen]
      .sort((a, b) => (STAGE_ORDER[a] ?? 99) - (STAGE_ORDER[b] ?? 99))
      .map(stage => ({ value: stage, label: STAGE_LABELS[stage] ?? stage }))
  })

  const availableTeams = computed(() => {
    const teamMap = new Map<string, string | null>()
    for (const item of predictions.value) {
      const match = item.match
      if (match.home_team && !teamMap.has(match.home_team)) {
        teamMap.set(match.home_team, match.home_flag)
      }
      if (match.away_team && !teamMap.has(match.away_team)) {
        teamMap.set(match.away_team, match.away_flag)
      }
    }
    return [...teamMap.entries()]
      .map(([name, flag]) => ({ name, flag: flagUrl(flag) }))
      .sort((a, b) => a.name.localeCompare(b.name))
  })

  const filteredTeams = computed(() => {
    let base = availableTeams.value
    if (activeStage.value) {
      const stageTeams = new Set<string>()
      for (const item of predictions.value) {
        if (item.match.stage === activeStage.value) {
          if (item.match.home_team) stageTeams.add(item.match.home_team)
          if (item.match.away_team) stageTeams.add(item.match.away_team)
        }
      }
      base = base.filter(team => stageTeams.has(team.name))
    }
    const query = teamSearch.value.toLowerCase().trim()
    if (!query) return base
    return base.filter(team => team.name.toLowerCase().includes(query))
  })

  const availableDates = computed(() => {
    const dateMap = new Map<string, string>()
    for (const item of predictions.value) {
      const key = dateKey(item.match.starts_at)
      if (!dateMap.has(key)) dateMap.set(key, item.match.starts_at)
    }
    return [...dateMap.entries()]
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([key, iso]) => ({ key, iso }))
  })

  function formatDateChip(iso: string): string {
    const date = new Date(iso)
    const today = new Date()
    const sameDay = (a: Date, b: Date) =>
      a.getFullYear() === b.getFullYear()
      && a.getMonth() === b.getMonth()
      && a.getDate() === b.getDate()
    if (sameDay(date, today)) return 'Hoje'
    const tomorrow = new Date()
    tomorrow.setDate(today.getDate() + 1)
    if (sameDay(date, tomorrow)) return 'Amanhã'
    return new Intl.DateTimeFormat('pt-BR', {
      day: 'numeric',
      month: 'short',
      timeZone: 'America/Sao_Paulo',
    }).format(date).replace('.', '')
  }

  const activeDateLabel = computed(() => {
    const found = availableDates.value.find(date => date.key === activeDate.value)
    return found ? formatDateChip(found.iso) : null
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
      const count = predictions.value.filter(
        item => dateKey(item.match.starts_at) === key,
      ).length

      return { key, weekday, day, month, isToday, count }
    })
  })

  function setStage(stage: string | null) {
    activeStage.value = activeStage.value === stage ? null : stage
  }

  function setTeam(team: string) {
    activeTeam.value = activeTeam.value === team ? null : team
  }

  function setDate(key: string) {
    activeDate.value = activeDate.value === key ? null : key
  }

  function setOutcome(outcome: OutcomeFilter) {
    activeOutcome.value = activeOutcome.value === outcome ? null : outcome
  }

  function clearFilters() {
    activeStage.value = null
    activeTeam.value = null
    activeDate.value = null
    activeOutcome.value = null
    teamSearch.value = ''
  }

  const hasActiveFilters = computed(() =>
    activeStage.value !== null
    || activeTeam.value !== null
    || activeDate.value !== null
    || activeOutcome.value !== null,
  )

  const activeFilterCount = computed(() =>
    [activeStage.value, activeTeam.value, activeDate.value, activeOutcome.value]
      .filter(Boolean).length,
  )

  const hasAnyPredictions = computed(() => predictions.value.length > 0)

  return {
    predictions,
    loading,
    error,
    viewMode,
    teamSearch,
    activeStage,
    activeTeam,
    activeDate,
    activeOutcome,
    filtered,
    grouped,
    summary,
    availableStages,
    availableTeams,
    filteredTeams,
    dateCards,
    activeDateLabel,
    hasActiveFilters,
    activeFilterCount,
    hasAnyPredictions,
    fetchHistory,
    setStage,
    setTeam,
    setDate,
    setOutcome,
    clearFilters,
    STAGE_LABELS,
    OUTCOME_LABELS,
  }
}
