export function unwrapList<T>(payload: T[] | { data: T[] }): T[] {
  if (Array.isArray(payload)) return payload
  return (payload as { data: T[] }).data ?? []
}

export function unwrapOne<T>(payload: T | { data: T }): T {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    return (payload as { data: T }).data
  }
  return payload as T
}
