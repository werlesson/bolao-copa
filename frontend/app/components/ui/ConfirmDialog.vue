<script setup lang="ts">
defineProps<{
  open: boolean
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  danger?: boolean
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4"
      role="dialog"
      :aria-modal="true"
      :aria-labelledby="'confirm-title'"
    >
      <div
        class="absolute inset-0 bg-background/80 backdrop-blur-sm"
        aria-hidden="true"
        @click="emit('cancel')"
      />
      <div class="glass-card relative z-10 w-full max-w-sm rounded-xl p-6 shadow-2xl">
        <h2
          id="confirm-title"
          class="mb-2 font-headline-lg-mobile text-headline-lg-mobile uppercase text-on-surface"
        >
          {{ title }}
        </h2>
        <p class="mb-6 font-body-lg text-body-lg text-on-surface-variant">
          {{ message }}
        </p>
        <div class="flex gap-3">
          <button
            type="button"
            class="flex-1 rounded-xl border border-white/10 py-2.5 font-label-caps text-label-caps uppercase text-on-surface-variant transition-colors hover:bg-surface-container-high active:scale-[0.98]"
            @click="emit('cancel')"
          >
            {{ cancelLabel ?? 'Cancelar' }}
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl py-2.5 font-label-caps text-label-caps uppercase transition-all hover:opacity-90 active:scale-[0.98]"
            :class="danger
              ? 'bg-error text-on-error'
              : 'bg-primary text-on-primary shadow-[0_0_15px_rgba(101,223,118,0.3)]'"
            @click="emit('confirm')"
          >
            {{ confirmLabel ?? 'Confirmar' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
