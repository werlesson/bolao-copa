export interface GroupMember {
  id: string
  name: string
  avatar_url: string | null
  joined_at?: string
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
  invite_token?: string
  members_count?: number | null
  pending_requests_count?: number
  members?: GroupMember[]
  created_at?: string
}

export interface JoinRequest {
  id: string
  group_id: string
  user_id: string
  status: string
  created_at: string
  user?: {
    id: string
    name: string
    avatar_url: string | null
  }
}

export interface CreateGroupPayload {
  name: string
  require_approval: boolean
  max_members: number | null
}

export interface UpdateGroupPayload {
  name?: string
  require_approval?: boolean
  max_members?: number | null
}

export interface InvitePreview {
  id: string
  name: string
  require_approval: boolean
  is_member: boolean
  has_pending_request: boolean
}

export interface GroupRankingPreviewRow {
  position: number
  name: string
  totalPoints: number
  isCurrentUser: boolean
  gapBefore?: boolean
}
