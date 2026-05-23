import type { UserPrediction } from '~/types/match'

export function usePredictions() {
  const config = useRuntimeConfig()
  const apiUrl = config.public.apiUrl as string

  const saving = ref(false)
  const saveError = ref<string | null>(null)

  async function savePrediction(
    matchId: string,
    homeScore: number,
    awayScore: number,
  ): Promise<UserPrediction | null> {
    saving.value = true
    saveError.value = null

    try {
      const res = await $fetch<UserPrediction | { data: UserPrediction }>(
        '/api/predictions',
        {
          method: 'POST',
          baseURL: apiUrl,
          credentials: 'include',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: {
            match_id: matchId,
            home_score: homeScore,
            away_score: awayScore,
          },
        },
      )

      return 'data' in res && res.data ? res.data : (res as UserPrediction)
    } catch (err: unknown) {
      const data = (err as { data?: { message?: string } })?.data
      saveError.value = data?.message ?? 'Não foi possível salvar o palpite.'
      return null
    } finally {
      saving.value = false
    }
  }

  return { saving, saveError, savePrediction }
}
