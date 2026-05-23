import { storeToRefs } from 'pinia'
import type { AuthUser } from '~/stores/auth'
import { useAuthStore } from '~/stores/auth'

const STORAGE_KEY = 'onboarding_seen'

function readOnboardingSeen(): boolean {
  if (!import.meta.client) return false
  const value = localStorage.getItem(STORAGE_KEY)
  return value !== null && value !== ''
}

export function shouldShowOnboarding(user: AuthUser | null): boolean {
  if (!user) return false
  if (!readOnboardingSeen()) return true
  return !user.onboarding_done
}

export function useOnboarding() {
  const store = useAuthStore()
  const { user } = storeToRefs(store)
  const route = useRoute()

  const isReplay = computed(() => route.query.replay === '1')

  const needsOnboarding = computed(() => {
    if (isReplay.value) return true
    return shouldShowOnboarding(user.value)
  })

  async function completeOnboarding() {
    if (import.meta.client) {
      localStorage.setItem(STORAGE_KEY, '1')
    }

    const config = useRuntimeConfig()
    const apiUrl = config.public.apiUrl as string

    try {
      await $fetch('/api/user/onboarding', {
        method: 'POST',
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
    } catch (error) {
      console.error('[useOnboarding] completeOnboarding failed', error)
    }

    if (store.user) {
      store.user = { ...store.user, onboarding_done: true }
    }
  }

  function clearOnboardingSeen() {
    if (import.meta.client) {
      localStorage.removeItem(STORAGE_KEY)
    }
  }

  return {
    needsOnboarding,
    isReplay,
    completeOnboarding,
    clearOnboardingSeen,
  }
}
