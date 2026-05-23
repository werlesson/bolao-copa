<script setup lang="ts">
defineProps<{
  subtitle?: string
}>()
</script>

<template>
  <div class="relative min-w-0 overflow-hidden rounded-xl">
    <div class="pointer-events-none min-w-0 opacity-40">
      <slot />
    </div>

    <div
      class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 px-4 py-6 bg-surface/40 backdrop-blur-sm"
      aria-hidden="true"
    >
      <span
        class="material-symbols-outlined text-[48px] text-on-surface-variant"
        style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24"
      >lock</span>
      <p class="font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
        PALPITES ENCERRADOS
      </p>
      <p
        v-if="subtitle"
        class="text-center font-body-sm text-body-sm text-on-surface-variant"
      >
        {{ subtitle }}
      </p>
    </div>
  </div>
</template>
