export default defineNuxtPlugin(async () => {
  const auth = useAuthStore()
  if (!auth.initialized) {
    await auth.fetchUser()
  }
  if (auth.isAuthenticated) {
    await auth.processPendingInvite()
  }
})
