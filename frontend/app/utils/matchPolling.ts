import type { Match } from '~/types/match'

const FAST_POLL_MS = 20_000
const SLOW_POLL_MS = 60_000
const KICKOFF_WINDOW_MS = 30 * 60 * 1000

export function matchListNeedsFastPoll(matches: Match[]): boolean {
  const now = Date.now()
  return matches.some((m) => matchNeedsFastPoll(m, now))
}

export function matchNeedsFastPoll(match: Match | null, now = Date.now()): boolean {
  if (!match) return false
  if (match.status === 'LIVE') return true
  if (match.status === 'FINISHED' && (match.home_score === null || match.away_score === null)) {
    return true
  }
  if (match.status === 'SCHEDULED') {
    const kickoff = new Date(match.starts_at).getTime()
    return kickoff > now && kickoff - now <= KICKOFF_WINDOW_MS
  }
  return false
}

export function matchPollIntervalMs(matches: Match[]): number {
  return matchListNeedsFastPoll(matches) ? FAST_POLL_MS : SLOW_POLL_MS
}

export { FAST_POLL_MS, SLOW_POLL_MS }
