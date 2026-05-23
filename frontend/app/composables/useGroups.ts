import type {
  BolaoGroup,
  CreateGroupPayload,
  InvitePreview,
  JoinRequest,
  UpdateGroupPayload,
} from '~/types/group'
import type { RankingEntry } from '~/types/ranking'
import { unwrapList, unwrapOne } from '~/utils/api'

export function useGroups() {
  const config = useRuntimeConfig()
  const apiUrl = config.public.apiUrl as string

  const groups = ref<BolaoGroup[]>([])
  const group = ref<BolaoGroup | null>(null)
  const joinRequests = ref<JoinRequest[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchGroups() {
    loading.value = true
    error.value = null
    try {
      const response = await $fetch<BolaoGroup[] | { data: BolaoGroup[] }>('/api/groups', {
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      groups.value = unwrapList(response).filter(g => !g.is_global)
    } catch (err: unknown) {
      groups.value = []
      error.value = 'Não foi possível carregar seus grupos.'
      console.warn('[useGroups] fetchGroups failed', err)
    } finally {
      loading.value = false
    }
  }

  async function fetchGroup(id: string) {
    loading.value = true
    error.value = null
    try {
      const response = await $fetch<BolaoGroup | { data: BolaoGroup }>(`/api/groups/${id}`, {
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      group.value = unwrapOne(response)
    } catch (err: unknown) {
      group.value = null
      const status = (err as { statusCode?: number })?.statusCode
      error.value = status === 404
        ? 'Grupo não encontrado.'
        : 'Não foi possível carregar o grupo.'
      console.error('[useGroups] fetchGroup failed', err)
    } finally {
      loading.value = false
    }
  }

  async function fetchGroupRanking(groupId: string): Promise<RankingEntry[]> {
    try {
      const response = await $fetch<RankingEntry[] | { data: RankingEntry[] }>(
        `/api/groups/${groupId}/ranking`,
        {
          baseURL: apiUrl,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        },
      )
      return unwrapList(response)
    } catch (err: unknown) {
      console.warn(`[useGroups] ranking for ${groupId} failed`, err)
      return []
    }
  }

  async function createGroup(payload: CreateGroupPayload): Promise<BolaoGroup> {
    const response = await $fetch<BolaoGroup | { data: BolaoGroup }>('/api/groups', {
      baseURL: apiUrl,
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json' },
      body: payload,
    })
    return unwrapOne(response)
  }

  async function updateGroup(id: string, payload: UpdateGroupPayload): Promise<BolaoGroup> {
    const response = await $fetch<BolaoGroup | { data: BolaoGroup }>(`/api/groups/${id}`, {
      baseURL: apiUrl,
      method: 'PATCH',
      credentials: 'include',
      headers: { Accept: 'application/json' },
      body: payload,
    })
    return unwrapOne(response)
  }

  async function regenerateInvite(id: string): Promise<string> {
    const response = await $fetch<{ invite_token: string }>(
      `/api/groups/${id}/invite/regenerate`,
      {
        baseURL: apiUrl,
        method: 'POST',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      },
    )
    return response.invite_token
  }

  async function fetchJoinRequests(groupId: string) {
    try {
      const response = await $fetch<JoinRequest[] | { data: JoinRequest[] }>(
        `/api/groups/${groupId}/requests`,
        {
          baseURL: apiUrl,
          credentials: 'include',
          headers: { Accept: 'application/json' },
        },
      )
      joinRequests.value = unwrapList(response)
    } catch (err: unknown) {
      joinRequests.value = []
      console.warn('[useGroups] fetchJoinRequests failed', err)
    }
  }

  async function approveJoinRequest(groupId: string, requestId: string) {
    await $fetch(`/api/groups/${groupId}/requests/${requestId}/approve`, {
      baseURL: apiUrl,
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    joinRequests.value = joinRequests.value.filter(r => r.id !== requestId)
  }

  async function rejectJoinRequest(groupId: string, requestId: string) {
    await $fetch(`/api/groups/${groupId}/requests/${requestId}/reject`, {
      baseURL: apiUrl,
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    joinRequests.value = joinRequests.value.filter(r => r.id !== requestId)
  }

  async function fetchInvitePreview(token: string): Promise<InvitePreview> {
    const response = await $fetch<InvitePreview | { data: InvitePreview }>(
      `/api/groups/invite/${token}`,
      {
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      },
    )
    return unwrapOne(response)
  }

  async function leaveGroup(id: string): Promise<void> {
    await $fetch(`/api/groups/${id}/leave`, {
      baseURL: apiUrl,
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
  }

  async function joinGroupByToken(token: string): Promise<{ groupId: string; pending: boolean }> {
    const response = await $fetch.raw<BolaoGroup | JoinRequest | { data: BolaoGroup | JoinRequest }>(
      `/api/groups/join/${token}`,
      {
        baseURL: apiUrl,
        method: 'POST',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      },
    )

    const data = unwrapOne(response._data as BolaoGroup | JoinRequest | { data: BolaoGroup | JoinRequest })

    if (response.status === 202) {
      return { groupId: (data as JoinRequest).group_id, pending: true }
    }

    return { groupId: (data as BolaoGroup).id, pending: false }
  }

  return {
    groups,
    group,
    joinRequests,
    loading,
    error,
    fetchGroups,
    fetchGroup,
    fetchGroupRanking,
    createGroup,
    updateGroup,
    regenerateInvite,
    fetchJoinRequests,
    approveJoinRequest,
    rejectJoinRequest,
    fetchInvitePreview,
    joinGroupByToken,
    leaveGroup,
  }
}
