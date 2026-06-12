export type MatchStatus = 'SCHEDULED' | 'LIVE' | 'FINISHED' | 'POSTPONED' | 'CANCELLED'

export interface Match {
  id: string
  external_id?: number
  home_team: string | null
  away_team: string | null
  home_flag: string | null
  away_flag: string | null
  starts_at: string
  stage: string
  group_name: string | null
  status: MatchStatus
  home_score: number | null
  away_score: number | null
  synced_at?: string | null
}

export interface UserPrediction {
  id: string
  match_id: string
  user_id?: string
  home_score: number
  away_score: number
  points_earned: number | null
}

export interface PredictionWithMatch extends UserPrediction {
  match: Match
}

export interface PredictionUser {
  id: string
  name: string
  avatar_url: string | null
}

export interface GroupPrediction extends UserPrediction {
  user_id: string
  user: PredictionUser
}

export interface MatchDetailResponse {
  data: Match
  user_prediction: UserPrediction | null
  group_predictions: GroupPrediction[]
}

export function isMatchTbd(match: Match): boolean {
  const tbd = (name: string | null) => !name || name.toUpperCase() === 'TBD'
  return tbd(match.home_team) || tbd(match.away_team)
}

export function flagUrl(code: string | null | undefined): string | null {
  if (!code?.trim()) return null
  const value = code.trim()
  if (value.startsWith('http://') || value.startsWith('https://')) {
    return value
  }
  return `https://flagcdn.com/h24/${value.toLowerCase()}.png`
}
