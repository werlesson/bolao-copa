<script setup lang="ts">
import type { GroupMember, UpdateGroupPayload } from '~/types/group'
import type { RankingEntry } from '~/types/ranking'
import { inviteUrlForToken } from '~/utils/group'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const { user } = useAuth()

const groupId = computed(() => String(route.params.id))

const { recordGroupView } = useRankingPrefs()

const {
  group, joinRequests, loading, error,
  fetchGroup, fetchGroupRanking, updateGroup, regenerateInvite,
  fetchJoinRequests, approveJoinRequest, rejectJoinRequest, leaveGroup, deleteGroup, removeMember,
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
const showDeleteDialog = ref(false)
const showDeleteConfirm = ref(false)
const leaving = ref(false)
const deleting = ref(false)
const removingMember = ref(false)
const memberSearch = ref('')
const selectedMember = ref<RankingEntry | null>(null)
const showRemoveMemberDialog = ref(false)
const removeMemberError = ref<string | null>(null)
const { visible: toastVisible, message: toastMessage, show: showToast } = useToast()

const groupNameInput = ref('')
const requireApproval = ref(false)
const maxMembersInput = ref('')

const isOwner = computed(() => group.value?.is_owner ?? false)

const deleteConfirmMessage = computed(() =>
  `O grupo "${group.value?.name ?? ''}" será excluído permanentemente.`,
)

const inviteUrl = computed(() => {
  const token = group.value?.invite_token
  if (!token) return ''
  return inviteUrlForToken(token)
})

const currentUserEntry = computed(() =>
  rankingEntries.value.find(e => e.user.id === user.value?.id) ?? null,
)

const rankingByUserId = computed(() => {
  const map = new Map<string, RankingEntry>()
  for (const entry of rankingEntries.value) {
    map.set(entry.user.id, entry)
  }
  return map
})

const members = computed(() => {
  const list = group.value?.members ?? []
  return [...list].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'))
})

const isSearchingMembers = computed(() => memberSearch.value.trim().length > 0)

const filteredMembers = computed(() => {
  const q = memberSearch.value.toLowerCase().trim()
  if (!q) return members.value
  return members.value.filter(m => m.name.toLowerCase().includes(q))
})

function memberInitial(name: string): string {
  const first = name.trim().charAt(0)
  if (!first) return '#'
  const upper = first.toUpperCase()
  return /[A-ZÀ-Ý]/i.test(upper) ? upper.normalize('NFD').replace(/\p{M}/gu, '') : '#'
}

const pinnedMember = computed(() => {
  if (isSearchingMembers.value) return null
  return members.value.find(m => isCurrentUser(m.id)) ?? null
})

const browsableMembers = computed(() => {
  if (isSearchingMembers.value) return filteredMembers.value
  return filteredMembers.value.filter(m => !isCurrentUser(m.id))
})

const groupedMembers = computed(() => {
  if (isSearchingMembers.value) return []

  const groups = new Map<string, GroupMember[]>()
  for (const member of browsableMembers.value) {
    const letter = memberInitial(member.name)
    const bucket = groups.get(letter) ?? []
    bucket.push(member)
    groups.set(letter, bucket)
  }

  return [...groups.entries()]
    .sort(([a], [b]) => {
      if (a === '#') return 1
      if (b === '#') return -1
      return a.localeCompare(b, 'pt-BR')
    })
    .map(([letter, list]) => ({ letter, list }))
})

function rankingPositionFor(userId: string): number | null {
  const position = rankingByUserId.value.get(userId)?.position
  return position && position > 0 ? position : null
}

watch(group, (g) => {
  if (!g) return
  groupNameInput.value = g.name
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

onMounted(() => {
  recordGroupView(groupId.value)
  loadPage()
})
watch(groupId, (id) => {
  recordGroupView(id)
  loadPage()
})

function goToGroupRanking() {
  recordGroupView(groupId.value)
  navigateTo(`/ranking?tab=${groupId.value}`)
}

function parsedMaxMembers(): number | null {
  const trimmed = maxMembersInput.value.trim()
  if (!trimmed) return null
  const parsed = Number.parseInt(trimmed, 10)
  return Number.isFinite(parsed) && parsed >= 2 ? parsed : null
}

async function saveSettings(extra?: UpdateGroupPayload): Promise<boolean> {
  if (!group.value || !isOwner.value || savingSettings.value) return false
  if (maxMembersInput.value.trim() && parsedMaxMembers() === null) {
    settingsError.value = 'Limite de membros deve ser um número inteiro ≥ 2.'
    return false
  }
  savingSettings.value = true
  settingsError.value = null
  try {
    const updated = await updateGroup(group.value.id, {
      require_approval: requireApproval.value,
      max_members: parsedMaxMembers(),
      ...extra,
    })
    group.value = {
      ...updated,
      members: updated.members ?? group.value.members,
    }
    groupNameInput.value = updated.name
    settingsSaved.value = true
    setTimeout(() => { settingsSaved.value = false }, 2500)
    return true
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } })?.data
    settingsError.value = data?.message ?? 'Não foi possível salvar as configurações.'
    if (group.value) groupNameInput.value = group.value.name
    console.error('[grupos/id] update failed', err)
    return false
  } finally {
    savingSettings.value = false
  }
}

async function onGroupNameBlur() {
  if (!group.value) return
  const trimmed = groupNameInput.value.trim()
  if (trimmed === group.value.name) return
  if (trimmed.length < 3) {
    settingsError.value = 'Nome deve ter pelo menos 3 caracteres.'
    groupNameInput.value = group.value.name
    return
  }
  groupNameInput.value = trimmed
  if (await saveSettings({ name: trimmed })) {
    showToast(`Grupo renomeado para "${trimmed}".`)
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
    await fetchGroup(groupId.value, { silent: true })
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

async function handleDeleteGroup() {
  if (!group.value || deleting.value) return
  deleting.value = true
  try {
    await deleteGroup(group.value.id)
    showDeleteConfirm.value = false
    showDeleteDialog.value = false
    closeSettingsSheet()
    await navigateTo('/grupos', { replace: true })
  } catch (err: unknown) {
    console.error('[grupos/id] delete failed', err)
  } finally {
    deleting.value = false
  }
}

function rankingEntryForMember(member: GroupMember): RankingEntry {
  const existing = rankingByUserId.value.get(member.id)
  if (existing) return existing

  return {
    position: 0,
    total_points: 0,
    exact_scores: 0,
    correct_results: 0,
    total_predictions: 0,
    user: {
      id: member.id,
      name: member.name,
      avatar_url: member.avatar_url,
    },
  }
}

function openMemberSheet(member: GroupMember) {
  selectedMember.value = rankingEntryForMember(member)
  removeMemberError.value = null
}

function closeMemberSheet() {
  selectedMember.value = null
  removeMemberError.value = null
}

function requestRemoveMember() {
  if (!selectedMember.value) return
  showRemoveMemberDialog.value = true
}

function cancelRemoveMember() {
  showRemoveMemberDialog.value = false
}

async function handleRemoveMember() {
  const target = selectedMember.value
  if (!group.value || !target || removingMember.value) return
  removingMember.value = true
  removeMemberError.value = null
  try {
    await removeMember(group.value.id, target.user.id)
    showRemoveMemberDialog.value = false
    closeMemberSheet()
    showToast(`${target.user.name} foi removido do grupo.`)
    await fetchGroup(groupId.value, { silent: true })
    rankingEntries.value = await fetchGroupRanking(groupId.value)
  } catch (err: unknown) {
    const data = (err as { data?: { message?: string } })?.data
    const message = data?.message ?? 'Não foi possível remover o membro.'
    removeMemberError.value = message
    showToast(message)
    showRemoveMemberDialog.value = false
    console.error('[grupos/id] remove member failed', err)
  } finally {
    removingMember.value = false
  }
}

const canRemoveSelectedMember = computed(() => {
  if (!isOwner.value || !selectedMember.value) return false
  return !isCurrentUser(selectedMember.value.user.id)
})

function closeMemberSheetAndGoToRanking() {
  closeMemberSheet()
  goToGroupRanking()
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

      <!-- Members section -->
      <section class="glass-card rounded-xl border border-white/10 p-4">
        <div class="mb-4 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px] text-secondary-container">group</span>
            <h3 class="font-headline-lg-mobile text-headline-lg-mobile uppercase tracking-tighter text-on-surface">
              Membros
            </h3>
          </div>
          <span class="rounded-full bg-surface-container-highest px-2.5 py-1 font-label-caps text-[10px] text-on-surface-variant/60">
            {{ members.length }}
          </span>
        </div>

        <div
          v-if="!loading && members.length > 0"
          class="mb-4 overflow-hidden rounded-xl border border-white/10 bg-surface-container-low/50"
        >
          <div class="flex items-stretch">
            <div
              v-if="currentUserEntry && !rankingLoading"
              class="min-w-0 flex-1 px-4 py-3.5"
            >
              <p class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/45">
                Sua posição
              </p>
              <p class="mt-1 font-mono text-[30px] font-bold leading-none tabular-nums text-primary">
                {{ currentUserEntry.position }}º
              </p>
              <p class="mt-1.5 font-body-sm text-[12px] text-on-surface-variant/55">
                {{ currentUserEntry.total_points }} pts
                <span class="text-on-surface-variant/30">·</span>
                {{ currentUserEntry.exact_scores }} {{ currentUserEntry.exact_scores === 1 ? 'exato' : 'exatos' }}
              </p>
            </div>
            <div
              v-else-if="rankingLoading"
              class="min-w-0 flex-1 px-4 py-3.5"
            >
              <p class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/45">
                Classificação
              </p>
              <p class="mt-2 font-body-sm text-[12px] text-on-surface-variant/45">Carregando posição…</p>
            </div>
            <div
              v-else
              class="flex min-w-0 flex-1 items-center px-4 py-3.5"
            >
              <p class="font-body-sm text-[12px] text-on-surface-variant/55">
                Acompanhe a pontuação de todos no ranking do grupo.
              </p>
            </div>

            <button
              type="button"
              class="flex w-[108px] shrink-0 flex-col items-center justify-center gap-1.5 border-l border-white/10 bg-secondary-container/10 px-3 transition-colors hover:bg-secondary-container/15 active:scale-[0.98]"
              @click="goToGroupRanking"
            >
              <span
                class="material-symbols-outlined text-[22px] text-secondary-container"
                style="font-variation-settings: 'FILL' 1"
              >leaderboard</span>
              <span class="text-center font-label-caps text-[9px] uppercase leading-tight text-on-surface-variant/70">
                Ver ranking
              </span>
            </button>
          </div>
        </div>

        <div v-if="members.length >= 4 || isSearchingMembers" class="relative mb-4">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] leading-none text-on-surface-variant/40">search</span>
          <input
            v-model="memberSearch"
            type="search"
            placeholder="Buscar por nome…"
            class="w-full rounded-xl border border-white/10 bg-surface-container-high/80 py-2.5 pl-9 pr-9 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/40 focus:border-primary/40 focus:outline-none"
          >
          <button
            v-if="memberSearch"
            type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-0.5 hover:bg-white/5"
            aria-label="Limpar busca"
            @click="memberSearch = ''"
          >
            <span class="material-symbols-outlined text-[14px] leading-none text-on-surface-variant/40">close</span>
          </button>
        </div>

        <p
          v-if="isSearchingMembers && filteredMembers.length > 0"
          class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/40"
        >
          {{ filteredMembers.length }} resultado{{ filteredMembers.length !== 1 ? 's' : '' }}
        </p>

        <div v-if="loading" class="space-y-2 py-1">
          <div
            v-for="i in 5"
            :key="i"
            class="flex items-center gap-3 rounded-xl px-1 py-2"
          >
            <div class="h-10 w-10 shrink-0 animate-pulse rounded-full bg-white/5" />
            <div class="flex-1 space-y-2">
              <div class="h-3 w-32 animate-pulse rounded bg-white/5" />
              <div class="h-2.5 w-20 animate-pulse rounded bg-white/5" />
            </div>
          </div>
        </div>

        <div
          v-else-if="members.length === 0"
          class="flex flex-col items-center py-10 text-center"
        >
          <span class="material-symbols-outlined text-[36px] text-on-surface-variant/15">groups</span>
          <p class="mt-3 font-body-sm text-body-sm text-on-surface-variant/55">
            Nenhum membro neste grupo ainda.
          </p>
        </div>

        <div
          v-else-if="filteredMembers.length === 0"
          class="flex flex-col items-center py-10 text-center"
        >
          <span class="material-symbols-outlined text-[36px] text-on-surface-variant/15">search_off</span>
          <p class="mt-3 font-body-sm text-body-sm text-on-surface-variant/55">
            Nenhum membro encontrado para “{{ memberSearch.trim() }}”.
          </p>
        </div>

        <div v-else class="space-y-4">
          <div v-if="pinnedMember">
            <p class="mb-2 px-1 font-label-caps text-[10px] uppercase tracking-widest text-primary/70">
              Você
            </p>
            <GroupMemberRow
              :member="pinnedMember"
              is-current-user
              :is-group-owner="group?.owner_id === pinnedMember.id"
              :ranking-position="rankingPositionFor(pinnedMember.id)"
              interactive
              @select="openMemberSheet(pinnedMember)"
            />
          </div>

          <template v-if="isSearchingMembers">
            <div class="space-y-1">
              <GroupMemberRow
                v-for="member in filteredMembers"
                :key="member.id"
                :member="member"
                :is-current-user="isCurrentUser(member.id)"
                :is-group-owner="group?.owner_id === member.id"
                :ranking-position="rankingPositionFor(member.id)"
                compact
                interactive
                @select="openMemberSheet(member)"
              />
            </div>
          </template>

          <template v-else>
            <div
              v-for="section in groupedMembers"
              :key="section.letter"
            >
              <p class="mb-1.5 px-1 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/35">
                {{ section.letter }}
              </p>
              <div class="divide-y divide-white/5 rounded-xl border border-white/5 bg-surface-container-low/30 px-2">
                <GroupMemberRow
                  v-for="member in section.list"
                  :key="member.id"
                  :member="member"
                  :is-group-owner="group.owner_id === member.id"
                  :ranking-position="rankingPositionFor(member.id)"
                  compact
                  interactive
                  @select="openMemberSheet(member)"
                />
              </div>
            </div>
          </template>
        </div>

        <p v-if="removeMemberError" class="mt-3 font-body-sm text-body-sm text-error">
          {{ removeMemberError }}
        </p>
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
            <!-- Group name -->
            <div class="space-y-1">
              <label
                for="group-rename"
                class="font-label-caps text-label-caps px-1 uppercase text-on-surface-variant"
              >
                Nome do grupo
              </label>
              <input
                id="group-rename"
                v-model="groupNameInput"
                type="text"
                maxlength="100"
                :disabled="savingSettings"
                class="font-body-lg text-body-lg w-full rounded-t-lg border-b-2 border-outline-variant bg-surface-container-low px-4 py-3 text-on-surface placeholder:text-on-surface-variant/50 transition-[border-color,background-color] focus:border-primary focus:bg-surface-container focus:outline-none disabled:opacity-50"
                @blur="onGroupNameBlur"
              >
            </div>

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

            <!-- Danger zone: delete group -->
            <div class="border-t border-error/20 pt-6">
              <p class="mb-3 font-label-caps text-[10px] uppercase tracking-widest text-error/70">Zona de perigo</p>
              <button
                type="button"
                class="flex w-full items-center gap-3 rounded-xl border border-error/20 bg-error/5 px-4 py-3 transition-colors hover:bg-error/10 active:scale-[0.98]"
                @click="showDeleteDialog = true"
              >
                <span class="material-symbols-outlined text-[20px] text-error/70">delete_forever</span>
                <span class="font-label-caps text-label-caps text-error/90">Excluir grupo</span>
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

    <UiConfirmDialog
      :open="showDeleteDialog"
      title="Excluir grupo?"
      message="Todos os membros perderão acesso e o histórico de pontuação será apagado. Esta ação não pode ser desfeita."
      confirm-label="Continuar"
      cancel-label="Cancelar"
      danger
      @confirm="showDeleteDialog = false; showDeleteConfirm = true"
      @cancel="showDeleteDialog = false"
    />

    <UiTypeToConfirmDialog
      :open="showDeleteConfirm"
      title="Confirmar exclusão"
      :message="deleteConfirmMessage"
      :confirm-phrase="group?.name ?? ''"
      confirm-label="Excluir permanentemente"
      cancel-label="Cancelar"
      danger
      @confirm="handleDeleteGroup"
      @cancel="showDeleteConfirm = false"
    />

    <GroupMemberSheet
      :open="selectedMember !== null"
      :entry="selectedMember"
      :is-current-user="selectedMember ? isCurrentUser(selectedMember.user.id) : false"
      :is-group-owner="selectedMember ? group?.owner_id === selectedMember.user.id : false"
      :can-remove="canRemoveSelectedMember"
      :removing="removingMember"
      @close="closeMemberSheet"
      @remove="requestRemoveMember"
      @view-ranking="closeMemberSheetAndGoToRanking"
    />

    <UiConfirmDialog
      :open="showRemoveMemberDialog"
      title="Remover membro?"
      :message="`Remover ${selectedMember?.user.name ?? 'este membro'} do grupo? O histórico de pontuação será apagado e a pessoa não poderá entrar novamente pelo convite.`"
      confirm-label="Remover"
      cancel-label="Cancelar"
      danger
      @confirm="handleRemoveMember"
      @cancel="cancelRemoveMember"
    />

    <UiToast :visible="toastVisible" :message="toastMessage" />

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
