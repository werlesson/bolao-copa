<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    points: number
    showIcon?: boolean
  }>(),
  { showIcon: true },
)

const suffix = computed(() => (props.points === 1 ? 'pt' : 'pts'))
const prefix = computed(() => (props.points >= 0 ? '+' : ''))
</script>

<template>
  <div class="inline-flex items-center gap-1 rounded-[6px] bg-success-bg px-2 py-0.5">
    <span
      v-if="showIcon"
      class="material-symbols-outlined text-[14px] leading-none text-success"
      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24"
    >stars</span>
    <span class="font-mono text-label-sm leading-none text-success">
      {{ prefix }}{{ points }} {{ suffix }}
    </span>
  </div>
</template>
