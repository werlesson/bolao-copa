import type { Match, MatchStatus, UserPrediction } from '~/types/match'
import { unwrapList } from '~/utils/api'
import { matchListNeedsFastPoll } from '~/utils/matchPolling'

const STAGE_ORDER: Record<string, number> = {
  GROUP_STAGE: 1,
  ROUND_OF_16: 2,
  QUARTER_FINALS: 3,
  SEMI_FINALS: 4,
  FINAL: 5,
}

const STATUS_ORDER: Record<MatchStatus, number> = {
  LIVE: 1,
  SCHEDULED: 2,
  FINISHED: 3,
  POSTPONED: 4,
  CANCELLED: 5,
}

export function sortMatches(matches: Match[]): Match[] {
  return [...matches].sort((a, b) => {
    const statusDiff = STATUS_ORDER[a.status] - STATUS_ORDER[b.status]
    if (statusDiff !== 0) return statusDiff
    return new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime()
  })
}

export function deriveCurrentPhase(matches: Match[]): string {
  const live = matches.find((m) => m.status === 'LIVE')
  if (live) return live.stage

  const nextScheduled = matches
    .filter((m) => m.status === 'SCHEDULED')
    .sort((a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime())[0]

  return nextScheduled?.stage ?? 'GROUP_STAGE'
}

export function dateKey(iso: string): string {
  const date = new Date(iso)
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

export function formatDateSection(iso: string): string {
  const date = new Date(iso)
  const today = new Date()
  const tomorrow = new Date()
  tomorrow.setDate(today.getDate() + 1)

  const sameDay = (a: Date, b: Date) =>
    a.getFullYear() === b.getFullYear()
    && a.getMonth() === b.getMonth()
    && a.getDate() === b.getDate()

  const formatted = new Intl.DateTimeFormat('pt-BR', {
    day: 'numeric',
    month: 'long',
  }).format(date)

  if (sameDay(date, today)) return `Hoje, ${formatted}`
  if (sameDay(date, tomorrow)) return `Amanhã, ${formatted}`

  const weekday = new Intl.DateTimeFormat('pt-BR', { weekday: 'long' }).format(date)
  return `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)}, ${formatted}`
}

export interface DateMatchGroup {
  key: string
  label: string
  matches: Match[]
}

export function groupMatchesByDate(matches: Match[]): DateMatchGroup[] {
  const map = new Map<string, Match[]>()

  for (const match of matches) {
    const key = dateKey(match.starts_at)
    const bucket = map.get(key) ?? []
    bucket.push(match)
    map.set(key, bucket)
  }

  return [...map.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([key, groupMatches]) => ({
      key,
      label: formatDateSection(groupMatches[0]!.starts_at),
      matches: groupMatches,
    }))
}

export function useMatches() {
  const config = useRuntimeConfig()
  const apiUrl = config.public.apiUrl as string

  const matches = ref<Match[]>([])
  const predictionsByMatchId = ref<Record<string, UserPrediction>>({})
  const loading = ref(false)
  const error = ref<string | null>(null)

  const currentPhase = computed(() => deriveCurrentPhase(matches.value))

  async function fetchMatches(options?: { bustCache?: boolean }) {
    loading.value = true
    error.value = null

    try {
      const matchesRes = await $fetch<Match[] | { data: Match[] }>('/api/matches', {
        baseURL: apiUrl,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          ...(options?.bustCache ? { 'Cache-Control': 'no-cache' } : {}),
        },
        ...(options?.bustCache ? { query: { _: Date.now() } } : {}),
      })

      matches.value = sortMatches(unwrapList(matchesRes))

      try {
        const predictionsRes = await $fetch<UserPrediction[] | { data: UserPrediction[] }>(
          '/api/predictions',
          {
            baseURL: apiUrl,
            credentials: 'include',
            headers: { Accept: 'application/json' },
          },
        )

        const predictionMap: Record<string, UserPrediction> = {}
        for (const prediction of unwrapList(predictionsRes)) {
          predictionMap[prediction.match_id] = prediction
        }
        predictionsByMatchId.value = predictionMap
      } catch (err: unknown) {
        predictionsByMatchId.value = {}
        console.warn('[useMatches] /api/predictions failed', err)
      }
    } catch (err: unknown) {
      const status = (err as { statusCode?: number })?.statusCode
      error.value = status === 401
        ? 'Sessão expirada. Faça login novamente.'
        : 'Não foi possível carregar os jogos.'
      matches.value = []
      predictionsByMatchId.value = {}
      console.error('[useMatches] /api/matches failed', err)
    } finally {
      loading.value = false
    }
  }

  function predictionFor(matchId: string): UserPrediction | null {
    return predictionsByMatchId.value[matchId] ?? null
  }

  function needsFastPoll(): boolean {
    return matchListNeedsFastPoll(matches.value)
  }

  return {
    matches,
    loading,
    error,
    currentPhase,
    fetchMatches,
    predictionFor,
    needsFastPoll,
    STAGE_ORDER,
  }
}
