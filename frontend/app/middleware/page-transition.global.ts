import { normalizeRoutePath } from '~/utils/route'

const SHALLOW = new Set(['/', '/jogos', '/ranking', '/grupos', '/perfil', '/login', '/onboarding'])

function depth(path: string): number {
  const normalized = normalizeRoutePath(path)
  if (SHALLOW.has(normalized)) return 0
  return normalized.split('/').filter(Boolean).length
}

export default defineNuxtRouteMiddleware((to, from) => {
  const toPath = normalizeRoutePath(to.path)
  const fromPath = normalizeRoutePath(from.path)

  // Redirects de bootstrap/auth — `out-in` desmonta a página antes da próxima montar.
  if (
    fromPath === '/'
    || (toPath === '/login' && fromPath !== '/login')
    || (toPath === '/onboarding' && fromPath !== '/onboarding')
  ) {
    to.meta.pageTransition = false
    from.meta.pageTransition = false
    return
  }

  const d = depth(to.path) - depth(from.path)
  to.meta.pageTransition = {
    name: d > 0 ? 'slide-left' : d < 0 ? 'slide-right' : 'page-fade',
    mode: 'out-in',
  }
  from.meta.pageTransition = to.meta.pageTransition
})
