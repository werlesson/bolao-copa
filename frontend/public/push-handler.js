// Push notification handlers — imported by the workbox-generated SW in production.
// (In dev, public/sw.js has equivalent handlers inline.)

self.addEventListener('push', (event) => {
  const data = event.data?.json() ?? {}
  event.waitUntil(
    self.registration.showNotification(data.title ?? 'BolãoCopa', {
      body: data.body ?? '',
      icon: data.icon ?? '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
      data: { url: data.url ?? '/' },
    })
  )
})

function clientMatchesUrl(clientUrl, targetUrl) {
  try {
    const clientPath = new URL(clientUrl).pathname
    const targetPath = targetUrl.startsWith('http')
      ? new URL(targetUrl).pathname
      : targetUrl
    return clientPath === targetPath
  } catch {
    return clientUrl.includes(targetUrl)
  }
}

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const url = event.notification.data?.url ?? '/'
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
      for (const client of clientList) {
        if (clientMatchesUrl(client.url, url) && 'focus' in client) return client.focus()
      }
      return self.clients.openWindow(url)
    })
  )
})
