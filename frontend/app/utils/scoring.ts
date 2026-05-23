export type MatchResult = 'home' | 'away' | 'draw'

export function getMatchResult(home: number, away: number): MatchResult {
  if (home > away) return 'home'
  if (away > home) return 'away'
  return 'draw'
}

/** Mirrors backend ScoringService — used for live points preview when result is known. */
export function calculatePoints(
  predHome: number,
  predAway: number,
  realHome: number,
  realAway: number,
): number {
  if (predHome === realHome && predAway === realAway) return 3
  if (getMatchResult(predHome, predAway) === getMatchResult(realHome, realAway)) return 1
  return 0
}

export function predictionPointsPreview(home: number, away: number): {
  exactLabel: string
  resultLabel: string
} {
  const resultWord = home === away ? 'empate' : 'vencedor'
  return {
    exactLabel: 'Se acertar o placar exato: +3 pts',
    resultLabel: `Se acertar só o ${resultWord}: +1 pt`,
  }
}
