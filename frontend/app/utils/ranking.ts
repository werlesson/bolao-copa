const MALE_NAMES_ENDING_A = new Set([
  'luca', 'joshua', 'nikola', 'noa', 'mica', 'barnabé', 'barnabe',
])

export function championLabel(name: string): 'CAMPEÃO' | 'CAMPEÃ' {
  const first = name.trim().split(/\s+/)[0]?.toLowerCase() ?? ''
  if (!first) return 'CAMPEÃO'
  if (MALE_NAMES_ENDING_A.has(first)) return 'CAMPEÃO'
  if (first.endsWith('a')) return 'CAMPEÃ'
  return 'CAMPEÃO'
}

export function shortDisplayName(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length <= 1) return parts[0] ?? name
  const lastInitial = parts[parts.length - 1]![0]
  return `${parts[0]} ${lastInitial}.`
}

export function positionMedal(position: number): string | null {
  if (position === 1) return '🥇'
  if (position === 2) return '🥈'
  if (position === 3) return '🥉'
  return null
}
