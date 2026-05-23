import { storeToRefs } from 'pinia'
import type { BolaoGroup, RankingEntry, RankingTabId } from '~/types/ranking'
import { unwrapList } from '~/utils/api'
import { useRankingStore } from '~/stores/ranking'

export function useRankings() {
  const config = useRuntimeConfig()
  const apiUrl = config.public.apiUrl as string
  const rankingStore = useRankingStore()
  const { entries: globalEntries, userEntry: globalUserEntry, total: globalTotal } = storeToRefs(rankingStore)

  const groups = ref<BolaoGroup[]>([])
  const localEntries = ref<RankingEntry[]>([])
  const isGlobalTab = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Transparently switches source depending on active tab.
  const entries = computed<RankingEntry[]>(() =>
    isGlobalTab.value ? globalEntries.value : localEntries.value,
  )

  async function fetchGroups() {
    try {
      const response = await $fetch<BolaoGroup[] | { data: BolaoGroup[] }>('/api/groups', {
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      groups.value = unwrapList(response).filter(group => !group.is_global)
    } catch (err: unknown) {
      groups.value = []
      console.warn('[useRankings] /api/groups failed', err)
    }
  }

  async function fetchRanking(tabId: RankingTabId, force = false) {
    loading.value = true
    error.value = null
    isGlobalTab.value = tabId === 'global'

    try {
      if (tabId === 'global') {
        await rankingStore.ensureGlobal(apiUrl, force)
      } else {
        const response = await $fetch<RankingEntry[] | { data: RankingEntry[] }>(
          `/api/groups/${tabId}/ranking`,
          {
            baseURL: apiUrl,
            credentials: 'include',
            headers: { Accept: 'application/json' },
          },
        )
        localEntries.value = unwrapList(response)
      }
    } catch (err: unknown) {
      const status = (err as { statusCode?: number })?.statusCode
      error.value = status === 401
        ? 'Sessão expirada. Faça login novamente.'
        : 'Não foi possível carregar o ranking.'
      localEntries.value = []
      console.error(`[useRankings] tabId=${tabId} failed`, err)
    } finally {
      loading.value = false
    }
  }

  return {
    groups,
    entries,
    globalUserEntry,
    globalTotal,
    isGlobalTab,
    loading,
    error,
    fetchGroups,
    fetchRanking,
  }
}
