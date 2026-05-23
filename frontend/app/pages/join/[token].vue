<script setup lang="ts">
import type { InvitePreview } from '~/types/group'

definePageMeta({
  layout: 'minimal',
})

const route = useRoute()
const { initialized, isAuthenticated, fetchUser } = useAuth()
const { fetchInvitePreview, joinGroupByToken } = useGroups()

const token = computed(() => String(route.params.token))

const preview = ref<InvitePreview | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const joining = ref(false)
const joinError = ref<string | null>(null)

const isPending = computed(() => preview.value?.has_pending_request ?? false)

const joinButtonLabel = computed(() => {
  if (isPending.value) return 'Aguardando aprovação'
  if (preview.value?.require_approval) return 'Entrar'
  return 'Entrar'
})

const joinDisabled = computed(() =>
  joining.value
  || isPending.value
  || preview.value?.is_member === true,
)

async function loadPreview() {
  loading.value = true
  error.value = null
  try {
    preview.value = await fetchInvitePreview(token.value)
    if (preview.value.is_member) {
      await navigateTo(`/grupos/${preview.value.id}`, { replace: true })
    }
  } catch (err: unknown) {
    preview.value = null
    const status = (err as { statusCode?: number })?.statusCode
    error.value = status === 404
      ? 'Convite inválido ou expirado.'
      : 'Não foi possível carregar o convite.'
    console.error('[join] preview failed', err)
  } finally {
    loading.value = false
  }
}

async function handleJoin() {
  if (joinDisabled.value || !preview.value) return

  joining.value = true
  joinError.value = null

  try {
    const result = await joinGroupByToken(token.value)
    if (result.pending) {
      preview.value = {
        ...preview.value,
        has_pending_request: true,
      }
      return
    }
    await navigateTo(`/grupos/${result.groupId}`)
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } })?.data
    joinError.value = data?.message ?? 'Não foi possível entrar no grupo.'
    console.error('[join] join failed', err)
  } finally {
    joining.value = false
  }
}

onMounted(async () => {
  if (!initialized.value) {
    await fetchUser()
  }

  if (!isAuthenticated.value) {
    if (import.meta.client) {
      sessionStorage.setItem('pending_invite_token', token.value)
    }
    return navigateTo({
      path: '/login',
      query: { message: 'Faça login para entrar no grupo' },
    })
  }

  await loadPreview()
})
</script>

<template>
  <div class="flex min-h-dvh flex-col items-center justify-center bg-background px-margin py-8 text-on-surface">
    <div class="w-full max-w-sm">
      <div class="mb-8 flex flex-col items-center text-center">
        <span
          class="material-symbols-outlined mb-4 text-primary"
          style="font-size: 56px; font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 48"
        >group_add</span>
        <h1 class="font-headline-lg-mobile text-headline-lg-mobile uppercase text-on-surface">
          ENTRAR NO GRUPO
        </h1>
      </div>

      <p
        v-if="loading"
        class="text-center font-body-lg text-body-lg text-on-surface-variant"
      >
        Carregando convite…
      </p>

      <p
        v-else-if="error"
        class="text-center font-body-lg text-body-lg text-error"
      >
        {{ error }}
      </p>

      <div
        v-else-if="preview"
        class="glass-card rounded-xl p-6"
      >
        <p class="mb-1 text-center font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
          VOCÊ FOI CONVIDADO PARA
        </p>
        <h2 class="mb-6 text-center font-headline-lg-mobile text-headline-lg-mobile uppercase text-primary">
          {{ preview.name }}
        </h2>

        <p
          v-if="preview.require_approval && !isPending"
          class="mb-4 text-center font-body-sm text-body-sm text-on-surface-variant"
        >
          Este grupo exige aprovação do dono para novos membros.
        </p>

        <button
          type="button"
          :disabled="joinDisabled"
          class="flex h-12 w-full items-center justify-center rounded-xl bg-primary px-4 font-label-caps text-label-caps uppercase text-on-primary shadow-[0_0_20px_rgba(101,223,118,0.3)] transition-opacity hover:brightness-110 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
          @click="handleJoin"
        >
          {{ joining ? 'PROCESSANDO…' : joinButtonLabel.toUpperCase() }}
        </button>

        <p
          v-if="joinError"
          class="mt-3 text-center font-label-caps text-label-caps text-error"
        >
          {{ joinError }}
        </p>
      </div>
    </div>
  </div>
</template>
