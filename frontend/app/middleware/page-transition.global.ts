const SHALLOW = new Set(['/', '/jogos', '/ranking', '/grupos', '/perfil', '/login', '/onboarding'])

function depth(path: string): number {
  if (SHALLOW.has(path)) return 0
  return path.split('/').filter(Boolean).length
}

export default defineNuxtRouteMiddleware((to, from) => {
  const d = depth(to.path) - depth(from.path)
  to.meta.pageTransition = {
    name: d > 0 ? 'slide-left' : d < 0 ? 'slide-right' : 'page-fade',
    mode: 'out-in',
  }
  from.meta.pageTransition = to.meta.pageTransition
})
