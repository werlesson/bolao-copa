export interface RankingBulletinMatch {
  id: string
  label: string
  home_team: string
  away_team: string
}

export interface RankingBulletin {
  id: string
  content: string
  source: 'ai' | 'template'
  created_at: string | null
  match: RankingBulletinMatch | null
}

export interface RankingBulletinResponse {
  data: RankingBulletin[]
}
