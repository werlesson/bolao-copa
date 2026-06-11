import type { RankingTabId } from '~/types/ranking'

const LAST_TAB_KEY = 'ranking:lastTab'
const RECENT_GROUPS_KEY = 'ranking:recentGroups'
const MAX_RECENT = 20

function readRecentGroups(): string[] {
  if (!import.meta.client) return []
  try {
    const raw = localStorage.getItem(RECENT_GROUPS_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw) as unknown
    return Array.isArray(parsed) ? parsed.filter((id): id is string => typeof id === 'string') : []
  } catch {
    return []
  }
}

function writeRecentGroups(ids: string[]) {
  if (!import.meta.client) return
  localStorage.setItem(RECENT_GROUPS_KEY, JSON.stringify(ids.slice(0, MAX_RECENT)))
}

export function useRankingPrefs() {
  function recordGroupView(tabId: RankingTabId) {
    if (!import.meta.client) return

    localStorage.setItem(LAST_TAB_KEY, tabId)

    if (tabId === 'global') return

    const recent = readRecentGroups().filter(id => id !== tabId)
    recent.unshift(tabId)
    writeRecentGroups(recent)
  }

  function getInitialTab(validGroupIds: string[]): RankingTabId {
    if (!import.meta.client) return 'global'

    const route = useRoute()
    const queryTab = route.query.tab
    if (typeof queryTab === 'string' && queryTab !== 'global' && validGroupIds.includes(queryTab)) {
      return queryTab
    }

    const stored = localStorage.getItem(LAST_TAB_KEY)
    if (stored === 'global') return 'global'
    if (stored && validGroupIds.includes(stored)) return stored

    return 'global'
  }

  function sortGroupsByRecent<T extends { id: string, name: string }>(groups: T[]): T[] {
    const recent = readRecentGroups()
    const order = new Map(recent.map((id, index) => [id, index]))

    return [...groups].sort((a, b) => {
      const aIdx = order.get(a.id) ?? Number.MAX_SAFE_INTEGER
      const bIdx = order.get(b.id) ?? Number.MAX_SAFE_INTEGER
      if (aIdx !== bIdx) return aIdx - bIdx
      return a.name.localeCompare(b.name, 'pt-BR', { sensitivity: 'base' })
    })
  }

  return {
    recordGroupView,
    getInitialTab,
    sortGroupsByRecent,
  }
}
