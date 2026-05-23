<script setup lang="ts">
const { isOnline } = useNetworkStatus()
</script>

<template>
  <div class="relative min-h-dvh bg-background pb-[calc(5.5rem+env(safe-area-inset-bottom))] text-on-surface">
    <Transition name="page-fade">
      <div
        v-if="!isOnline"
        class="fixed inset-x-0 top-0 z-50 flex items-center justify-center gap-2 border-b border-error/20 bg-surface-container-high py-2 px-4"
      >
        <span
          class="material-symbols-outlined text-[16px] text-error"
          style="font-variation-settings: 'FILL' 1"
        >wifi_off</span>
        <span class="font-label-caps text-label-caps uppercase tracking-widest text-error">Sem conexão</span>
      </div>
    </Transition>
    <slot />
    <UiBottomNav />
  </div>
</template>
