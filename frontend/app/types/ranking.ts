export interface RankingUser {
  id: string
  name: string
  avatar_url: string | null
}

export interface RankingEntry {
  position: number
  total_points: number
  exact_scores: number
  correct_results: number
  total_predictions: number
  user: RankingUser
}

export interface BolaoGroup {
  id: string
  name: string
  slug: string
  is_global: boolean
  require_approval: boolean
  max_members: number | null
  owner_id: string | null
  is_owner: boolean
  members_count?: number | null
}

export interface GlobalRankingResponse {
  data: RankingEntry[]
  total: number
  user_entry: RankingEntry | null
}

export type RankingTabId = 'global' | string
