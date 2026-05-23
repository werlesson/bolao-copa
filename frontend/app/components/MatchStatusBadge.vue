<script setup lang="ts">
import type { MatchStatus } from '~/types/match'
import { isMatchTbd, type Match } from '~/types/match'

const props = defineProps<{
  status: MatchStatus
  match?: Match
}>()

const isTbd = computed(() => (props.match ? isMatchTbd(props.match) : false))
</script>

<template>
  <span
    v-if="isTbd"
    class="inline-flex items-center rounded-full bg-surface-container-high px-3 py-1 font-label-caps text-label-caps uppercase text-on-surface-variant"
  >
    Times a definir
  </span>

  <span
    v-else-if="status === 'LIVE'"
    class="inline-flex items-center gap-1.5 rounded-full bg-primary px-3 py-1 font-label-caps text-label-caps uppercase tracking-widest text-on-primary"
  >
    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-on-primary animate-pulse" />
    AO VIVO
  </span>

  <span
    v-else-if="status === 'FINISHED'"
    class="inline-flex items-center rounded-full bg-surface-container-high px-3 py-1 font-label-caps text-label-caps uppercase text-on-surface-variant"
  >
    Encerrado
  </span>

  <span
    v-else-if="status === 'POSTPONED'"
    class="inline-flex items-center rounded-full bg-error/20 px-3 py-1 font-label-caps text-label-caps uppercase text-error"
  >
    Adiado
  </span>

  <span
    v-else-if="status === 'CANCELLED'"
    class="inline-flex items-center rounded-full bg-error/20 px-3 py-1 font-label-caps text-label-caps uppercase text-error"
  >
    Cancelado
  </span>
</template>
