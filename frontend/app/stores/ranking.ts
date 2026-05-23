import { defineStore } from 'pinia'
import type { GlobalRankingResponse, RankingEntry } from '~/types/ranking'

const TTL_MS = 90_000

export const useRankingStore = defineStore('ranking', () => {
  const entries = ref<RankingEntry[]>([])
  const userEntry = ref<RankingEntry | null>(null)
  const total = ref<number>(0)
  const fetchedAt = ref<number | null>(null)

  function isFresh(): boolean {
    return fetchedAt.value !== null && Date.now() - fetchedAt.value < TTL_MS
  }

  async function ensureGlobal(apiUrl: string, force = false): Promise<void> {
    if (!force && isFresh()) return

    const response = await $fetch<GlobalRankingResponse>(
      '/api/rankings/global',
      {
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      },
    )

    entries.value = response.data
    total.value = response.total
    userEntry.value = response.user_entry
    fetchedAt.value = Date.now()
  }

  function invalidate() {
    fetchedAt.value = null
  }

  return { entries, userEntry, total, fetchedAt, isFresh, ensureGlobal, invalidate }
})
