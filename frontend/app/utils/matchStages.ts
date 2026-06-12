const STAGE_ALIASES: Record<string, string> = {
  REGULAR_SEASON: 'GROUP_STAGE',
  LAST_16: 'ROUND_OF_16',
}

export const STAGE_ORDER: Record<string, number> = {
  GROUP_STAGE: 0,
  LAST_32: 1,
  ROUND_OF_16: 2,
  QUARTER_FINALS: 3,
  SEMI_FINALS: 4,
  THIRD_PLACE: 5,
  FINAL: 6,
}

export const STAGE_LABELS: Record<string, string> = {
  GROUP_STAGE: 'Grupos',
  LAST_32: 'Rodada 32',
  ROUND_OF_16: 'Oitavas',
  QUARTER_FINALS: 'Quartas',
  SEMI_FINALS: 'Semifinal',
  THIRD_PLACE: '3º Lugar',
  FINAL: 'Final',
}

export function normalizeStage(stage: string | null | undefined): string {
  if (!stage) return 'GROUP_STAGE'
  return STAGE_ALIASES[stage] ?? stage
}

export function stageLabel(stage: string): string {
  const normalized = normalizeStage(stage)
  return STAGE_LABELS[normalized] ?? stage
}

export function isGroupStage(stage: string): boolean {
  const normalized = normalizeStage(stage)
  return normalized === 'GROUP_STAGE'
}
