<script setup lang="ts">
import type { RankingEntry, RankingTabId } from '~/types/ranking'
import RankingTopGroups from '~/components/ranking/RankingTopGroups.vue'
import RankingRow from '~/components/ranking/RankingRow.vue'

const MAX_VISIBLE = 10

// Number of entries consumed by the leaders section (first 3 distinct position groups).
const podiumCount = computed(() => {
  let groups = 0
  let lastPos: number | null = null
  let count = 0
  for (const e of entries.value) {
    if (e.position !== lastPos) {
      if (groups >= 3) break
      groups++
      lastPos = e.position
    }
    count++
  }
  return count
})

definePageMeta({ middleware: 'auth' })

const { user } = useAuth()
const { recordGroupView, getInitialTab } = useRankingPrefs()
const { sortedGroups, entries, globalUserEntry, globalTotal, isGlobalTab, loading, error, fetchGroups, fetchRanking } = useRankings()

const activeTab = ref<RankingTabId>('global')
const showFullList = ref(false)
const searchQuery = ref('')

const tabs = computed(() => [
  { id: 'global' as const, label: 'Geral' },
  ...sortedGroups.value.map(group => ({
    id: group.id as RankingTabId,
    label: group.name,
  })),
])

const showViewAllButton = computed(() => entries.value.length > MAX_VISIBLE)

const userEntry = computed(() => {
  if (isGlobalTab.value) return globalUserEntry.value
  return entries.value.find(e => e.user.id === user.value?.id) ?? null
})

// User is "in the visible top" if they appear in the leaders section or preview.
const userInTopN = computed(() => {
  if (!userEntry.value) return false
  return entries.value
    .slice(0, MAX_VISIBLE)
    .some(e => e.user.id === userEntry.value!.user.id)
})

// Preview: entries after podium, capped so total visible is MAX_VISIBLE.
const previewEntries = computed<RankingEntry[]>(() => {
  const previewSize = Math.max(0, MAX_VISIBLE - podiumCount.value)
  const regular = entries.value.slice(podiumCount.value, podiumCount.value + previewSize)
  if (userInTopN.value || !userEntry.value) return regular
  if (previewSize <= 1) return [userEntry.value]
  return [...regular.slice(0, previewSize - 1), userEntry.value]
})

const showUserGap = computed(() => !!userEntry.value && !userInTopN.value)

const filteredEntries = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  if (!q) return entries.value
  return entries.value.filter(e => e.user.name.toLowerCase().includes(q))
})

const participantHint = computed(() => {
  const count = isGlobalTab.value ? globalTotal.value : entries.value.length
  if (count <= 1) return `${count} participante`
  return `${count} participantes`
})

onMounted(async () => {
  await fetchGroups()
  const initial = getInitialTab(sortedGroups.value.map(g => g.id))
  activeTab.value = initial
  await fetchRanking(initial)
})

watch(activeTab, (tabId) => {
  recordGroupView(tabId)
  fetchRanking(tabId)
})

function selectTab(tabId: RankingTabId) {
  activeTab.value = tabId
}

function isCurrentUser(entry: RankingEntry): boolean {
  return user.value?.id === entry.user.id
}

function openFullList() {
  showFullList.value = true
}

function closeFullList() {
  showFullList.value = false
  searchQuery.value = ''
}
</script>

<template>
  <div>
    <UiSubPageHeader title="CLASSIFICAÇÃO" />

    <!-- Tab bar: only shown when there are groups beyond "Geral" -->
    <div v-if="tabs.length > 1" class="relative mt-2">
      <section
        class="hide-scrollbar flex items-center gap-1.5 overflow-x-auto whitespace-nowrap px-margin pb-3"
        role="tablist"
        aria-label="Ranking por grupo"
      >
        <template v-for="(tab, i) in tabs" :key="tab.id">
          <!-- Separator between Geral and group tabs -->
          <div
            v-if="i === 1"
            class="mx-1 h-4 w-px shrink-0 self-center bg-white/10"
            aria-hidden="true"
          />
          <button
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.id"
            :class="[
              'max-w-[240px] shrink-0 truncate rounded-full px-3.5 py-1.5 font-label-caps text-label-caps transition-colors',
              activeTab === tab.id
                ? 'bg-primary text-on-primary'
                : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest',
            ]"
            @click="selectTab(tab.id)"
          >
            {{ tab.label }}
          </button>
        </template>
      </section>
      <div class="pointer-events-none absolute right-0 top-0 h-full w-10 bg-gradient-to-l from-background to-transparent" />
    </div>

    <!-- Loading -->
    <p
      v-if="loading"
      class="mt-8 px-margin font-body-lg text-body-lg text-on-surface-variant"
    >
      Carregando ranking…
    </p>

    <!-- Error -->
    <div v-else-if="error" class="mt-8 space-y-3 px-margin">
      <p class="font-body-lg text-body-lg text-error">{{ error }}</p>
      <button
        type="button"
        class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container py-3 font-label-caps text-label-caps uppercase text-on-surface transition-colors hover:bg-surface-container-high"
        @click="fetchRanking(activeTab)"
      >
        <span class="material-symbols-outlined text-[18px]">refresh</span>
        TENTAR NOVAMENTE
      </button>
    </div>

    <!-- Empty -->
    <p
      v-else-if="entries.length === 0"
      class="mt-8 px-margin font-body-lg text-body-lg text-on-surface-variant"
    >
      Nenhum participante no ranking ainda.
    </p>

    <template v-else>
      <!-- Participant count (compact, inline with tab bar when no groups) -->
      <p
        class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/40"
        :class="tabs.length > 1 ? 'px-margin' : 'mt-2 px-margin'"
      >
        {{ participantHint }}
      </p>

      <Transition name="page-fade" mode="out-in">
        <div :key="activeTab">
      <!-- Top leaders — keyed by tab so animation replays on tab switch -->
      <RankingTopGroups :entries="entries" :current-user-id="user?.id" />

      <!-- Preview list: capped at MAX_VISIBLE (or user substituted at last slot) -->
      <section class="mt-4 px-margin" :class="showViewAllButton ? 'pb-24' : 'pb-6'">
        <!-- Column header -->
        <div class="mb-3 flex items-center justify-between px-1">
          <span class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">Classificação</span>
          <span class="font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/50">Pontos</span>
        </div>

        <div class="space-y-2">
          <template v-for="(entry, i) in previewEntries" :key="entry.user.id">
            <!-- Gap separator: indicates user is outside top 10 -->
            <div
              v-if="showUserGap && i === previewEntries.length - 1"
              class="flex items-center gap-3 py-1"
            >
              <div class="h-px flex-1 border-t border-dashed border-white/10" />
              <span class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/35">
                sua posição · #{{ userEntry?.position }}
              </span>
              <div class="h-px flex-1 border-t border-dashed border-white/10" />
            </div>

            <RankingRow :entry="entry" :is-current-user="isCurrentUser(entry)" />
          </template>
        </div>

      </section>

      <!-- Floating Ver todos button -->
      <div
        v-if="showViewAllButton"
        class="pointer-events-none fixed inset-x-0 z-30 px-margin"
        style="bottom: calc(5.5rem + env(safe-area-inset-bottom))"
      >
        <button
          type="button"
          class="pointer-events-auto flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-surface-container/95 py-3 font-label-caps text-label-caps uppercase tracking-widest text-on-surface shadow-[0_-4px_24px_rgba(0,0,0,0.35)] backdrop-blur-xl transition-colors hover:bg-surface-container-high active:scale-[0.98]"
          @click="openFullList"
        >
          <span class="material-symbols-outlined text-[18px]">format_list_numbered</span>
          Ver todos · {{ isGlobalTab ? globalTotal : entries.length }}
        </button>
      </div>
        </div>
      </Transition>
    </template>

    <!-- Full ranking bottom sheet -->
    <Teleport to="body">
      <Transition name="overlay-fade">
        <div
          v-if="showFullList"
          class="fixed inset-0 z-40 bg-black/60 backdrop-blur-[2px]"
          @click="closeFullList"
        />
      </Transition>

      <Transition name="sheet-up">
        <div
          v-if="showFullList"
          class="fixed bottom-0 left-0 right-0 z-50 flex max-h-[90vh] flex-col rounded-t-2xl bg-surface-container"
          style="padding-bottom: env(safe-area-inset-bottom)"
        >
          <!-- Drag handle -->
          <div class="flex justify-center pb-2 pt-3">
            <div class="h-1 w-10 rounded-full bg-on-surface-variant/20" />
          </div>

          <!-- Sheet header -->
          <div class="flex items-center justify-between px-margin pb-3 pt-1">
            <div>
              <span class="font-headline-lg-mobile text-headline-lg-mobile uppercase tracking-tighter text-on-surface">
                Classificação
              </span>
              <span class="ml-2 font-label-caps text-[10px] text-on-surface-variant/40">
                {{ isGlobalTab ? globalTotal : entries.length }} participantes
              </span>
            </div>
            <button
              type="button"
              class="flex h-8 w-8 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-white/5"
              @click="closeFullList"
            >
              <span class="material-symbols-outlined text-[20px] leading-none">close</span>
            </button>
          </div>

          <!-- Search -->
          <div class="relative mx-margin mb-3">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] leading-none text-on-surface-variant/40">search</span>
            <input
              v-model="searchQuery"
              type="search"
              placeholder="Buscar participante…"
              class="w-full rounded-lg border border-white/10 bg-surface-container-high py-2.5 pl-9 pr-9 font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/40 focus:border-primary/40 focus:outline-none"
            >
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2"
              @click="searchQuery = ''"
            >
              <span class="material-symbols-outlined text-[14px] leading-none text-on-surface-variant/40">close</span>
            </button>
          </div>

          <!-- Result count while searching -->
          <p
            v-if="searchQuery"
            class="px-margin pb-2 font-label-caps text-[10px] uppercase tracking-widest text-on-surface-variant/40"
          >
            {{ filteredEntries.length }} resultado{{ filteredEntries.length !== 1 ? 's' : '' }}
          </p>

          <!-- List -->
          <div class="flex-1 overflow-y-auto">
            <div v-if="filteredEntries.length === 0" class="py-10 text-center">
              <span class="material-symbols-outlined text-[32px] text-on-surface-variant/20">search_off</span>
              <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant/50">
                Nenhum participante encontrado.
              </p>
            </div>

            <div v-else class="space-y-2 px-margin pb-4 pt-1">
              <RankingRow
                v-for="entry in filteredEntries"
                :key="entry.user.id"
                :entry="entry"
                :is-current-user="isCurrentUser(entry)"
              />
              <!-- Pin authenticated user below the list when outside global top 100 -->
              <template v-if="!searchQuery && isGlobalTab && globalUserEntry && !filteredEntries.some(e => e.user.id === user?.id)">
                <div class="flex items-center gap-3 py-1">
                  <div class="h-px flex-1 border-t border-dashed border-white/10" />
                  <span class="font-label-caps text-[9px] uppercase tracking-widest text-on-surface-variant/35">sua posição</span>
                  <div class="h-px flex-1 border-t border-dashed border-white/10" />
                </div>
                <RankingRow :entry="globalUserEntry" :is-current-user="true" />
              </template>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
