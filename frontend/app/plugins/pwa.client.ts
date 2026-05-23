export async function requestPushPermission(): Promise<NotificationPermission> {
  if (!import.meta.client || !('Notification' in window)) return 'denied'
  if (Notification.permission === 'granted' || Notification.permission === 'denied') {
    return Notification.permission
  }
  return Notification.requestPermission()
}

export async function getServiceWorkerRegistration(): Promise<ServiceWorkerRegistration> {
  if (!import.meta.client || !('serviceWorker' in navigator)) {
    throw new Error('Service Worker não suportado.')
  }

  const nuxtApp = useNuxtApp()
  const fromPwa = nuxtApp.$pwa?.getSWRegistration?.()
  if (fromPwa?.active) return fromPwa

  await navigator.serviceWorker.ready
  const active = (await navigator.serviceWorker.getRegistrations()).find(r => r.active)
  if (active) return active

  throw new Error('Service worker não registrado. Recarregue a página.')
}

export default defineNuxtPlugin(() => {
  // In production, SW registration is handled by @vite-pwa/nuxt.
  // In dev, devOptions.enabled is false (viteEnvironmentApi breaks the middleware),
  // so we register public/sw.js manually. The SW only handles push — no precaching —
  // so it does not interfere with Vite HMR WebSocket traffic.
  if (import.meta.client && import.meta.dev && 'serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {})
  }

  return {
    provide: { requestPushPermission, getServiceWorkerRegistration },
  }
})
