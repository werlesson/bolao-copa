export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.fetchUser()
  }

  if (auth.isAuthenticated) {
    if (shouldShowOnboarding(auth.user)) {
      return navigateTo('/onboarding')
    }
    return navigateTo('/jogos')
  }
})
