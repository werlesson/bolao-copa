<script setup lang="ts">
const props = defineProps<{ phase: string }>()

const phaseLabels: Record<string, string> = {
  GROUP_STAGE: 'Fase de Grupos',
  ROUND_OF_16: 'Oitavas de Final',
  QUARTER_FINALS: 'Quartas de Final',
  SEMI_FINALS: 'Semifinais',
  FINAL: 'Final',
}

const label = computed(() => phaseLabels[props.phase] ?? props.phase)
</script>

<template>
  <div class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1.5">
    <span
      class="material-symbols-outlined text-[14px] leading-none text-primary"
      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24"
    >sports_soccer</span>
    <span class="font-label-caps text-label-caps uppercase tracking-widest text-primary">
      {{ label }}
    </span>
  </div>
</template>
