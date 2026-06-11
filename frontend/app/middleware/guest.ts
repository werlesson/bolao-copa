export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()

  // Always revalidate on login — evita estado stale após expiração da sessão.
  await auth.fetchUser()

  if (auth.isAuthenticated) {
    if (shouldShowOnboarding(auth.user)) {
      return navigateTo('/onboarding')
    }
    return navigateTo('/jogos')
  }
})
