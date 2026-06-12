<script setup lang="ts">
import type { PredictionWithMatch } from '~/types/match'
import { flagUrl } from '~/types/match'
import { predictionOutcome } from '~/composables/usePredictionHistory'

const props = defineProps<{
  item: PredictionWithMatch
  compact?: boolean
}>()

const match = computed(() => props.item.match)
const isFinished = computed(() => match.value.status === 'FINISHED')
const isScheduled = computed(() => match.value.status === 'SCHEDULED')
const isLive = computed(() => match.value.status === 'LIVE')
const outcome = computed(() => predictionOutcome(props.item))

const KNOCKOUT_LABELS: Record<string, string> = {
  LAST_32: 'Rodada de 32',
  LAST_16: 'Oitavas',
  QUARTER_FINALS: 'Quartas',
  SEMI_FINALS: 'Semifinal',
  THIRD_PLACE: '3º Lugar',
  FINAL: 'Final',
}

const metaLabel = computed(() => {
  const parts: string[] = []

  if (match.value.group_name) {
    const label = match.value.stage === 'REGULAR_SEASON'
      ? `Rodada ${match.value.group_name}`
      : `Grupo ${match.value.group_name}`
    parts.push(label)
  } else if (KNOCKOUT_LABELS[match.value.stage]) {
    parts.push(KNOCKOUT_LABELS[match.value.stage]!)
  }

  const time = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'America/Sao_Paulo',
  }).format(new Date(match.value.starts_at))
  parts.push(time)

  return parts.join(' · ')
})

const hasRealScore = computed(() =>
  match.value.home_score !== null && match.value.away_score !== null,
)

const realScoreLabel = computed(() =>
  hasRealScore.value
    ? `${match.value.home_score}×${match.value.away_score}`
    : '—',
)

const predictionLabel = computed(
  () => `${props.item.home_score}×${props.item.away_score}`,
)

const pointsEarned = computed(() => props.item.points_earned)

const cardClass = computed(() => {
  if (!isFinished.value) {
    return 'border-white/10'
  }
  if (outcome.value === 'exact') {
    return 'border-l-4 border-l-primary neon-glow-green border-white/10'
  }
  if (outcome.value === 'partial') {
    return 'border-l-4 border-l-secondary-container/40 border-white/10'
  }
  return 'border-white/10'
})

const pointsBadgeClass = computed(() => {
  if (!isFinished.value || pointsEarned.value === null) return 'text-on-surface-variant'
  if (pointsEarned.value === 3) return 'text-primary'
  if (pointsEarned.value === 1) return 'text-secondary-container'
  return 'text-on-surface-variant/50'
})

const homeFlagSrc = computed(() => flagUrl(match.value.home_flag))
const awayFlagSrc = computed(() => flagUrl(match.value.away_flag))

function goToMatch() {
  navigateTo(`/jogos/${match.value.id}`)
}
</script>

<template>
  <article
    class="glass-card relative cursor-pointer overflow-hidden rounded-xl border p-4 transition-[border-color,box-shadow,transform] duration-200 hover:border-primary/20 active:scale-[0.98]"
    :class="[cardClass, compact ? 'p-3' : 'p-4']"
    role="button"
    tabindex="0"
    @click="goToMatch"
    @keydown.enter="goToMatch"
  >
    <template v-if="compact">
      <div class="flex items-center justify-between gap-3">
        <div class="min-w-0 flex-1">
          <p class="truncate font-body-sm text-body-sm font-semibold text-on-surface">
            {{ match.home_team }} × {{ match.away_team }}
          </p>
          <p class="mt-0.5 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/60">
            {{ metaLabel }}
          </p>
        </div>
        <div class="shrink-0 text-right">
          <p class="font-mono text-[11px] text-on-surface-variant/50">
            {{ realScoreLabel }}
          </p>
          <p class="font-mono text-[12px] font-bold text-on-surface">
            {{ predictionLabel }}
          </p>
          <p
            v-if="isFinished && pointsEarned !== null"
            class="font-mono text-[11px] font-bold"
            :class="pointsBadgeClass"
          >
            {{ pointsEarned > 0 ? `+${pointsEarned}` : '0' }} pt
          </p>
          <span
            v-else-if="isLive"
            class="font-label-caps text-[9px] uppercase tracking-widest text-primary"
          >Ao vivo</span>
          <span
            v-else
            class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/50"
          >Aguardando</span>
        </div>
      </div>
    </template>

    <template v-else>
      <p class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/60">
        {{ metaLabel }}
      </p>

      <div class="mb-4 grid grid-cols-3 items-center gap-3">
        <div class="flex min-w-0 flex-col items-center gap-1.5">
          <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-white/10 bg-surface-container-highest p-1.5">
            <img
              v-if="homeFlagSrc"
              :src="homeFlagSrc"
              :alt="match.home_team ?? ''"
              class="h-full w-full object-contain"
              loading="lazy"
            >
          </div>
          <span class="w-full truncate text-center text-[11px] font-bold uppercase text-on-surface">
            {{ match.home_team ?? '—' }}
          </span>
        </div>

        <div class="flex flex-col items-center gap-0.5">
          <span class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/40">
            Resultado
          </span>
          <span class="font-headline-lg-mobile text-headline-lg-mobile leading-none text-on-surface">
            {{ realScoreLabel }}
          </span>
        </div>

        <div class="flex min-w-0 flex-col items-center gap-1.5">
          <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-white/10 bg-surface-container-highest p-1.5">
            <img
              v-if="awayFlagSrc"
              :src="awayFlagSrc"
              :alt="match.away_team ?? ''"
              class="h-full w-full object-contain"
              loading="lazy"
            >
          </div>
          <span class="w-full truncate text-center text-[11px] font-bold uppercase text-on-surface">
            {{ match.away_team ?? '—' }}
          </span>
        </div>
      </div>

      <div class="flex items-center justify-between gap-3 border-t border-white/5 pt-3">
        <div>
          <p class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/50">
            Seu palpite
          </p>
          <p class="font-mono text-[15px] font-bold leading-none text-on-surface">
            {{ predictionLabel }}
          </p>
        </div>

        <div class="text-right">
          <template v-if="isFinished && pointsEarned !== null">
            <p class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/50">
              Pontos
            </p>
            <p class="font-mono text-[15px] font-bold leading-none" :class="pointsBadgeClass">
              {{ pointsEarned > 0 ? `+${pointsEarned}` : '0' }} pt
            </p>
          </template>
          <span
            v-else-if="isLive"
            class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1 font-label-caps text-[10px] uppercase tracking-widest text-primary"
          >
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary" />
            Ao vivo
          </span>
          <span
            v-else-if="isScheduled"
            class="inline-flex rounded-full border border-white/10 bg-surface-container px-2.5 py-1 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant"
          >
            Aguardando
          </span>
        </div>
      </div>
    </template>
  </article>
</template>
