<script setup lang="ts">
defineProps<{
  visible: boolean
  message: string
}>()
</script>

<template>
  <Teleport to="body">
    <Transition name="toast-slide">
      <div
        v-if="visible"
        class="fixed bottom-24 left-1/2 z-[70] max-w-[calc(100vw-2rem)] -translate-x-1/2 px-1"
      >
        <div class="neon-glow-green flex items-center gap-2.5 rounded-full border border-primary/25 bg-surface-container-high px-4 py-2.5 shadow-lg shadow-black/40">
          <span
            class="material-symbols-outlined text-[18px] leading-none text-primary"
            style="font-variation-settings: 'FILL' 1"
          >check_circle</span>
          <span class="font-label-caps text-label-caps text-on-surface whitespace-normal text-center">{{ message }}</span>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
