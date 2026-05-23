<script setup lang="ts">
defineProps<{
  title: string
  description: string
}>()
</script>

<template>
  <div class="flex w-full flex-col items-center text-center">
    <div class="mb-8 w-full">
      <slot />
    </div>
    <h2 class="mb-3 font-headline-lg-mobile text-headline-lg-mobile uppercase text-on-surface">
      {{ title }}
    </h2>
    <p class="max-w-sm text-body-lg text-on-surface-variant">
      {{ description }}
    </p>
  </div>
</template>
