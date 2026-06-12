<script setup lang="ts">
const props = defineProps<{
  error: {
    statusCode?: number
    statusMessage?: string
    message?: string
  }
}>()

const message = computed(() =>
  props.error?.message
  ?? props.error?.statusMessage
  ?? 'Algo deu errado ao carregar o app.',
)

function reload() {
  window.location.reload()
}
</script>

<template>
  <div class="flex min-h-dvh flex-col items-center justify-center gap-4 bg-background px-6 text-center text-on-surface">
    <span
      class="material-symbols-outlined text-[48px] text-primary"
      style="font-variation-settings: 'FILL' 1"
    >error</span>
    <h1 class="font-headline-lg-mobile text-headline-lg-mobile uppercase tracking-tight">
      Ops!
    </h1>
    <p class="max-w-sm font-body-sm text-body-sm text-on-surface-variant">
      {{ message }}
    </p>
    <button
      type="button"
      class="rounded-lg bg-primary px-5 py-2.5 font-label-caps text-label-caps uppercase tracking-widest text-on-primary"
      @click="reload"
    >
      Recarregar
    </button>
  </div>
</template>
