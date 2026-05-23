/** One-time cleanup: old duplicate SW registrations break Vite HMR in dev. */
export default defineNuxtPlugin(async () => {
  if (!import.meta.dev || !('serviceWorker' in navigator)) return

  const flag = 'bolao-dev-sw-cleanup-done'
  if (sessionStorage.getItem(flag)) return

  const regs = await navigator.serviceWorker.getRegistrations()
  if (regs.length === 0) {
    sessionStorage.setItem(flag, '1')
    return
  }

  await Promise.all(regs.map(r => r.unregister()))
  sessionStorage.setItem(flag, '1')
  window.location.reload()
})
