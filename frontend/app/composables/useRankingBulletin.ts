import type { RankingBulletin, RankingBulletinResponse } from '~/types/rankingBulletin'
import type { RankingTabId } from '~/types/ranking'
import { unwrapList } from '~/utils/api'

export function useRankingBulletin() {
  const config = useRuntimeConfig()
  const apiUrl = config.public.apiUrl as string

  const bulletin = ref<RankingBulletin | null>(null)
  const loading = ref(false)

  async function fetchBulletin(tabId: RankingTabId) {
    loading.value = true

    try {
      const url = tabId === 'global'
        ? '/api/rankings/global/bulletin?limit=1'
        : `/api/groups/${tabId}/ranking/bulletin?limit=1`

      const response = await $fetch<RankingBulletin[] | RankingBulletinResponse>(url, {
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })

      const rows = unwrapList(response)
      bulletin.value = rows[0] ?? null
    } catch {
      bulletin.value = null
    } finally {
      loading.value = false
    }
  }

  return {
    bulletin,
    loading,
    fetchBulletin,
  }
}
