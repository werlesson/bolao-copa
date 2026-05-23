<script setup lang="ts">
import { useRegisterSW } from 'virtual:pwa-register/vue'

const swUpdated = ref(false)

useRegisterSW({
  onUpdatedSW() {
    swUpdated.value = true
    setTimeout(() => { swUpdated.value = false }, 4500)
  },
})
</script>

<template>
  <NuxtPwaManifest />
  <NuxtRouteAnnouncer />
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>

  <!-- SW update toast -->
  <Transition name="page-fade">
    <div
      v-if="swUpdated"
      class="fixed bottom-[calc(5.5rem+env(safe-area-inset-bottom)+0.5rem)] left-1/2 z-50 -translate-x-1/2 whitespace-nowrap rounded-full border border-white/10 bg-surface-container-highest px-5 py-2.5 shadow-lg"
    >
      <span class="flex items-center gap-2 font-label-caps text-label-caps uppercase tracking-widest text-on-surface">
        <span
          class="material-symbols-outlined text-[16px] text-primary"
          style="font-variation-settings: 'FILL' 1"
        >check_circle</span>
        App atualizado
      </span>
    </div>
  </Transition>
</template>
