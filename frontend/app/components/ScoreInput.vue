<script setup lang="ts">
import { predictionPointsPreview } from '~/utils/scoring'

const homeScore = defineModel<number>('homeScore', { required: true })
const awayScore = defineModel<number>('awayScore', { required: true })

const props = withDefaults(
  defineProps<{
    disabled?: boolean
    saving?: boolean
    showSave?: boolean
  }>(),
  {
    disabled: false,
    saving: false,
    showSave: true,
  },
)

const emit = defineEmits<{ save: [] }>()

const MAX_SCORE = 20

const preview = computed(() =>
  predictionPointsPreview(homeScore.value, awayScore.value),
)

function decrement(side: 'home' | 'away') {
  if (props.disabled) return
  if (side === 'home' && homeScore.value > 0) homeScore.value -= 1
  if (side === 'away' && awayScore.value > 0) awayScore.value -= 1
}

function increment(side: 'home' | 'away') {
  if (props.disabled) return
  if (side === 'home' && homeScore.value < MAX_SCORE) homeScore.value += 1
  if (side === 'away' && awayScore.value < MAX_SCORE) awayScore.value += 1
}

function onSave() {
  if (props.disabled || props.saving) return
  emit('save')
}
</script>

<template>
  <div
    class="glass-card flex flex-col gap-6 overflow-hidden rounded-xl p-6"
    :class="{ 'pointer-events-none opacity-70': disabled }"
  >
    <!-- Score inputs -->
    <div class="flex items-center justify-center gap-6">
      <!-- Home -->
      <div class="flex flex-col items-center gap-2">
        <span class="font-label-caps text-label-caps text-on-surface-variant">CASA</span>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-surface-container transition-[background-color,transform] hover:bg-surface-container-high active:scale-95 disabled:opacity-50"
            :disabled="disabled || homeScore <= 0"
            aria-label="Diminuir gols do time mandante"
            @click="decrement('home')"
          >
            <span class="material-symbols-outlined text-[22px] text-primary">remove</span>
          </button>
          <input
            v-model.number="homeScore"
            type="number"
            inputmode="numeric"
            :disabled="disabled"
            aria-label="Gols do time mandante"
            class="font-display-lg text-display-lg h-16 w-20 rounded-lg border-b-2 border-primary/20 bg-surface-container-lowest text-center text-primary transition-[border-color,background-color] focus:border-primary focus:bg-surface-container focus:ring-0 focus:outline-none"
            :min="0"
            :max="MAX_SCORE"
          >
          <button
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-surface-container transition-[background-color,transform] hover:bg-surface-container-high active:scale-95 disabled:opacity-50"
            :disabled="disabled || homeScore >= MAX_SCORE"
            aria-label="Aumentar gols do time mandante"
            @click="increment('home')"
          >
            <span class="material-symbols-outlined text-[22px] text-primary">add</span>
          </button>
        </div>
      </div>

      <span class="font-headline-lg-mobile text-headline-lg-mobile pt-6 text-on-surface-variant">:</span>

      <!-- Away -->
      <div class="flex flex-col items-center gap-2">
        <span class="font-label-caps text-label-caps text-on-surface-variant">FORA</span>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-surface-container transition-[background-color,transform] hover:bg-surface-container-high active:scale-95 disabled:opacity-50"
            :disabled="disabled || awayScore <= 0"
            aria-label="Diminuir gols do time visitante"
            @click="decrement('away')"
          >
            <span class="material-symbols-outlined text-[22px] text-primary">remove</span>
          </button>
          <input
            v-model.number="awayScore"
            type="number"
            inputmode="numeric"
            :disabled="disabled"
            aria-label="Gols do time visitante"
            class="font-display-lg text-display-lg h-16 w-20 rounded-lg border-b-2 border-primary/20 bg-surface-container-lowest text-center text-primary transition-[border-color,background-color] focus:border-primary focus:bg-surface-container focus:ring-0 focus:outline-none"
            :min="0"
            :max="MAX_SCORE"
          >
          <button
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-surface-container transition-[background-color,transform] hover:bg-surface-container-high active:scale-95 disabled:opacity-50"
            :disabled="disabled || awayScore >= MAX_SCORE"
            aria-label="Aumentar gols do time visitante"
            @click="increment('away')"
          >
            <span class="material-symbols-outlined text-[22px] text-primary">add</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Points bento -->
    <div class="grid grid-cols-2 gap-3">
      <div class="glass-card flex flex-col justify-between rounded-xl border-l-4 border-secondary-container p-4">
        <span class="material-symbols-outlined mb-2 text-secondary-container">target</span>
        <div>
          <p class="font-headline-lg-mobile text-headline-lg-mobile text-secondary-container">
            {{ preview.exactLabel }}
          </p>
          <p class="font-label-caps text-label-caps opacity-70">PLACAR EXATO</p>
        </div>
      </div>
      <div class="glass-card flex flex-col justify-between rounded-xl border-l-4 border-tertiary p-4">
        <span class="material-symbols-outlined mb-2 text-tertiary">trophy</span>
        <div>
          <p class="font-headline-lg-mobile text-headline-lg-mobile text-tertiary">
            {{ preview.resultLabel }}
          </p>
          <p class="font-label-caps text-label-caps opacity-70">RESULTADO</p>
        </div>
      </div>
    </div>

    <!-- CTA -->
    <button
      v-if="showSave"
      type="button"
      class="font-label-caps text-label-caps w-full rounded-xl py-4 uppercase transition-[background-color,box-shadow,transform] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
      :class="disabled || saving
        ? 'bg-surface-container-highest text-on-surface-variant'
        : 'bg-primary text-on-primary shadow-[0_0_20px_rgba(101,223,118,0.3)] hover:shadow-[0_0_30px_rgba(101,223,118,0.5)]'"
      :disabled="disabled || saving"
      @click="onSave"
    >
      {{ saving ? 'SALVANDO…' : 'SALVAR PALPITE' }}
    </button>
  </div>
</template>
