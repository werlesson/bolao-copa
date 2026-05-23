export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.fetchUser()
  }

  if (!auth.isAuthenticated) {
    return navigateTo('/login')
  }

  if (to.path !== '/onboarding' && shouldShowOnboarding(auth.user)) {
    return navigateTo('/onboarding')
  }
})
