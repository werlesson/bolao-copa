<script setup lang="ts">
defineProps<{
  total: number
  current: number
}>()
</script>

<template>
  <div class="flex items-center justify-center gap-2">
    <div
      v-for="index in total"
      :key="index"
      class="h-1.5 rounded-full transition-all duration-300"
      :class="index - 1 === current
        ? 'w-8 bg-primary-container'
        : 'w-1.5 bg-surface-container-highest'"
    />
  </div>
</template>
