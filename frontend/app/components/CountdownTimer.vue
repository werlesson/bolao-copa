<script setup lang="ts">
const props = defineProps<{
  startsAt: string
}>()

const TWENTY_FOUR_HOURS_MS = 24 * 60 * 60 * 1000

const showCountdown = computed(() => {
  const diff = new Date(props.startsAt).getTime() - Date.now()
  return diff > 0 && diff <= TWENTY_FOUR_HOURS_MS
})

const fixedScheduleLabel = computed(() => {
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'America/Sao_Paulo',
  }).format(new Date(props.startsAt))
})

const emit = defineEmits<{ kickoff: [] }>()

const { label, on } = useCountdown(() => props.startsAt)

on('kickoff', () => emit('kickoff'))
</script>

<template>
  <div
    v-if="showCountdown"
    class="inline-flex items-center gap-1.5 text-primary"
  >
    <span
      class="material-symbols-outlined text-[18px] leading-none"
      style="font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24"
    >schedule</span>
    <span class="font-label-caps text-label-caps uppercase">{{ label }}</span>
  </div>

  <span
    v-else
    class="font-label-caps text-label-caps uppercase text-on-surface-variant"
  >
    {{ fixedScheduleLabel }}
  </span>
</template>
