export default defineNuxtPlugin(async () => {
  const auth = useAuthStore()
  if (!auth.initialized) {
    await auth.fetchUser()
  }
  if (auth.isAuthenticated) {
    await auth.processPendingInvite()

    // Revalida a sessão periodicamente para detectar expiração sem reload manual.
    const REFRESH_MS = 5 * 60 * 1000
    setInterval(() => {
      if (document.visibilityState === 'visible') {
        void auth.fetchUser()
      }
    }, REFRESH_MS)
  }
})
