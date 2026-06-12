import type { RankingTabId } from '~/types/ranking'

const prefsKey = (tabId: RankingTabId) => `ranking:bulletin:${tabId}`

interface BulletinPrefs {
  bulletinId: string
  hidden: boolean
}

function readPrefs(tabId: RankingTabId): BulletinPrefs | null {
  if (!import.meta.client) return null

  try {
    const raw = localStorage.getItem(prefsKey(tabId))
    if (!raw) return null
    const parsed = JSON.parse(raw) as BulletinPrefs
    if (typeof parsed.bulletinId !== 'string' || typeof parsed.hidden !== 'boolean') {
      return null
    }
    return parsed
  } catch {
    return null
  }
}

function writePrefs(tabId: RankingTabId, prefs: BulletinPrefs) {
  if (!import.meta.client) return
  localStorage.setItem(prefsKey(tabId), JSON.stringify(prefs))
}

export function useRankingBulletinPrefs() {
  function isHidden(tabId: RankingTabId, bulletinId: string): boolean {
    const prefs = readPrefs(tabId)
    if (!prefs || prefs.bulletinId !== bulletinId) return false
    return prefs.hidden
  }

  function setHidden(tabId: RankingTabId, bulletinId: string, hidden: boolean) {
    writePrefs(tabId, { bulletinId, hidden })
  }

  return {
    isHidden,
    setHidden,
  }
}
