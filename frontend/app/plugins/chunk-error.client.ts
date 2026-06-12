/** Recover from stale PWA caches pointing at deleted JS chunks after deploy. */
export default defineNuxtPlugin(() => {
  if (!import.meta.client) return

  const RELOAD_FLAG = 'bolao-chunk-reload-attempted'

  function isChunkLoadError(reason: unknown): boolean {
    const message = reason instanceof Error ? reason.message : String(reason ?? '')
    return /Failed to fetch dynamically imported module|Loading chunk \d+ failed|Importing a module script failed|error loading dynamically imported module/i.test(message)
  }

  function recoverFromStaleAssets(reason: unknown) {
    if (!isChunkLoadError(reason)) return

    if (sessionStorage.getItem(RELOAD_FLAG)) {
      console.error('[chunk-error] Stale assets after reload:', reason)
      return
    }

    sessionStorage.setItem(RELOAD_FLAG, '1')
    window.location.reload()
  }

  window.addEventListener('unhandledrejection', (event) => {
    if (isChunkLoadError(event.reason)) {
      event.preventDefault()
      recoverFromStaleAssets(event.reason)
    }
  })

  window.addEventListener('error', (event) => {
    if (event.message && isChunkLoadError(event.message)) {
      recoverFromStaleAssets(event.message)
    }
  })

  sessionStorage.removeItem(RELOAD_FLAG)
})
