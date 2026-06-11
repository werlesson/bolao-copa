<script setup lang="ts">
import type { Match, UserPrediction } from '~/types/match'
import { flagUrl, isMatchTbd } from '~/types/match'

const props = defineProps<{
  match: Match
  prediction?: UserPrediction | null
  resultsMode?: boolean
}>()

const localStatus = ref(props.match.status)

watch(
  () => props.match.status,
  (status) => { localStatus.value = status },
)

const isTbd = computed(() => isMatchTbd(props.match))
const isLive = computed(() => localStatus.value === 'LIVE')
const isFinished = computed(() => localStatus.value === 'FINISHED')
const isScheduled = computed(() => localStatus.value === 'SCHEDULED')
const isInactive = computed(() => ['POSTPONED', 'CANCELLED'].includes(localStatus.value))

const KNOCKOUT_LABELS: Record<string, string> = {
  LAST_32: 'Rodada de 32',
  LAST_16: 'Oitavas',
  QUARTER_FINALS: 'Quartas',
  SEMI_FINALS: 'Semifinal',
  THIRD_PLACE: '3º Lugar',
  FINAL: 'Final',
}

const timeLabel = computed(() => {
  const parts: string[] = []

  if (props.match.group_name) {
    const label = props.match.stage === 'REGULAR_SEASON'
      ? `Rodada ${props.match.group_name}`
      : `Grupo ${props.match.group_name}`
    parts.push(label)
  } else if (KNOCKOUT_LABELS[props.match.stage]) {
    parts.push(KNOCKOUT_LABELS[props.match.stage]!)
  }

  if (isScheduled.value || isLive.value || (isFinished.value && props.resultsMode)) {
    const time = new Intl.DateTimeFormat('pt-BR', {
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'America/Sao_Paulo',
    }).format(new Date(props.match.starts_at))
    parts.push(time)
  }
  return parts.join(' • ')
})

const hasScore = computed(() =>
  props.match.home_score !== null && props.match.away_score !== null,
)

const scoreDisplay = computed(() => {
  if (hasScore.value && (isLive.value || isFinished.value)) {
    return `${props.match.home_score} - ${props.match.away_score}`
  }
  if (isFinished.value) return '—'
  return 'VS'
})

const homeWon = computed(() =>
  isFinished.value && hasScore.value && props.match.home_score! > props.match.away_score!,
)
const awayWon = computed(() =>
  isFinished.value && hasScore.value && props.match.away_score! > props.match.home_score!,
)
const showStatusBadge = computed(() =>
  !(props.resultsMode && isFinished.value),
)

const predictionLabel = computed(() => {
  if (!props.prediction) return null
  return `${props.prediction.home_score}×${props.prediction.away_score}`
})

const pointsEarned = computed(() => props.prediction?.points_earned ?? null)
const homeFlagSrc = computed(() => flagUrl(props.match.home_flag))
const awayFlagSrc = computed(() => flagUrl(props.match.away_flag))
const showPalpitar = computed(() => isScheduled.value && !isTbd.value)

function largeFlagUrl(flag: string | null): string | null {
  if (!flag?.trim()) return null
  const v = flag.trim()
  if (v.startsWith('http://') || v.startsWith('https://')) return v
  return `https://flagcdn.com/w320/${v.toLowerCase()}.png`
}

const bgFlag = computed((): { src: string; side: 'home' | 'away' } | null => {
  if (isFinished.value) {
    const h = props.match.home_score
    const a = props.match.away_score
    if (h === null || a === null || h === a) return null
    if (h > a) {
      const src = largeFlagUrl(props.match.home_flag)
      return src ? { src, side: 'home' } : null
    }
    const src = largeFlagUrl(props.match.away_flag)
    return src ? { src, side: 'away' } : null
  }
  const isBrazil = (name: string | null) =>
    !!name && ['brazil', 'brasil'].includes(name.toLowerCase())
  if (isBrazil(props.match.home_team)) return { src: 'https://flagcdn.com/w320/br.png', side: 'home' }
  if (isBrazil(props.match.away_team)) return { src: 'https://flagcdn.com/w320/br.png', side: 'away' }
  return null
})

function handleKickoff() {
  if (localStatus.value === 'SCHEDULED') localStatus.value = 'LIVE'
}

function goToMatch() {
  navigateTo(`/jogos/${props.match.id}`)
}
</script>

<template>
  <article
    class="glass-card relative overflow-hidden cursor-pointer rounded-xl border border-white/10 p-5 transition-[border-color,box-shadow,transform] duration-200 hover:border-primary/20 active:scale-[0.98]"
    :class="[
      isLive ? 'neon-glow-green' : '',
      resultsMode && isFinished ? 'border-white/5' : '',
    ]"
    role="button"
    :tabindex="0"
    @click="goToMatch"
    @keydown.enter="goToMatch"
  >
    <!-- Flag background: winner for finished, Brazil for upcoming -->
    <img
      v-if="bgFlag"
      :src="bgFlag.src"
      aria-hidden="true"
      class="pointer-events-none absolute inset-y-0 h-full w-[60%] select-none object-cover opacity-[0.11]"
      :class="bgFlag.side === 'home' ? 'left-0 object-left' : 'right-0 object-right'"
      :style="bgFlag.side === 'home'
        ? 'mask-image: linear-gradient(to right, black 0%, black 25%, transparent 85%); -webkit-mask-image: linear-gradient(to right, black 0%, black 25%, transparent 85%);'
        : 'mask-image: linear-gradient(to left, black 0%, black 25%, transparent 85%); -webkit-mask-image: linear-gradient(to left, black 0%, black 25%, transparent 85%);'"
    >

    <!-- Content above the flag layer -->
    <div class="relative">
    <!-- Top bar -->
    <div class="mb-4 flex items-start justify-between gap-2">
      <template v-if="resultsMode && isFinished">
        <span
          v-if="timeLabel"
          class="font-label-caps text-label-caps text-on-surface-variant/70"
        >{{ timeLabel }}</span>
      </template>
      <template v-else>
        <div class="min-w-0">
          <template v-if="isScheduled && !isTbd && !isInactive">
            <span class="font-label-caps text-label-caps inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-primary">
              <CountdownTimer
                :starts-at="match.starts_at"
                @kickoff="handleKickoff"
              />
            </span>
          </template>
          <template v-else-if="showStatusBadge">
            <MatchStatusBadge
              :status="localStatus"
              :match="match"
            />
          </template>
        </div>
        <span
          v-if="timeLabel"
          class="font-label-caps text-label-caps shrink-0 text-on-surface-variant"
        >
          {{ timeLabel }}
        </span>
      </template>
    </div>

    <!-- Teams & score -->
    <div class="mb-4 grid grid-cols-3 items-center gap-4">
      <div class="flex min-w-0 flex-col items-center gap-2">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-white/10 bg-surface-container-highest p-2">
          <img
            v-if="homeFlagSrc"
            :src="homeFlagSrc"
            :alt="`Bandeira ${match.home_team}`"
            width="40"
            height="40"
            loading="lazy"
            class="h-full w-full object-contain"
          >
          <span v-else class="font-label-caps text-label-caps text-on-surface-variant">?</span>
        </div>
        <span class="min-w-0 w-full truncate text-center text-[13px] font-bold uppercase font-sans"
          :class="homeWon ? 'text-primary' : awayWon ? 'text-on-surface-variant/45' : 'text-on-surface'"
        >
          {{ match.home_team ?? '—' }}
        </span>
      </div>

      <div class="flex flex-col items-center gap-1">
        <span
          class="font-headline-lg text-headline-lg leading-none tracking-tighter"
          :class="isLive
            ? 'text-primary'
            : isFinished
              ? (resultsMode ? 'text-on-surface' : 'text-on-surface-variant/60')
              : 'text-primary/20'"
        >
          {{ scoreDisplay }}
        </span>
        <span
          v-if="isLive"
          class="font-label-caps text-label-caps animate-pulse text-primary"
        >
          AO VIVO
        </span>
      </div>

      <div class="flex min-w-0 flex-col items-center gap-2">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-white/10 bg-surface-container-highest p-2">
          <img
            v-if="awayFlagSrc"
            :src="awayFlagSrc"
            :alt="`Bandeira ${match.away_team}`"
            width="40"
            height="40"
            loading="lazy"
            class="h-full w-full object-contain"
          >
          <span v-else class="font-label-caps text-label-caps text-on-surface-variant">?</span>
        </div>
        <span class="min-w-0 w-full truncate text-center text-[13px] font-bold uppercase font-sans"
          :class="awayWon ? 'text-primary' : homeWon ? 'text-on-surface-variant/45' : 'text-on-surface'"
        >
          {{ match.away_team ?? '—' }}
        </span>
      </div>
    </div>

    <!-- Bottom action row -->
    <div class="mt-3 border-t border-white/5 pt-2.5">

      <!-- Scheduled: palpitar / editar button + badge if has prediction -->
      <template v-if="showPalpitar">
        <div v-if="predictionLabel" class="flex items-center justify-between gap-2">
          <span class="inline-flex items-center gap-1.5 rounded-full border border-secondary-container/20 bg-secondary-container/10 px-2.5 py-1">
            <span class="font-label-caps text-[10px] uppercase tracking-widest text-secondary-container/60">Palpite</span>
            <span class="font-mono text-[12px] font-bold leading-none text-secondary-container">{{ predictionLabel }}</span>
          </span>
          <button
            type="button"
            class="font-label-caps text-label-caps flex items-center gap-1.5 rounded-lg border border-white/10 bg-surface-container px-3 py-2 text-on-surface-variant transition-[background-color,transform] duration-150 active:scale-95"
            @click.stop="goToMatch"
          >
            <span class="material-symbols-outlined text-[16px]">edit</span>
            EDITAR
          </button>
        </div>
        <button
          v-else
          type="button"
          class="font-label-caps text-label-caps flex w-full items-center justify-center gap-2 rounded-lg bg-primary py-3 text-on-primary shadow-lg shadow-primary/20 transition-[background-color,transform] duration-150 active:scale-95"
          @click.stop="goToMatch"
        >
          <span
            class="material-symbols-outlined text-[18px]"
            style="font-variation-settings: 'FILL' 1"
          >edit_square</span>
          PALPITAR
        </button>
      </template>

      <!-- TBD -->
      <button
        v-else-if="isTbd && !isInactive"
        type="button"
        disabled
        class="font-label-caps text-label-caps flex w-full cursor-not-allowed items-center justify-center rounded-lg border border-white/10 bg-surface-container px-3 py-3 uppercase text-on-surface-variant opacity-60"
        @click.stop
      >
        Times ainda não definidos
      </button>

      <!-- Live / finished with prediction: badge + points -->
      <div v-else-if="predictionLabel" class="flex items-center justify-between">
        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-surface-container px-2.5 py-1">
          <span class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/60">Palpite</span>
          <span
            class="font-mono text-[12px] font-bold leading-none"
            :class="isFinished ? 'text-on-surface/60' : 'text-secondary-container/75'"
          >{{ predictionLabel }}</span>
        </span>
        <div v-if="pointsEarned !== null && isFinished" class="flex items-center gap-1.5">
          <span
            v-if="pointsEarned > 0"
            class="material-symbols-outlined text-[14px] leading-none text-secondary-container"
            style="font-variation-settings: 'FILL' 1"
          >stars</span>
          <span
            class="font-mono font-bold leading-none"
            :class="[
              pointsEarned > 0 ? 'text-secondary-container' : 'text-on-surface-variant/40',
              resultsMode ? 'text-[14px]' : 'text-[11px]',
            ]"
          >{{ pointsEarned > 0 ? `+${pointsEarned}` : '0' }} pt</span>
        </div>
      </div>

      <!-- Live / finished, no prediction -->
      <div v-else-if="isLive || isFinished" class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 rounded-full bg-on-surface-variant/20" />
          <span class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/35">Sem palpite</span>
        </div>
        <span
          v-if="resultsMode && isFinished"
          class="material-symbols-outlined text-[16px] leading-none text-on-surface-variant/30"
        >chevron_right</span>
      </div>

    </div>
    </div><!-- end .relative content wrapper -->
  </article>
</template>
