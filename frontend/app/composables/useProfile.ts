import type { ProfilePrediction, ProfileStats, PhasePoints } from '~/types/profile'
import { useAuthStore, type AuthUser } from '~/stores/auth'
import { useRankingStore } from '~/stores/ranking'
import { unwrapList } from '~/utils/api'

const STAT_CARD = 'glass-card rounded-xl'

const PHASE_ORDER = [
  'GROUP_STAGE',
  'ROUND_OF_16',
  'QUARTER_FINALS',
  'SEMI_FINALS',
  'FINAL',
] as const

const PHASE_SHORT_LABELS: Record<string, string> = {
  GROUP_STAGE: 'Grupos',
  ROUND_OF_16: 'Oitavas',
  QUARTER_FINALS: 'Quartas',
  SEMI_FINALS: 'Semi-final',
  FINAL: 'Final',
}

function buildPhasePoints(predictions: ProfilePrediction[]): PhasePoints[] {
  const byStage = new Map<string, number>()

  for (const prediction of predictions) {
    if (prediction.match.status !== 'FINISHED' || prediction.points_earned == null) continue
    const stage = prediction.match.stage
    byStage.set(stage, (byStage.get(stage) ?? 0) + prediction.points_earned)
  }

  const rows: PhasePoints[] = []
  for (const stage of PHASE_ORDER) {
    const points = byStage.get(stage)
    if (points != null && points > 0) {
      rows.push({ stage, label: PHASE_SHORT_LABELS[stage] ?? stage, points })
    }
  }

  for (const [stage, points] of byStage) {
    if (!(PHASE_ORDER as readonly string[]).includes(stage) && points > 0) {
      rows.push({ stage, label: PHASE_SHORT_LABELS[stage] ?? stage, points })
    }
  }

  return rows
}

export function useProfile() {
  const config = useRuntimeConfig()
  const apiUrl = config.public.apiUrl as string
  const authStore = useAuthStore()
  const rankingStore = useRankingStore()

  const stats = ref<ProfileStats | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const savingName = ref(false)
  const nameError = ref<string | null>(null)

  async function fetchStats(userId: string) {
    loading.value = true
    error.value = null

    try {
      const [, predictionsRes] = await Promise.all([
        rankingStore.ensureGlobal(apiUrl),
        $fetch<ProfilePrediction[] | { data: ProfilePrediction[] }>('/api/predictions', {
          baseURL: apiUrl,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        }),
      ])

      // userEntry comes directly from the API (handles users outside global top 100).
      const me = rankingStore.userEntry ?? rankingStore.entries.find(e => e.user.id === userId)
      const total_predictions = me?.total_predictions ?? 0
      const correct_results = me?.correct_results ?? 0
      const accuracy_percent = total_predictions > 0
        ? Math.round((correct_results / total_predictions) * 100)
        : null

      stats.value = {
        position: me?.position ?? null,
        total_points: me?.total_points ?? 0,
        total_predictions,
        exact_scores: me?.exact_scores ?? 0,
        correct_results,
        accuracy_percent,
        phase_points: buildPhasePoints(unwrapList(predictionsRes)),
      }
    } catch {
      error.value = 'Não foi possível carregar suas estatísticas.'
      stats.value = null
    } finally {
      loading.value = false
    }
  }

  async function updateName(name: string): Promise<boolean> {
    const trimmed = name.trim()
    if (trimmed.length < 2) {
      nameError.value = 'O nome deve ter pelo menos 2 caracteres.'
      return false
    }

    savingName.value = true
    nameError.value = null

    try {
      const response = await $fetch<{ data?: AuthUser } | AuthUser>('/api/user', {
        method: 'PATCH',
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
        body: { name: trimmed },
      })

      const updated = 'data' in response && response.data
        ? response.data
        : (response as AuthUser)

      if (authStore.user && updated) {
        authStore.user = { ...authStore.user, name: updated.name ?? trimmed }
      }
      return true
    } catch {
      nameError.value = 'Não foi possível salvar o nome.'
      return false
    } finally {
      savingName.value = false
    }
  }

  return {
    statCardClass: STAT_CARD,
    stats,
    loading,
    error,
    savingName,
    nameError,
    fetchStats,
    updateName,
  }
}
