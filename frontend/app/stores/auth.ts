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

const AUTH_SYNC_CHANNEL = 'bolao-auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const initialized = ref(false)
  let fetchUserPromise: Promise<void> | null = null
  let authChannel: BroadcastChannel | null = null
  let syncingFromPeer = false

  const isAuthenticated = computed(() => user.value !== null)

  function initAuthSync() {
    if (!import.meta.client || authChannel) return

    authChannel = new BroadcastChannel(AUTH_SYNC_CHANNEL)
    authChannel.onmessage = (event: MessageEvent<{ type?: string }>) => {
      const type = event.data?.type
      if (type === 'logout') {
        void syncLogoutFromPeer()
      } else if (type === 'login') {
        void fetchUser()
      }
    }
  }

  function notifyAuthPeers(type: 'login' | 'logout') {
    if (!import.meta.client || syncingFromPeer) return

    try {
      initAuthSync()
      authChannel?.postMessage({ type })
    } catch {
      // BroadcastChannel indisponível em alguns contextos.
    }
  }

  async function syncLogoutFromPeer() {
    if (syncingFromPeer) return

    syncingFromPeer = true
    try {
      user.value = null
      initialized.value = true

      const route = useRoute()
      if (route.path === '/login') return

      if (route.path.startsWith('/join/')) {
        await preserveJoinTokenAndRedirectToLogin('Sessão encerrada.')
        return
      }

      await navigateTo({
        path: '/login',
        query: { message: 'Sessão encerrada.' },
      })
    } finally {
      syncingFromPeer = false
    }
  }

  function preserveJoinInviteToken() {
    if (!import.meta.client) return

    const route = useRoute()
    const match = route.path.match(/^\/join\/([^/]+)/)
    if (match?.[1]) {
      sessionStorage.setItem('pending_invite_token', match[1])
    }
  }

  async function preserveJoinTokenAndRedirectToLogin(message: string) {
    preserveJoinInviteToken()
    await navigateTo({
      path: '/login',
      query: { message },
    })
  }

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
            await handleUnauthorized()
            return
          }

          if (!response.ok) {
            user.value = null
            return
          }

          if (response._data != null) {
            const wasLoggedOut = user.value === null
            user.value = unwrapUser(response._data)
            if (wasLoggedOut && user.value !== null) {
              notifyAuthPeers('login')
            }
          }
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

  let unauthorizedRedirect: Promise<void> | null = null

  /** Clears local auth state and sends the user to login (unless already there). */
  async function handleUnauthorized() {
    user.value = null
    initialized.value = true
    notifyAuthPeers('logout')

    if (!import.meta.client) return

    const route = useRoute()
    if (route.path === '/login') return

    if (route.path.startsWith('/join/')) {
      await preserveJoinTokenAndRedirectToLogin('Sessão expirada. Entre novamente.')
      return
    }

    if (!unauthorizedRedirect) {
      unauthorizedRedirect = (async () => {
        try {
          await navigateTo({
            path: '/login',
            query: { message: 'Sessão expirada. Entre novamente.' },
          })
        } finally {
          unauthorizedRedirect = null
        }
      })()
    }

    await unauthorizedRedirect
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
    notifyAuthPeers('logout')

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
    initAuthSync,
    fetchUser,
    handleUnauthorized,
    login,
    logout,
    processPendingInvite,
  }
})
