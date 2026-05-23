import type { GroupRankingPreviewRow } from '~/types/group'
import type { RankingEntry } from '~/types/ranking'

export function buildRankingPreview(
  entries: RankingEntry[],
  currentUserId: string,
): { userRank: number; previewRows: GroupRankingPreviewRow[] } {
  const userEntry = entries.find(entry => entry.user.id === currentUserId)
  const userRank = userEntry?.position ?? (entries.length > 0 ? entries.length : 1)

  const PREVIEW_LIMIT = 5

  const previewRows: GroupRankingPreviewRow[] = entries
    .filter(entry => entry.position <= PREVIEW_LIMIT)
    .slice(0, PREVIEW_LIMIT)
    .map(entry => ({
      position: entry.position,
      name: entry.user.name,
      totalPoints: entry.total_points,
      isCurrentUser: entry.user.id === currentUserId,
    }))

  const userInTop = previewRows.some(r => r.isCurrentUser)
  if (!userInTop && userEntry) {
    const lastPosition = previewRows[previewRows.length - 1]?.position ?? 0
    previewRows.push({
      position: userEntry.position,
      name: userEntry.user.name,
      totalPoints: userEntry.total_points,
      isCurrentUser: true,
      gapBefore: userEntry.position > lastPosition + 1,
    })
  }

  return { userRank, previewRows }
}

export function inviteUrlForToken(token: string): string {
  if (import.meta.client) {
    return `${window.location.origin}/join/${token}`
  }
  return `/join/${token}`
}
