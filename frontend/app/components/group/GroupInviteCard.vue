<script setup lang="ts">
const props = defineProps<{
  inviteUrl: string
  isOwner: boolean
}>()

const emit = defineEmits<{
  regenerate: []
}>()

const copied = ref(false)
const showRegenerateDialog = ref(false)
let copiedTimer: ReturnType<typeof setTimeout> | undefined

const inviteCode = computed(() => {
  try {
    const url = new URL(props.inviteUrl)
    const parts = url.pathname.split('/')
    return parts[parts.length - 1]?.toUpperCase() ?? props.inviteUrl
  } catch {
    return props.inviteUrl
  }
})

async function copyLink() {
  try {
    await navigator.clipboard.writeText(props.inviteUrl)
    copied.value = true
    clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    // fallback
  }
}

function onRegenerate() {
  if (!props.isOwner) return
  showRegenerateDialog.value = true
}

function confirmRegenerate() {
  showRegenerateDialog.value = false
  emit('regenerate')
}

onUnmounted(() => {
  clearTimeout(copiedTimer)
})
</script>

<template>
  <section class="space-y-3 rounded-xl border border-white/10 bg-surface-container-highest/40 p-4">
    <!-- Code display -->
    <div>
      <span class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant">Link de Convite</span>
      <p class="mt-1 min-w-0 truncate font-mono text-[13px] font-bold text-secondary-container">
        {{ inviteUrl }}
      </p>
    </div>

    <!-- Actions -->
    <div class="flex gap-2">
      <button
        type="button"
        class="font-label-caps text-label-caps flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-on-primary transition-all duration-150 active:scale-95"
        @click="copyLink"
      >
        <span class="material-symbols-outlined text-[18px]">{{ copied ? 'check' : 'share' }}</span>
        {{ copied ? 'COPIADO!' : 'COMPARTILHAR' }}
      </button>
      <button
        v-if="isOwner"
        type="button"
        class="flex items-center justify-center rounded-lg border border-white/10 bg-surface-container-highest/50 px-3 py-2.5 text-on-surface-variant transition-colors hover:text-on-surface active:scale-[0.98]"
        :title="'Regenerar link'"
        @click="onRegenerate"
      >
        <span class="material-symbols-outlined text-[18px]">refresh</span>
      </button>
    </div>
  </section>

  <UiConfirmDialog
    :open="showRegenerateDialog"
    title="Regenerar link de convite"
    message="O link atual será invalidado. Quem tiver o link antigo não conseguirá mais entrar."
    confirm-label="Regenerar"
    cancel-label="Cancelar"
    danger
    @confirm="confirmRegenerate"
    @cancel="showRegenerateDialog = false"
  />
</template>
