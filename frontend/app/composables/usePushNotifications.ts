import {
  getServiceWorkerRegistration,
  requestPushPermission,
} from '~/plugins/pwa.client'

export interface PushSubscriptionPayload {
  endpoint: string
  keys: {
    p256dh: string
    auth: string
  }
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = atob(base64)
  return Uint8Array.from([...rawData], char => char.charCodeAt(0))
}

function subscriptionToPayload(subscription: PushSubscription): PushSubscriptionPayload {
  const json = subscription.toJSON()
  if (!json.endpoint || !json.keys?.p256dh || !json.keys?.auth) {
    throw new Error('Dados de inscrição push incompletos.')
  }
  return {
    endpoint: json.endpoint,
    keys: {
      p256dh: json.keys.p256dh,
      auth: json.keys.auth,
    },
  }
}

export function usePushNotifications() {
  const config = useRuntimeConfig()
  const permission = ref<NotificationPermission>('default')
  const loading = ref(false)
  const error = ref<string | null>(null)
  const subscription = ref<PushSubscription | null>(null)

  const isSupported = computed(() => {
    if (!import.meta.client) return false
    return (
      'serviceWorker' in navigator
      && 'PushManager' in window
      && 'Notification' in window
    )
  })

  function syncPermission() {
    if (import.meta.client && 'Notification' in window) {
      permission.value = Notification.permission
    }
  }

  async function requestPermission(): Promise<NotificationPermission> {
    if (!isSupported.value) {
      error.value = 'Notificações não são suportadas neste navegador.'
      return 'denied'
    }

    error.value = null
    const result = await requestPushPermission()
    permission.value = result
    return result
  }

  function pushEnvironmentError(): string | null {
    if (!import.meta.client) return null
    if (!window.isSecureContext) {
      return 'Notificações push exigem HTTPS (ou acesse via http://localhost:3000).'
    }
    const host = window.location.hostname
    if (host === '127.0.0.1') {
      return 'Use http://localhost:3000 em vez de 127.0.0.1 — o Chrome costuma falhar no push com IP.'
    }
    return null
  }

  async function subscribe(): Promise<PushSubscription | null> {
    if (!isSupported.value) {
      error.value = 'Notificações não são suportadas neste navegador.'
      return null
    }

    const envErr = pushEnvironmentError()
    if (envErr) {
      error.value = envErr
      return null
    }

    const vapidKey = (config.public.vapidPublicKey as string) || ''
    if (!vapidKey) {
      error.value = 'Chave VAPID não configurada (NUXT_PUBLIC_VAPID_PUBLIC_KEY no .env).'
      return null
    }

    async function attemptSubscribe(reg: ServiceWorkerRegistration): Promise<PushSubscription> {
      const existing = await reg.pushManager.getSubscription()
      if (existing) {
        try { await existing.unsubscribe() } catch { /* non-fatal */ }
      }
      return reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidKey) as BufferSource,
      })
    }

    const registration = await getServiceWorkerRegistration()
    await navigator.serviceWorker.ready

    // Chrome may fail push registration until the page is controlled by the SW.
    if (!navigator.serviceWorker.controller) {
      await new Promise<void>((resolve) => {
        const timer = setTimeout(resolve, 3_000)
        navigator.serviceWorker.addEventListener('controllerchange', () => {
          clearTimeout(timer)
          resolve()
        }, { once: true })
      })
    }

    try {
      const current = await attemptSubscribe(registration)
      subscription.value = current
      return current
    }
    catch (firstErr) {
      if (!(firstErr instanceof Error) || firstErr.name !== 'AbortError') {
        throw firstErr
      }
      // "push service error" = FCM can't be reached — retrying with a fresh SW won't help.
      // Only retry for stale-channel AbortErrors (no "push service" in the message).
      if (firstErr.message.toLowerCase().includes('push service')) {
        throw firstErr
      }

      let freshReg: ServiceWorkerRegistration

      if (import.meta.dev) {
        const allRegs = await navigator.serviceWorker.getRegistrations().catch(() => [] as ServiceWorkerRegistration[])
        for (const r of allRegs) {
          await r.unregister().catch(() => {})
        }

        freshReg = await new Promise<ServiceWorkerRegistration>((resolve, reject) => {
          const timer = setTimeout(
            () => reject(new Error('Timeout ao reativar o service worker.')),
            10_000,
          )
          navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then((reg) => {
              if (reg.active) {
                clearTimeout(timer)
                resolve(reg)
                return
              }
              const sw = reg.installing ?? reg.waiting
              if (!sw) {
                clearTimeout(timer)
                resolve(reg)
                return
              }
              sw.addEventListener('statechange', function handler() {
                if (sw.state === 'activated') {
                  sw.removeEventListener('statechange', handler)
                  clearTimeout(timer)
                  resolve(reg)
                }
              })
            })
            .catch((e) => { clearTimeout(timer); reject(e) })
        })

        if (!navigator.serviceWorker.controller) {
          await new Promise<void>((resolve) => {
            const timer = setTimeout(resolve, 3_000)
            navigator.serviceWorker.addEventListener('controllerchange', () => {
              clearTimeout(timer)
              resolve()
            }, { once: true })
          })
        }
      } else {
        freshReg = await getServiceWorkerRegistration()
        await navigator.serviceWorker.ready
      }

      const current = await attemptSubscribe(freshReg)
      subscription.value = current
      return current
    }
  }

  async function save(pushSubscription?: PushSubscription | null): Promise<void> {
    const sub = pushSubscription ?? subscription.value
    if (!sub) {
      throw new Error('Nenhuma inscrição push para salvar.')
    }

    const payload = subscriptionToPayload(sub)
    const apiUrl = config.public.apiUrl as string

    await $fetch('/api/user/push-subscription', {
      method: 'PUT',
      baseURL: apiUrl,
      credentials: 'include',
      headers: { Accept: 'application/json' },
      body: payload,
    })

    const store = useAuthStore()
    if (store.user) {
      store.user = { ...store.user, has_push: true }
    }
  }

  async function unsubscribe(): Promise<void> {
    if (import.meta.client && 'serviceWorker' in navigator) {
      try {
        const registration = await getServiceWorkerRegistration()
        const current = await registration.pushManager.getSubscription()
        if (current) await current.unsubscribe()
      } catch {
        // SW might not be available; still remove from API
      }
    }

    subscription.value = null

    const apiUrl = config.public.apiUrl as string
    await $fetch('/api/user/push-subscription', {
      method: 'DELETE',
      baseURL: apiUrl,
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    const store = useAuthStore()
    if (store.user) {
      store.user = { ...store.user, has_push: false }
    }
  }

  async function subscribeAndSave(): Promise<boolean> {
    loading.value = true
    error.value = null

    try {
      const result = await requestPermission()

      if (result === 'denied') {
        error.value = 'Notificações bloqueadas. Permita nas configurações do navegador.'
        return false
      }

      if (result !== 'granted') {
        // User dismissed the dialog without deciding
        error.value = 'Permissão necessária para ativar as notificações.'
        return false
      }

      const sub = await subscribe()
      if (!sub) {
        if (!error.value) error.value = 'Não foi possível criar a inscrição push.'
        return false
      }

      await save(sub)
      return true
    }
    catch (e) {
      const msg = e instanceof Error ? e.message : String(e)
      const isPushServiceError = e instanceof Error
        && (e.name === 'AbortError' || msg.toLowerCase().includes('push service'))
      error.value = isPushServiceError
        ? 'O Chrome não conseguiu conectar ao serviço de push do Google (FCM). Desative VPN/proxy, abra chrome://gcm-internals/ (Connection State deve ser CONNECTED), teste em http://localhost:3000 ou use o Firefox.'
        : msg || 'Não foi possível ativar as notificações.'
      console.error('[push] subscribeAndSave failed:', e)
      return false
    }
    finally {
      loading.value = false
    }
  }

  async function unsubscribeAndRemove(): Promise<boolean> {
    loading.value = true
    error.value = null

    try {
      await unsubscribe()
      return true
    }
    catch (e) {
      const msg = e instanceof Error ? e.message : String(e)
      error.value = msg || 'Não foi possível desativar as notificações.'
      console.error('[push] unsubscribeAndRemove failed:', e)
      return false
    }
    finally {
      loading.value = false
    }
  }

  onMounted(syncPermission)

  return {
    permission,
    loading,
    error,
    subscription,
    isSupported,
    syncPermission,
    requestPermission,
    subscribe,
    save,
    unsubscribe,
    subscribeAndSave,
    unsubscribeAndRemove,
  }
}
