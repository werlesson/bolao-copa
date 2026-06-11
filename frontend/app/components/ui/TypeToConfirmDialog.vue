<script setup lang="ts">
const props = defineProps<{
  open: boolean
  title: string
  message: string
  confirmPhrase: string
  confirmLabel?: string
  cancelLabel?: string
  danger?: boolean
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

const input = ref('')

const canConfirm = computed(() => input.value.trim() === props.confirmPhrase)

watch(() => props.open, (isOpen) => {
  if (!isOpen) input.value = ''
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[100] flex items-center justify-center p-4"
      role="dialog"
      :aria-modal="true"
      :aria-labelledby="'type-confirm-title'"
    >
      <div
        class="absolute inset-0 bg-background/80 backdrop-blur-sm"
        aria-hidden="true"
        @click="emit('cancel')"
      />
      <div class="glass-card relative z-10 w-full max-w-sm rounded-xl p-6 shadow-2xl">
        <h2
          id="type-confirm-title"
          class="mb-2 font-headline-lg-mobile text-headline-lg-mobile uppercase text-on-surface"
        >
          {{ title }}
        </h2>
        <p class="mb-4 font-body-lg text-body-lg text-on-surface-variant">
          {{ message }}
        </p>
        <p class="mb-2 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/60">
          Digite <span class="text-on-surface">{{ confirmPhrase }}</span> para confirmar
        </p>
        <input
          v-model="input"
          type="text"
          autocomplete="off"
          class="mb-6 w-full rounded-lg border border-white/10 bg-surface-container-low px-3 py-2.5 font-body-lg text-body-lg text-on-surface placeholder:text-on-surface-variant/40 focus:border-error/40 focus:outline-none"
          :placeholder="confirmPhrase"
        >
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
            :disabled="!canConfirm"
            class="flex-1 rounded-xl py-2.5 font-label-caps text-label-caps uppercase transition-all hover:opacity-90 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
            :class="danger
              ? 'bg-error text-on-error'
              : 'bg-primary text-on-primary shadow-[0_0_15px_rgba(101,223,118,0.3)]'"
            @click="canConfirm && emit('confirm')"
          >
            {{ confirmLabel ?? 'Confirmar' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
