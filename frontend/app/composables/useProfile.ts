import type { ProfileStats } from '~/types/profile'
import { useAuthStore, type AuthUser } from '~/stores/auth'

const STAT_CARD = 'glass-card rounded-xl'

export function useProfile() {
  const config = useRuntimeConfig()
  const apiUrl = config.public.apiUrl as string
  const authStore = useAuthStore()

  const stats = ref<ProfileStats | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const savingName = ref(false)
  const nameError = ref<string | null>(null)
  const deactivating = ref(false)
  const deleting = ref(false)
  const accountError = ref<string | null>(null)

  async function fetchStats(_userId?: string) {
    loading.value = true
    error.value = null

    try {
      const response = await $fetch<ProfileStats>('/api/user/stats', {
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      stats.value = response
    } catch {
      error.value = 'Não foi possível carregar suas estatísticas.'
      stats.value = null
    } finally {
      loading.value = false
    }
  }

  async function updateName(name: string): Promise<boolean> {
    const trimmed = name.trim()
    if (trimmed.length < 2) {
      nameError.value = 'O nome deve ter pelo menos 2 caracteres.'
      return false
    }

    savingName.value = true
    nameError.value = null

    try {
      const response = await $fetch<{ data?: AuthUser } | AuthUser>('/api/user', {
        method: 'PATCH',
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
        body: { name: trimmed },
      })

      const updated = 'data' in response && response.data
        ? response.data
        : (response as AuthUser)

      if (authStore.user && updated) {
        authStore.user = { ...authStore.user, name: updated.name ?? trimmed }
      }
      return true
    } catch {
      nameError.value = 'Não foi possível salvar o nome.'
      return false
    } finally {
      savingName.value = false
    }
  }

  async function deactivateAccount(): Promise<boolean> {
    deactivating.value = true
    accountError.value = null

    try {
      await $fetch('/api/user/deactivate', {
        method: 'POST',
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      return true
    } catch {
      accountError.value = 'Não foi possível desativar a conta.'
      return false
    } finally {
      deactivating.value = false
    }
  }

  async function deleteAccount(): Promise<boolean> {
    deleting.value = true
    accountError.value = null

    try {
      await $fetch('/api/user', {
        method: 'DELETE',
        baseURL: apiUrl,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      return true
    } catch {
      accountError.value = 'Não foi possível excluir a conta.'
      return false
    } finally {
      deleting.value = false
    }
  }

  return {
    statCardClass: STAT_CARD,
    stats,
    loading,
    error,
    savingName,
    nameError,
    deactivating,
    deleting,
    accountError,
    fetchStats,
    updateName,
    deactivateAccount,
    deleteAccount,
  }
}
