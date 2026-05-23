<script setup lang="ts">
import type { RankingEntry } from '~/types/ranking'
import { inviteUrlForToken } from '~/utils/group'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const { user } = useAuth()

const groupId = computed(() => String(route.params.id))

const {
  group, joinRequests, loading, error,
  fetchGroup, fetchGroupRanking, updateGroup, regenerateInvite,
  fetchJoinRequests, approveJoinRequest, rejectJoinRequest, leaveGroup,
} = useGroups()

const rankingEntries = ref<RankingEntry[]>([])
const rankingLoading = ref(false)
const savingSettings = ref(false)
const settingsError = ref<string | null>(null)
const settingsSaved = ref(false)
const requestBusyId = ref<string | null>(null)
const regenerating = ref(false)
const showSettingsSheet = ref(false)
const showLeaveDialog = ref(false)
const leaving = ref(false)
const memberSearch = ref('')

const requireApproval = ref(false)
const maxMembersInput = ref('')

const isOwner = computed(() => group.value?.is_owner ?? false)

const inviteUrl = computed(() => {
  const token = group.value?.invite_token
  if (!token) return ''
  return inviteUrlForToken(token)
})

const currentUserEntry = computed(() =>
  rankingEntries.value.find(e => e.user.id === user.value?.id) ?? null,
)

const filteredRankingEntries = computed(() => {
  const q = memberSearch.value.toLowerCase().trim()
  if (!q) return rankingEntries.value
  return rankingEntries.value.filter(e => e.user.name.toLowerCase().includes(q))
})

watch(group, (g) => {
  if (!g) return
  requireApproval.value = g.require_approval
  maxMembersInput.value = g.max_members != null ? String(g.max_members) : ''
}, { immediate: true })

async function loadPage() {
  await fetchGroup(groupId.value)
  rankingLoading.value = true
  rankingEntries.value = await fetchGroupRanking(groupId.value)
  rankingLoading.value = false
  if (group.value?.is_owner) await fetchJoinRequests(groupId.value)
}

onMounted(() => { loadPage() })
watch(groupId, () => { loadPage() })

function parsedMaxMembers(): number | null {
  const trimmed = maxMembersInput.value.trim()
  if (!trimmed) return null
  const parsed = Number.parseInt(trimmed, 10)
  return Number.isFinite(parsed) && parsed >= 2 ? parsed : null
}

async function saveSettings() {
  if (!group.value || !isOwner.value || savingSettings.value) return
  if (maxMembersInput.value.trim() && parsedMaxMembers() === null) {
    settingsError.value = 'Limite de membros deve ser um número inteiro ≥ 2.'
    return
  }
  savingSettings.value = true
  settingsError.value = null
  try {
    const updated = await updateGroup(group.value.id, {
      require_approval: requireApproval.value,
      max_members: parsedMaxMembers(),
    })
    group.value = updated
    settingsSaved.value = true
    setTimeout(() => { settingsSaved.value = false }, 2500)
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } })?.data
    settingsError.value = data?.message ?? 'Não foi possível salvar as configurações.'
    console.error('[grupos/id] update failed', err)
  } finally {
    savingSettings.value = false
  }
}

async function onRequireApprovalChange() { await saveSettings() }

async function onMaxMembersBlur() {
  if (!group.value) return
  const current = group.value.max_members ?? null
  const next = parsedMaxMembers()
  const inputEmpty = !maxMembersInput.value.trim()
  if (inputEmpty && current === null) return
  if (!inputEmpty && next === current) return
  await saveSettings()
}

async function onRegenerateInvite() {
  if (!group.value || !isOwner.value || regenerating.value) return
  regenerating.value = true
  try {
    const token = await regenerateInvite(group.value.id)
    group.value = { ...group.value, invite_token: token }
  } catch (err: unknown) {
    console.error('[grupos/id] regenerate failed', err)
  } finally {
    regenerating.value = false
  }
}

async function onApprove(requestId: string) {
  requestBusyId.value = requestId
  try {
    await approveJoinRequest(groupId.value, requestId)
    await fetchGroup(groupId.value)
    rankingEntries.value = await fetchGroupRanking(groupId.value)
  } finally {
    requestBusyId.value = null
  }
}

async function onReject(requestId: string) {
  requestBusyId.value = requestId
  try {
    await rejectJoinRequest(groupId.value, requestId)
  } finally {
    requestBusyId.value = null
  }
}

function isCurrentUser(userId: string): boolean {
  return user.value?.id === userId
}

function closeSettingsSheet() {
  showSettingsSheet.value = false
  settingsError.value = null
}

async function handleLeaveGroup() {
  if (!group.value || leaving.value) return
  leaving.value = true
  try {
    await leaveGroup(group.value.id)
    await navigateTo('/grupos', { replace: true })
  } catch (err: unknown) {
    console.error('[grupos/id] leave failed', err)
  } finally {
    leaving.value = false
  }
}
</script>

<template>
  <div class="pb-4">
    <UiSubPageHeader :title="group?.name?.toUpperCase() ?? 'GRUPO'" back-to="/grupos">
      <button
        v-if="isOwner && group"
        type="button"
        class="flex h-8 w-8 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-white/5"
        aria-label="Configurações do grupo"
        @click="showSettingsSheet = true"
      >
        <span class="material-symbols-outlined text-[22px] leading-none">settings</span>
      </button>
    </UiSubPageHeader>

    <p
      v-if="loading"
      class="px-margin-mobile pt-8 font-body-lg text-body-lg text-on-surface-variant"
    >
      Carregando…
    </p>

    <div v-else-if="error" class="space-y-3 px-margin-mobile pt-4">
      <p class="font-body-lg text-body-lg text-error">{{ error }}</p>
      <button
        type="button"
        class="font-label-caps text-label-caps flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container py-3 text-on-surface transition-colors hover:bg-surface-container-high"
        @click="loadPage"
      >
        <span class="material-symbols-outlined text-[18px]">refresh</span>
        TENTAR NOVAMENTE
      </button>
    </div>

    <div v-else-if="group" class="flex flex-col gap-stack-lg px-margin-mobile pt-4">

      <!-- Hero section -->
      <section class="diagonal-grad rounded-xl border border-white/10 p-4 space-y-3">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="font-headline-lg-mobile text-headline-lg-mobile truncate text-white">
              {{ group.name.toUpperCase() }}
            </h2>
            <p class="font-label-caps text-label-caps tracking-widest text-primary/80 uppercase">
              Bolão Copa 2026
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-1.5 rounded-full border border-white/5 bg-surface-container-highest/50 px-3 py-1">
            <span class="material-symbols-outlined text-[15px] text-secondary-container">group</span>
            <span class="font-label-caps text-label-caps text-on-surface">
              {{ group.members_count ?? 0 }}
            </span>
          </div>
        </div>

        <GroupInviteCard
          v-if="inviteUrl && isOwner"
          :invite-url="inviteUrl"
          :is-owner="isOwner"
          @regenerate="onRegenerateInvite"
        />
      </section>

      <!-- Join requests -->
      <section v-if="isOwner && joinRequests.length > 0">
        <div class="mb-3 flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px] text-secondary-container">pending</span>
          <h3 class="font-label-caps text-label-caps uppercase tracking-widest text-on-surface-variant">
            Solicitações
          </h3>
          <span class="font-label-caps text-[10px] text-secondary-container">{{ joinRequests.length }}</span>
        </div>
        <div class="flex flex-col gap-2">
          <GroupJoinRequestRow
            v-for="request in joinRequests"
            :key="request.id"
            :request="request"
            :busy="requestBusyId === request.id"
            @approve="onApprove(request.id)"
            @reject="onReject(request.id)"
          />
        </div>
      </section>

      <!-- Ranking section -->
      <section>
        <!-- Section header -->
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span
              class="material-symbols-outlined text-[20px] text-secondary-container"
              style="font-variation-settings: 'FILL' 1"
            >leaderboard</span>
            <h3 class="font-headline-lg-mobile text-headline-lg-mobile uppercase tracking-tighter text-on-surface">
              Classificação
            </h3>
          </div>
          <span class="font-label-caps text-[10px] text-on-surface-variant/40">
            {{ rankingEntries.length }} membro{{ rankingEntries.length !== 1 ? 's' : '' }}
          </span>
        </div>

        <template v-if="!rankingLoading && rankingEntries.length > 0">
          <!-- User position highlight card -->
          <div
            v-if="currentUserEntry"
            class="glass-card mb-4 rounded-xl border border-primary/20 p-4 neon-glow-green"
          >
            <p class="mb-2 font-label-caps text-[9px] uppercase tracking-widest text-primary/60">
              Sua posição
            </p>
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10">
                <span class="font-mono text-[18px] font-bold leading-none text-primary tabular-nums">
                  {{ currentUserEntry.position }}
                </span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate font-body-sm text-[14px] font-semibold text-on-surface">
                  {{ user?.name }}
                </p>
                <p class="font-label-caps text-[10px] text-on-surface-variant/50">
                  {{ currentUserEntry.exact_scores }} exato{{ currentUserEntry.exact_scores !== 1 ? 's' : '' }}
                  · {{ currentUserEntry.correct_results }} correto{{ currentUserEntry.correct_results !== 1 ? 's' : '' }}
                </p>
              </div>
              <div class="shrink-0 text-right">
                <p class="font-mono text-[22px] font-bold leading-none text-primary tabular-nums">
                  {{ currentUserEntry.total_points }}
                </p>
                <p class="font-label-caps text-[9px] text-on-surface-variant/50">pts</p>
              </div>
            </div>
          </div>

          <!-- Search (show only when list is long enough) -->
          <div v-if="rankingEntries.length > 6" class="relative mb-3">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] leading-none text-on-surface-variant/40">search</span>
            <input
              v-model="memberSearch"
              type="search"
              placeholder="Buscar membro…"
              class="w-full rounded-lg border border-white/10 bg-surface-container-high py-2 pl-9 pr-9 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/40 focus:border-primary/40 focus:outline-none"
            >
            <button
              v-if="memberSearch"
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2"
              @click="memberSearch = ''"
            >
              <span class="material-symbols-outlined text-[14px] leading-none text-on-surface-variant/40">close</span>
            </button>
          </div>
        </template>

        <p
          v-if="rankingLoading"
          class="font-body-lg text-body-lg text-on-surface-variant"
        >
          Carregando…
        </p>

        <div v-else-if="rankingEntries.length === 0" class="font-body-sm text-body-sm text-on-surface-variant/60">
          Nenhum membro no ranking ainda.
        </div>

        <div v-else-if="filteredRankingEntries.length === 0" class="py-6 text-center">
          <span class="material-symbols-outlined text-[28px] text-on-surface-variant/20">search_off</span>
          <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant/50">Nenhum membro encontrado.</p>
        </div>

        <div v-else class="space-y-2">
          <GroupMemberRow
            v-for="entry in filteredRankingEntries"
            :key="entry.user.id"
            :entry="entry"
            :is-current-user="isCurrentUser(entry.user.id)"
          />
        </div>

        <button
          v-if="!rankingLoading && rankingEntries.length > 0"
          type="button"
          class="font-label-caps text-label-caps mt-5 flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container py-3 text-on-surface-variant transition-colors hover:bg-surface-container-high"
          @click="navigateTo('/ranking')"
        >
          <span class="material-symbols-outlined text-[18px]">leaderboard</span>
          Ver classificação geral
        </button>
      </section>

      <!-- Leave group — visible only to non-owners -->
      <button
        v-if="!isOwner"
        type="button"
        :disabled="leaving"
        class="flex w-full items-center gap-3 rounded-xl border border-error/20 bg-error/5 px-4 py-3 transition-colors hover:bg-error/10 active:scale-[0.98] disabled:opacity-60"
        @click="showLeaveDialog = true"
      >
        <span class="material-symbols-outlined text-[20px] text-error/70">logout</span>
        <span class="font-label-caps text-label-caps text-error/90">
          {{ leaving ? 'Saindo…' : 'Sair do grupo' }}
        </span>
      </button>
    </div>

    <!-- Settings bottom sheet (owner only) -->
    <Teleport to="body">
      <Transition name="overlay-fade">
        <div
          v-if="showSettingsSheet"
          class="fixed inset-0 z-40 bg-black/60 backdrop-blur-[2px]"
          @click="closeSettingsSheet"
        />
      </Transition>

      <Transition name="sheet-up">
        <div
          v-if="showSettingsSheet"
          class="fixed bottom-0 left-0 right-0 z-50 rounded-t-2xl bg-surface-container"
          style="padding-bottom: env(safe-area-inset-bottom)"
        >
          <!-- Drag handle -->
          <div class="flex justify-center pb-2 pt-3">
            <div class="h-1 w-10 rounded-full bg-on-surface-variant/20" />
          </div>

          <!-- Header -->
          <div class="flex items-center justify-between px-margin pb-3 pt-1">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-[20px] text-on-surface-variant">settings</span>
              <span class="font-headline-lg-mobile text-headline-lg-mobile uppercase tracking-tighter text-on-surface">
                Configurações
              </span>
            </div>
            <button
              type="button"
              class="flex h-8 w-8 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-white/5"
              @click="closeSettingsSheet"
            >
              <span class="material-symbols-outlined text-[20px] leading-none">close</span>
            </button>
          </div>

          <!-- Settings content -->
          <div class="px-margin pb-8 space-y-6">
            <!-- Require approval toggle -->
            <div class="flex items-center justify-between gap-4">
              <div class="min-w-0">
                <p class="font-body-lg text-body-lg text-on-surface">Aprovar novos membros</p>
                <p class="font-body-sm text-[12px] text-on-surface-variant/60">
                  Membros precisam de aprovação para entrar
                </p>
              </div>
              <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                <input
                  v-model="requireApproval"
                  type="checkbox"
                  class="peer sr-only"
                  :disabled="savingSettings"
                  @change="onRequireApprovalChange"
                >
                <div
                  class="relative h-6 w-11 rounded-full bg-surface-container-highest after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-white/10 after:bg-on-surface after:transition-all after:content-[''] peer-checked:bg-secondary-container peer-checked:after:translate-x-5 peer-disabled:opacity-50"
                />
              </label>
            </div>

            <!-- Max members -->
            <div class="flex items-center justify-between gap-4">
              <div class="min-w-0">
                <p class="font-body-lg text-body-lg text-on-surface">Limite de membros</p>
                <p class="font-body-sm text-[12px] text-on-surface-variant/60">
                  Deixe em branco para sem limite
                </p>
              </div>
              <input
                v-model="maxMembersInput"
                type="number"
                min="2"
                inputmode="numeric"
                placeholder="∞"
                :disabled="savingSettings"
                class="w-20 rounded-lg border-b-2 border-outline-variant bg-surface-container-lowest px-2 py-1 text-center font-mono text-on-surface transition-all focus:border-primary focus:outline-none disabled:opacity-50"
                @blur="onMaxMembersBlur"
              >
            </div>

            <!-- Feedback -->
            <p v-if="settingsError" class="font-body-sm text-body-sm text-error">
              {{ settingsError }}
            </p>
            <p v-else-if="savingSettings" class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/40">
              Salvando…
            </p>
            <p v-else-if="settingsSaved" class="font-label-caps text-[10px] uppercase tracking-widest text-primary/70">
              Configuração salva
            </p>

          </div>
        </div>
      </Transition>
    </Teleport>

    <UiConfirmDialog
      :open="showLeaveDialog"
      title="Sair do grupo?"
      message="Você sairá do grupo e perderá seu histórico de pontuação neste bolão."
      confirm-label="Sair"
      cancel-label="Cancelar"
      danger
      @confirm="showLeaveDialog = false; handleLeaveGroup()"
      @cancel="showLeaveDialog = false"
    />
  </div>
</template>
