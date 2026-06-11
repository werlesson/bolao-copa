export function inviteUrlForToken(token: string): string {
  if (import.meta.client) {
    return `${window.location.origin}/join/${token}`
  }
  return `/join/${token}`
}
