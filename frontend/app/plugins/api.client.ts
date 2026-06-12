/** Global 401 handler — keeps auth store in sync and unlocks /login after session expiry. */
export default defineNuxtPlugin(() => {
  const auth = useAuthStore()

  globalThis.$fetch = $fetch.create({
    onResponseError({ response, request }) {
      if (response.status !== 401) return

      const url = typeof request === 'string' ? request : request.toString()
      if (url.includes('/api/auth/google') || url.includes('/api/auth/logout')) return
      // Guest check on /api/user is expected on login — fetchUser handles it.
      if (url.includes('/api/user')) return

      void auth.handleUnauthorized()
    },
  })
})
