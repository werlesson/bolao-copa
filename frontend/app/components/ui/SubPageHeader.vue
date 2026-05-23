<script setup lang="ts">
defineProps<{
  title: string
  backTo?: string
}>()
</script>

<template>
  <header class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-white/10 bg-surface/80 px-margin-mobile backdrop-blur-xl shadow-md shadow-primary/5">
    <div class="flex min-w-0 items-center gap-3">
      <button
        v-if="backTo"
        type="button"
        class="shrink-0 text-on-surface transition-transform duration-150 active:scale-95"
        aria-label="Voltar"
        @click="navigateTo(backTo)"
      >
        <span class="material-symbols-outlined">arrow_back</span>
      </button>
      <h1 class="min-w-0 truncate font-headline-lg-mobile text-headline-lg-mobile uppercase tracking-tighter text-on-surface">
        {{ title }}
      </h1>
    </div>
    <slot />
  </header>
</template>
