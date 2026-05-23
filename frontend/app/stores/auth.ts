import { defineStore } from 'pinia'
import type { BolaoGroup, JoinRequest } from '~/types/group'

export interface AuthUser {
  id: string
  name: string
  email: string
  avatar_url: string | null
  is_admin: boolean
  onboarding_done: boolean
  has_push?: boolean
}

type UserApiResponse = AuthUser | { data: AuthUser }

function unwrapUser(response: UserApiResponse): AuthUser {
  if (response && typeof response === 'object' && 'data' in response && response.data) {
    return response.data
  }
  return response as AuthUser
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const initialized = ref(false)
  let fetchUserPromise: Promise<void> | null = null

  const isAuthenticated = computed(() => user.value !== null)

  async function fetchUser() {
    if (!fetchUserPromise) {
      fetchUserPromise = (async () => {
        const config = useRuntimeConfig()
        const apiUrl = config.public.apiUrl as string

        try {
          const response = await $fetch.raw<UserApiResponse>('/api/user', {
            baseURL: apiUrl,
            credentials: 'include',
            headers: { Accept: 'application/json' },
            ignoreResponseError: true,
          })

          if (response.status === 401) {
            user.value = null
            return
          }

          if (!response.ok) {
            user.value = null
            return
          }

          user.value = unwrapUser(response._data)
        } catch {
          user.value = null
        } finally {
          initialized.value = true
        }
      })().finally(() => {
        fetchUserPromise = null
      })
    }

    return fetchUserPromise
  }

  function login() {
    const config = useRuntimeConfig()
    const apiUrl = config.public.apiUrl as string
    const base = apiUrl || (import.meta.client ? window.location.origin : '')
    const url = new URL(`${base}/api/auth/google/redirect`)

    const pendingInvite = sessionStorage.getItem('pending_invite_token')
    if (pendingInvite) {
      url.searchParams.set('pending_invite_token', pendingInvite)
    }

    window.location.href = url.toString()
  }

  async function processPendingInvite() {
    if (!import.meta.client || !user.value) {
      return
    }

    const token = sessionStorage.getItem('pending_invite_token')
    if (!token) {
      return
    }

    sessionStorage.removeItem('pending_invite_token')

    const config = useRuntimeConfig()
    const apiUrl = config.public.apiUrl as string

    try {
      const response = await $fetch.raw<BolaoGroup | JoinRequest | { data: BolaoGroup | JoinRequest }>(
        `/api/groups/join/${token}`,
        {
          baseURL: apiUrl,
          method: 'POST',
          credentials: 'include',
          headers: { Accept: 'application/json' },
        },
      )

      const data = response._data
      const payload = data && typeof data === 'object' && 'data' in data
        ? (data as { data: BolaoGroup | JoinRequest }).data
        : data as BolaoGroup | JoinRequest

      const groupId = response.status === 202
        ? (payload as JoinRequest).group_id
        : (payload as BolaoGroup).id

      await navigateTo(`/grupos/${groupId}`)
    } catch (err: unknown) {
      console.error('[auth] processPendingInvite failed', err)
      sessionStorage.setItem('pending_invite_token', token)
    }
  }

  async function logout() {
    const config = useRuntimeConfig()
    const apiUrl = config.public.apiUrl as string

    try {
      await $fetch('/api/auth/logout', {
        method: 'POST',
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
    } finally {
      user.value = null
      initialized.value = true
      await navigateTo('/login')
    }
  }

  return {
    user,
    initialized,
    isAuthenticated,
    fetchUser,
    login,
    logout,
    processPendingInvite,
  }
})
