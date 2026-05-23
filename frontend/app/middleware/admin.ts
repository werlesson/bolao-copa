export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.fetchUser()
  }

  if (!auth.user?.is_admin) {
    return navigateTo('/jogos')
  }
})
