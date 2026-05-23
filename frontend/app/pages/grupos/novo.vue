<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
  layout: 'minimal',
})

const { createGroup } = useGroups()

const name = ref('')
const requireApproval = ref(false)
const maxMembersInput = ref('')
const submitting = ref(false)
const submitError = ref<string | null>(null)

const maxMembers = computed(() => {
  const trimmed = String(maxMembersInput.value).trim()
  if (!trimmed) return null
  const parsed = Number.parseInt(trimmed, 10)
  return Number.isFinite(parsed) && parsed >= 2 ? parsed : null
})

const canSubmit = computed(() => name.value.trim().length > 0 && !submitting.value)

async function handleSubmit() {
  if (!canSubmit.value) return

  if (String(maxMembersInput.value).trim() && maxMembers.value === null) {
    submitError.value = 'Limite de membros deve ser um número inteiro ≥ 2.'
    return
  }

  submitting.value = true
  submitError.value = null

  try {
    const group = await createGroup({
      name: name.value.trim(),
      require_approval: requireApproval.value,
      max_members: maxMembers.value,
    })
    await navigateTo(`/grupos/${group.id}`)
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } })?.data
    submitError.value = data?.message ?? 'Não foi possível criar o grupo.'
    console.error('[grupos/novo] create failed', err)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-background text-on-surface">
    <UiSubPageHeader title="CRIAR GRUPO" back-to="/grupos" />

    <main class="mx-auto w-full max-w-lg px-margin-mobile pb-8 pt-6">
      <p class="mb-stack-lg font-body-lg text-body-lg text-on-surface-variant">
        Configuração do grupo
      </p>

      <form
        class="space-y-stack-lg"
        @submit.prevent="handleSubmit"
      >
        <!-- Fields -->
        <div class="space-y-stack-md">
          <!-- Name -->
          <div class="space-y-1">
            <label
              for="group-name"
              class="font-label-caps text-label-caps px-1 uppercase text-on-surface-variant"
            >
              Nome do Grupo
            </label>
            <input
              id="group-name"
              v-model="name"
              type="text"
              required
              maxlength="255"
              placeholder="Ex: Galera do Escritório 2026"
              class="font-body-lg text-body-lg w-full rounded-t-lg border-b-2 border-outline-variant bg-surface-container-low px-4 py-3 text-on-surface placeholder:text-on-surface-variant/50 transition-[border-color,background-color] focus:border-primary focus:bg-surface-container focus:outline-none"
            >
          </div>

          <!-- Privacy / Approval -->
          <div class="space-y-3">
            <label class="font-label-caps text-label-caps px-1 uppercase text-on-surface-variant">
              Aprovação de Membros
            </label>
            <div class="grid grid-cols-2 gap-4">
              <label class="relative cursor-pointer">
                <input
                  v-model="requireApproval"
                  :value="false"
                  type="radio"
                  name="approval"
                  class="sr-only"
                >
                <div
                  class="glass-card flex flex-col items-center gap-2 rounded-xl border p-4 transition-[border-color,background-color]"
                  :class="!requireApproval ? 'border-primary bg-primary/5' : 'border-white/10'"
                >
                  <span
                    class="material-symbols-outlined transition-colors"
                    :class="!requireApproval ? 'text-primary' : 'text-on-surface-variant'"
                    style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24"
                  >public</span>
                  <span class="font-label-caps text-label-caps uppercase text-on-surface">Livre</span>
                </div>
              </label>
              <label class="relative cursor-pointer">
                <input
                  v-model="requireApproval"
                  :value="true"
                  type="radio"
                  name="approval"
                  class="sr-only"
                >
                <div
                  class="glass-card flex flex-col items-center gap-2 rounded-xl border p-4 transition-[border-color,background-color]"
                  :class="requireApproval ? 'border-primary bg-primary/5' : 'border-white/10'"
                >
                  <span
                    class="material-symbols-outlined transition-colors"
                    :class="requireApproval ? 'text-primary' : 'text-on-surface-variant'"
                  >lock</span>
                  <span class="font-label-caps text-label-caps uppercase text-on-surface">Aprovação</span>
                </div>
              </label>
            </div>
          </div>

          <!-- Max members -->
          <div class="space-y-1">
            <label
              for="max-members"
              class="font-label-caps text-label-caps px-1 uppercase text-on-surface-variant"
            >
              Limite de Membros
            </label>
            <input
              id="max-members"
              v-model="maxMembersInput"
              type="number"
              min="2"
              inputmode="numeric"
              placeholder="Sem limite"
              class="font-body-lg text-body-lg w-full rounded-t-lg border-b-2 border-outline-variant bg-surface-container-low px-4 py-3 font-mono text-on-surface placeholder:text-on-surface-variant/50 transition-[border-color,background-color] focus:border-primary focus:bg-surface-container focus:outline-none"
            >
            <p class="font-label-caps text-label-caps px-1 text-on-surface-variant">
              Deixe em branco para sem limite
            </p>
          </div>
        </div>

        <p
          v-if="submitError"
          class="font-body-sm text-body-sm text-error"
        >
          {{ submitError }}
        </p>

        <!-- Actions -->
        <div class="space-y-4 pt-2">
          <button
            type="submit"
            :disabled="!canSubmit"
            class="font-label-caps text-label-caps w-full rounded-xl py-4 uppercase tracking-wider transition-[background-color,transform] active:scale-95 disabled:opacity-50"
            :class="canSubmit
              ? 'bg-primary text-on-primary shadow-[0_0_20px_rgba(101,223,118,0.3)] hover:brightness-110'
              : 'bg-surface-container-highest text-on-surface-variant'"
          >
            {{ submitting ? 'CRIANDO…' : 'CRIAR GRUPO' }}
          </button>
        </div>
      </form>

      <!-- Info card -->
      <div class="mt-stack-lg relative overflow-hidden rounded-xl border border-secondary-container/25 bg-secondary-container/10 p-4">
        <div class="flex gap-3">
          <span class="material-symbols-outlined shrink-0 text-[22px] text-secondary-container">info</span>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            <span class="text-secondary-container font-bold">INFO:</span> Grupos 'Livre' permitem entrada automática. Grupos 'Aprovação' requerem que você aceite novos membros.
          </p>
        </div>
      </div>
    </main>
  </div>
</template>
