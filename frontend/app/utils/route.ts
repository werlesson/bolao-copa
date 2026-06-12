/** Normalizes `/login/` → `/login` for route comparisons. */
export function normalizeRoutePath(path: string): string {
  if (path.length > 1 && path.endsWith('/')) {
    return path.slice(0, -1)
  }
  return path
}

export function isLoginRoute(path: string): boolean {
  return normalizeRoutePath(path) === '/login'
}

export function isJoinRoute(path: string): boolean {
  return normalizeRoutePath(path).startsWith('/join/')
}
