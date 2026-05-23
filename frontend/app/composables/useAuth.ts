import { storeToRefs } from 'pinia'
import { useAuthStore } from '~/stores/auth'

export function useAuth() {
  const store = useAuthStore()
  const { user, initialized, isAuthenticated } = storeToRefs(store)

  return {
    user,
    initialized,
    isAuthenticated,
    fetchUser: store.fetchUser,
    login: store.login,
    logout: store.logout,
    processPendingInvite: store.processPendingInvite,
  }
}
